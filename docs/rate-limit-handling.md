# Rate Limit Handling — Scope

## Overview

Eventaroo ingests events from three external APIs (Data Thistle, Ticketmaster, Billetto) and calls the Anthropic API for AI-based category classification. Each has its own rate limit model. This document captures what exists today, what each provider's limits look like, and what needs to be built to protect our quotas at scale.

---

## Current State

### What's Already Handled

**Data Thistle (`DataThistleImporter`)** — partially covered. After each paginated request the importer reads the `X-RateLimit-Remaining` response header. If it drops below 10 the importer logs a warning and stops pagination early, discarding subsequent pages for that run. This is a reasonable last-resort brake but it offers no proactive throttling — we only act after the quota is almost gone.

**Ticketmaster (`TicketmasterImporter`)** — not handled. The importer makes a single hard-capped request (200 events) with no header inspection, no quota tracking, and no response to 429s.

**Ticketmaster Feed (`TicketmasterFeedImporter`)** — not applicable in the same way. The feed is a single file download rather than a paginated API. HTTP errors during download are caught but there is no retry or backoff logic.

**Billetto (`BillettoImporter`)** — not handled. The importer paginates via `has_more` / `next_url` with a 50-page hard cap but does not read the `X-Ratelimit-Remaining` header or respond to 429s.

**Anthropic (`AiCategoryClassifier`, `AiEventClassifier`)** — not handled. Both classifiers make HTTP calls with no rate awareness. `ClassifyEventJob` has 3 retries with 60-second backoff, which provides some resilience, but there is no proactive throttling or queue-level concurrency control.

---

## Provider Rate Limits

### Data Thistle

Data Thistle does not publish its rate limits publicly — they are account-specific and provided at signup. What we know from the API responses:

| Header | Meaning |
|---|---|
| `X-RateLimit-Remaining` | Calls remaining in the current window |
| `X-Next` | URL of the next page (absent when pagination is complete) |

**Key risk:** The limit is almost certainly a monthly quota rather than a per-minute cap, given the `since`-based incremental fetch pattern. Exhausting it mid-month means no updates until the window resets.

**Recommended action:** Log the `X-RateLimit-Remaining` value on every request (not just when it's low) so we can build a picture of our real quota burn rate over time. Store the value in `IngestionLog`.

---

### Ticketmaster Discovery API

Publicly documented limits:

| Limit type | Value |
|---|---|
| Daily quota | 5,000 API calls per day |
| Rate per second | 5 requests per second |
| Pagination depth | `size × page < 1,000` (hard platform constraint) |

Rate limit response headers:

| Header | Meaning |
|---|---|
| `Rate-Limit` | Total daily quota (5,000) |
| `Rate-Limit-Available` | Remaining calls today |
| `Rate-Limit-Over` | `1` if quota exceeded, `0` otherwise |
| `Rate-Limit-Reset` | Unix timestamp of next quota reset |

**Key risk:** `TicketmasterImporter` is currently our fallback when `TICKETMASTER_FEED_URL` is not set. If it ever needed to paginate (which it cannot currently), 5,000 calls/day at 200 events per call = 1,000,000 events maximum — adequate. The real concern is the 5 req/s cap and the `Rate-Limit-Over` flag, which we currently ignore entirely.

**Recommended action:** Once the Discovery Feed is the primary source, the Discovery API is low-traffic. Still worth reading `Rate-Limit-Available` on each request and logging it. Add 200ms inter-request delay if we ever add pagination to `TicketmasterImporter`.

---

### Ticketmaster Discovery Feed

A separate access tier from the Discovery API. When configured (`TICKETMASTER_FEED_URL`), this replaces the paginated API with a single bulk JSONL download.

**Key risk:** The file can be several hundred MB. No per-request quota concern, but the download must be streamed (already implemented via `gzopen` / `gzgets`) and the temp file must always be cleaned up (already implemented in a `finally` block). The main risk is network timeout on a slow connection or a partial download being silently treated as complete.

**Recommended action:** Validate that the HTTP response code is 200 before processing. Check that the temp file is non-empty before decompressing. Log file size at download time to `IngestionLog`.

---

### Billetto

Billetto uses a **complexity-based token budget** rather than a flat request count:

| Header | Meaning |
|---|---|
| `X-Ratelimit-Cost` | Token cost of the current request |
| `X-Ratelimit-Limit` | Total token budget per window |
| `X-Ratelimit-Remaining` | Tokens remaining in the current window |
| `X-Ratelimit-Retry-After` | Milliseconds until budget resets (on 429) |

**Key behaviours:**
- Returns HTTP 429 when the token budget is exceeded
- If a request pushes the balance negative, a longer cooldown period applies
- Higher-complexity requests (larger page sizes, more filters) cost more tokens

**Key risk:** We currently paginate up to 50 pages with no header inspection. A large ingestion run with expensive queries could push the budget negative, triggering an extended cooldown that blocks subsequent runs.

**Recommended action:** Read `X-Ratelimit-Remaining` and `X-Ratelimit-Cost` after each page. If remaining drops below `2 × cost` (i.e. fewer than two pages worth of budget left), stop paginating early and log a warning. On a 429 response, read `X-Ratelimit-Retry-After` and either sleep for that duration (if short) or abort the run and record the failure in `IngestionLog`.

---

### Anthropic (Claude Haiku)

We use `claude-haiku-4-5-20251001` for category classification. Rate limits are tier-based:

| Tier | Spend threshold | RPM | Input TPM | Output TPM |
|---|---|---|---|---|
| Tier 1 | $0–$100/month | 50 | 50,000 | 10,000 |
| Tier 2 | $101–$500/month | 1,000 | 450,000 | 90,000 |
| Tier 3 | $501–$1,000/month | 2,000 | 1,000,000 | 200,000 |
| Tier 4 | $1,001+/month | 4,000 | 4,000,000 | 800,000 |

The Batch API (async) has the same RPM tiers but much higher queue depth and is significantly cheaper per token. Classification requests are not time-sensitive.

**Key risk:** `ClassifyEventJob` is dispatched immediately after every newly created event. During a first-time bulk ingestion this could mean hundreds of jobs hitting the API simultaneously. At Tier 1 that's 50 RPM — easily breached. At Tier 1 with a prompt of ~300 input tokens per call, we can process roughly 166 classifications per minute before hitting the 50,000 ITPM ceiling.

**Recommended action:** Introduce queue concurrency limits and throttling for the `classification` queue. Log the HTTP status code and any `retry-after` header from Anthropic responses. Consider switching `ClassifyEventJob` to use the [Batch API](https://docs.anthropic.com/en/api/creating-message-batches) for bulk ingestion runs, which is 50% cheaper and not subject to the same RPM limits.

---

## What Needs Building

### 1. Rate Limit Header Logging in `IngestionLog`

**Priority: High**

Add columns to `ingestion_logs` for recording the rate limit state at the end of each run:

```
rate_limit_remaining  int  nullable
rate_limit_total      int  nullable
rate_limit_reset_at   datetime  nullable
```

Each importer should return these values alongside the event data (or store them on the instance). `EventIngestionService` writes them into the log record after each provider finishes. This makes quota burn visible without needing to instrument every API call individually.

---

### 2. Billetto — 429 Response Handling

**Priority: High**

In `BillettoImporter::fetch()`, after each HTTP response:

1. Check `$response->status() === 429`. If so, read `X-Ratelimit-Retry-After` (in milliseconds).
2. If the retry window is under 30 seconds, sleep and retry the same page once.
3. If the retry window is over 30 seconds (or on second consecutive 429), stop paginating, log a warning, and return whatever events have been collected so far. The `IngestionLog` record should reflect `status = 'partial'`.
4. Read `X-Ratelimit-Remaining` and `X-Ratelimit-Cost` on every successful response. If remaining < `cost * 2`, stop early as a proactive measure.

---

### 3. Ticketmaster Discovery API — Header Inspection

**Priority: Medium**

In `TicketmasterImporter::fetch()` (used as fallback when feed URL is not set):

1. After the HTTP response, read `Rate-Limit-Available` and log it alongside the ingestion result.
2. If `Rate-Limit-Over` is `1`, do not attempt any further requests and record this in the log.
3. Add a 200ms delay between requests if pagination is ever added to this importer.

No urgency here while `TicketmasterFeedImporter` is the primary path, but worth doing now to protect the Discovery API fallback.

---

### 4. Data Thistle — Lower the `X-RateLimit-Remaining` Threshold + Log All Values

**Priority: Medium**

The current threshold of 10 is very tight for a monthly quota. Consider raising it to something like `5%` of the total limit (once we know the total). In the meantime:

1. Log the `X-RateLimit-Remaining` value to the Laravel log on every request, not just when it's critically low.
2. Store the final `X-RateLimit-Remaining` value in `IngestionLog` so we can track burn rate across runs.
3. Update the early-stop threshold to a configurable value via `config('services.datathistle.rate_limit_threshold', 10)` so it can be tuned without a code deploy.

---

### 5. Ticketmaster Feed — Download Validation

**Priority: Medium**

In `TicketmasterFeedImporter::fetch()`:

1. Verify the response status code is 200 before writing to disk. On non-200, throw an exception so `EventIngestionService` records the failure.
2. After download, check the temp file size is greater than 0. If empty, throw before attempting decompression.
3. Log the file size (in MB) to the ingestion stats for visibility.

---

### 6. Anthropic — Classification Queue Throttling

**Priority: Medium** (becomes High during a bulk ingestion run)

Two complementary changes:

**a) Queue concurrency limit for `classification` queue**

In `config/queue.php` (or via Horizon configuration if Horizon is in use), set the `classification` queue to a maximum of 10 concurrent workers. This prevents a bulk ingestion from flooding the Anthropic API.

**b) Rate limiting via Laravel's built-in throttle**

In `ClassifyEventJob::handle()`, wrap the Anthropic API call in a rate-limit check using Laravel's `RateLimiter`:

```php
RateLimiter::attempt(
    key: 'anthropic-classification',
    maxAttempts: 40,          // conservative below the 50 RPM Tier 1 limit
    callback: fn() => $this->runClassification(),
    decaySeconds: 60,
);
```

If the rate limit is exceeded, release the job back onto the queue with a 5-second delay rather than failing it.

**c) Log AI call counts in `IngestionLog`**

`EventIngestionService` already tracks `created` event counts. Add an `ai_classified` counter that increments each time `AiCategoryClassifier::classify()` is called. Write this to `IngestionLog` so AI API usage can be monitored per run.

---

### 7. Centralised HTTP Retry Policy

**Priority: Low**

All importers use Laravel's `Http` facade directly with no shared retry configuration. Create a single `ResilientHttpClient` helper (or a macro registered in `AppServiceProvider`) that wraps `Http::retry()` with a standard exponential backoff for transient errors (408, 429, 500, 502, 503, 504):

```php
Http::retry(3, fn(int $attempt) => $attempt * 1000, throw: false)
    ->withOptions(['timeout' => 30])
```

This standardises retry behaviour across all importers without each one needing to reimplement it.

---

## Summary Table

| Provider | Limit type | Current handling | What to build |
|---|---|---|---|
| Data Thistle | Monthly quota (account-specific) | Stops at `X-RateLimit-Remaining < 10` | Log all values; configurable threshold; store in `IngestionLog` |
| Ticketmaster Discovery | 5,000/day; 5 req/s | None | Read `Rate-Limit-Available`; log; stop on `Rate-Limit-Over` |
| Ticketmaster Feed | Single file download | HTTP errors caught | Validate status code and file size before processing |
| Billetto | Token budget per window | None | Read `X-Ratelimit-Remaining`; handle 429 with `Retry-After`; stop early proactively |
| Anthropic | 50–4,000 RPM (tier-based) | 3 retries with 60s backoff on job | Queue concurrency cap; `RateLimiter` throttle; log call counts |

---

## Out of Scope

- Increasing API quotas (commercial arrangement with each provider — not a code task)
- Switching to the Anthropic Batch API (could be a follow-up task once classification volume is measured)
- Webhook-based ingestion from Billetto (their API supports webhooks for new event notifications — worth revisiting if polling becomes too expensive)
