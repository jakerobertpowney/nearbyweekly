# Scope: Schema.org Event Crawler

Two sequential pieces of work. Part 1 populates a registry of UK domains that publish event data. Part 2 crawls those domains weekly and ingests the events they contain.

---

## Part 1 — Common Crawl Domain Discovery

### What It Does

Queries the Common Crawl dataset to find every UK-based domain that has ever published `@type: Event` JSON-LD markup. The results are written to a new `external_websites` table. This job runs once a month on a dedicated queue and is the source of truth for which domains Part 2 should crawl.

### Data Source

[Web Data Commons (WDC)](http://webdatacommons.org/structureddata/) publishes pre-extracted schema.org structured data from every Common Crawl snapshot as downloadable NDJSON files. Their `schemaorg-types/Event/` dataset contains every `@type: Event` object found in the crawl, with the source URL included in each record. This is significantly simpler than processing raw WARC files and avoids needing AWS access.

The secondary source is the [CC Index API](https://index.commoncrawl.org/), queried for UK TLDs to catch any domains not covered by WDC.

Both sources are free and require no authentication.

### New Migration — `external_websites`

```
external_websites
  id                      bigint, PK
  domain                  string, unique         — e.g. 'ronniescotts.co.uk'
  events_page_url         string, nullable       — best known events listing URL for this domain
  sitemap_url             string, nullable       — cached sitemap location (from robots.txt or discovery)
  robots_txt              text, nullable         — cached robots.txt content
  robots_txt_fetched_at   timestamp, nullable
  crawl_status            enum, default 'pending'  — pending | active | paused | error | blocked
  blocked_reason          string, nullable       — 'adult-content' | 'malicious' | 'robots-disallowed' | 'no-events-found' | 'probe-failed'
  consecutive_failures    int, default 0         — auto-paused after 5
  last_scanned_at         timestamp, nullable
  next_scan_at            timestamp, nullable    — allows manual override of next crawl time
  events_found_last_scan  int, default 0
  total_events_ingested   int, default 0
  discovery_source        string                 — 'wdc', 'cc-index', 'manual'
  discovery_crawl_id      string, nullable       — e.g. 'CC-MAIN-2026-13' for traceability
  notes                   text, nullable
  created_at, updated_at
```

Add an index on `crawl_status` and a composite index on `[crawl_status, next_scan_at]` for the weekly dispatch query.

### New Model — `ExternalWebsite`

Standard Eloquent model with fillable and casts matching the migration. Add:

- A `scopeActive($query)` local scope filtering `crawl_status = 'active'`
- A `scopeDue($query)` local scope filtering `next_scan_at <= now()` or `next_scan_at IS NULL`
- A `markFailure()` method that increments `consecutive_failures` and sets `crawl_status = 'error'` when the count reaches 5
- A `markSuccess(int $eventsFound)` method that resets `consecutive_failures`, updates `last_scanned_at`, increments `total_events_ingested`, sets `events_found_last_scan`, and sets `next_scan_at = now()->addWeek()`
- A `markBlocked(string $reason)` method that sets `crawl_status = 'blocked'` and `blocked_reason` to the given reason. Blocked domains are permanently excluded from all crawl dispatch queries and cannot be re-queued without a manual status reset.

### New Job — `DiscoverFromCommonCrawlJob`

`app/Jobs/DiscoverFromCommonCrawlJob.php` — implements `ShouldQueue` with `Queueable`.

```php
public string $queue = 'common-crawl';
public int $timeout = 7200; // 2 hours — WDC files are large
public int $tries = 2;
```

**Execution steps:**

1. Resolve the latest WDC snapshot URL from `http://webdatacommons.org/structureddata/` (the index page lists available releases). Target the most recent `schemaorg-types/Event/` dataset.

2. Download the WDC Event NDJSON file(s) to `storage/app/temp/wdc-events-{date}.ndjson.gz`. Stream the download — do not buffer the whole file in memory.

3. Decompress and read line by line. For each line:
   - Decode JSON
   - Extract the `url` field
   - Parse the domain using `parse_url($url, PHP_URL_HOST)`, strip leading `www.`
   - Skip if domain does not end in a UK TLD: `.co.uk`, `.org.uk`, `.ac.uk`, `.gov.uk`, `.me.uk`, `.net.uk`, `.sch.uk`, `.nhs.uk`, `.police.uk`, `.uk`
   - Upsert into `external_websites` using `domain` as the unique key. On insert: set `discovery_source = 'wdc'`, `discovery_crawl_id` to the snapshot ID, `crawl_status = 'pending'`, `events_page_url` to the source URL. On conflict: skip (do not overwrite an already-active domain's settings).

4. After WDC processing, run a supplementary CC Index query for any UK domains missed:
   - `GET https://index.commoncrawl.org/CC-MAIN-{latest}-index?url=*.co.uk&output=json&matchType=domain&filter=mime:text/html&limit=10000`
   - Repeat for `*.org.uk`, `*.ac.uk`, `*.gov.uk`
   - For each result URL, sample the page live (via HTTP) and check for `<script type="application/ld+json">` containing `"@type":"Event"` before inserting — this keeps the CC Index results high-signal rather than adding every `.co.uk` domain in the crawl index.

5. Delete the temporary WDC file after processing.

6. For every newly inserted domain (rows where `crawl_status` is still `'pending'` after the upsert), dispatch one `ProbeExternalWebsiteJob`. Do not probe domains that already existed in the table.

7. Log a summary: how many new domains inserted, how many already existed, how many probes dispatched, total `external_websites` count.

**Important:** This job must never update `crawl_status` from `active` to `pending`, never overwrite `robots_txt`, and never reset `consecutive_failures`. It is an additive discovery job only — it adds new rows but does not modify existing ones.

---

### New Job — `ProbeExternalWebsiteJob`

`app/Jobs/ProbeExternalWebsiteJob.php` — runs automatically for every newly discovered domain. Performs a series of checks and either promotes the domain to `active` or blocks it with a reason. Runs on the `crawlers` queue so it does not occupy the main worker.

```php
public string $queue = 'crawlers';
public int $timeout = 60;
public int $tries = 2;
public int $backoff = 120;
```

**Execution steps — run in this order, short-circuit on first failure:**

**Step 1 — Domain keyword pre-filter (no HTTP request)**

Check the domain string itself against a hardcoded blocklist of terms strongly associated with adult content. This is a fast, zero-cost gate that catches the most obvious cases before making any network request.

Terms to block: `xxx`, `porn`, `escort`, `adult`, `sex`, `fetish`, `erotic`, `nude`, `cam`, `onlyfans`, `dating`, `hookup`.

Match as a whole word or segment — `sexshop.co.uk` matches, `essex-events.co.uk` does not. If matched, call `$website->markBlocked('adult-content')` and return.

**Step 2 — TLD trust fast-track**

If the domain ends in `.ac.uk`, `.gov.uk`, `.nhs.uk`, `.police.uk`, or `.sch.uk`, skip step 3 entirely. These are restricted registration TLDs — a university, government body, NHS trust, or school cannot be an adult content site by definition. Jump directly to step 4.

**Step 3 — RTA label check (adult content declaration)**

The [RTA (Restricted To Adults)](https://rtalabel.org) label is the UK/international industry standard for adult site self-declaration. Compliant adult sites embed the label in their HTTP response headers or HTML. Fetch the domain's homepage and check two locations:

- HTTP response header: `Rating: RTA-5042-1996-1400-1577-RTA`
- HTML meta tag: `<meta name="rating" content="RTA-5042-1996-1400-1577-RTA">` or `<meta name="rating" content="adult">` or `<meta name="rating" content="mature">`

Check both case-insensitively. If either is found, call `$website->markBlocked('adult-content')` and return.

Note: not all adult sites carry the RTA label, so this is not exhaustive on its own. It catches self-declaring sites and is a zero-false-positive check — if the label is present, the domain is explicitly declaring itself adult-only.

**Step 4 — Event markup verification**

Fetch `$website->events_page_url` (the URL found by WDC). Check:

- The page returns a 200 response
- At least one `<script type="application/ld+json">` tag contains `@type: Event`
- At least one of those events has a UK postcode in `location.address.postalCode`

If the page returns a non-200, or no Event markup is found, or no events have UK postcodes, leave `crawl_status` as `'pending'` and set `blocked_reason = 'no-events-found'` with a note explaining which check failed. These domains are flagged for manual review rather than auto-blocked — the markup may have moved, the URL may have changed, or the WDC data may simply be stale.

If all checks pass, set `crawl_status = 'active'`.

**Step 5 — robots.txt pre-fetch**

As a courtesy, fetch and cache `robots.txt` for newly activated domains immediately so the first weekly `CrawlExternalWebsiteJob` does not need to fetch it. This is a best-effort step — failure here should not roll back the `active` status.

---

### New Command — `websites:discover`

`app/Console/Commands/WebsitesDiscoverCommand.php` — wraps `DiscoverFromCommonCrawlJob::dispatchSync()` for manual runs. Outputs a summary table of new vs existing domains. Signature: `websites:discover {--dry-run}`.

### Scheduler Entry

In `routes/console.php`, add on the first Tuesday of each month. Common Crawl releases new snapshots roughly monthly, so this cadence keeps the registry fresh without redundant reruns:

```php
// Discover new UK event domains from Common Crawl — first Tuesday of each month.
Schedule::job(new DiscoverFromCommonCrawlJob)->monthlyOnWeekday(2, '03:00')
    ->onOneServer();
```

`onOneServer()` prevents the job running on multiple workers simultaneously if the queue has multiple consumers. The `03:00` start time avoids overlap with the Tuesday ingestion jobs that start at `06:00`.

---

## Part 2 — Schema.org Event Crawler

### What It Does

Loops through every `active` domain in `external_websites`, fetches its events pages, extracts `@type: Event` JSON-LD markup, normalises the events to Eventaroo's internal format, and pipes them through the existing `EventIngestionService::persist()`. Runs weekly, in parallel across all active domains, on a dedicated `crawlers` queue.

### Politeness Rules

Every crawl must respect the following — no exceptions:

- **User-Agent:** `Eventaroo-Bot/1.0 (+https://eventaroo.co.uk/bot)`. This is crawlable by the site operator and identifies the bot.
- **robots.txt:** Fetch and cache per domain before any crawl. Refresh if `robots_txt_fetched_at` is older than 24 hours. If robots.txt is unreachable, treat the domain as fully disallowed and mark a failure — do not guess.
- **Disallow directives:** Respect all `Disallow` paths under `User-agent: *` and `User-agent: Eventaroo-Bot`. Do not fetch any disallowed URL.
- **Crawl-delay:** Respect the `Crawl-delay` directive if present. Default to a 2-second delay between requests to the same domain if no directive is set.
- **Sitemap discovery:** Read the `Sitemap:` directive from robots.txt first. Fall back to trying `/sitemap.xml`, `/sitemap_index.xml`, `/sitemap-events.xml`, `/whats-on/sitemap.xml` in order. Stop after the first successful response.
- **Page cap:** Do not fetch more than 300 URLs per domain per crawl run, regardless of sitemap size. This prevents runaway crawls on large sites.
- **Response size cap:** Skip any page whose `Content-Length` header exceeds 1 MB, or abort the stream if the body exceeds 1 MB mid-download. Large pages are almost always not event listings.
- **No JavaScript rendering:** Extract markup from the raw HTML response only. If a site requires JavaScript to render its events, it will not have static JSON-LD and will naturally return zero results — mark it and move on.
- **noindex respect:** If a page carries `<meta name="robots" content="noindex">` or `X-Robots-Tag: noindex`, do not include URLs from that page in further discovery.

### New Job — `DispatchWebsiteCrawlsJob`

`app/Jobs/DispatchWebsiteCrawlsJob.php` — a lightweight dispatch job.

```php
public string $queue = 'default';
public int $timeout = 60;
```

Queries `ExternalWebsite::active()->due()->get()` and dispatches one `CrawlExternalWebsiteJob` per record. Does nothing else — all logic lives in the per-domain job.

### New Job — `CrawlExternalWebsiteJob`

`app/Jobs/CrawlExternalWebsiteJob.php` — accepts an `ExternalWebsite $website` (or its ID).

```php
public string $queue = 'crawlers';
public int $timeout = 600;  // 10 min per domain
public int $tries = 2;
public int $backoff = 300;
```

**Execution steps:**

1. **Fetch/validate robots.txt.** If `robots_txt_fetched_at` is null or older than 24 hours, fetch `https://{domain}/robots.txt`. Store the raw content on the `external_websites` record. If the fetch fails (non-200, timeout), call `$website->markFailure()` and return early.

2. **Check crawl permission.** Parse the cached robots.txt. If the target domain disallows `Eventaroo-Bot` or `*` for `/`, call `$website->markFailure()` and return.

3. **Discover sitemap.** Read the `Sitemap:` directive from robots.txt. If absent, probe the fallback paths listed above. Store the resolved sitemap URL on `$website->sitemap_url`. If no sitemap is found, fall back to crawling `$website->events_page_url` directly (the URL discovered in Part 1). If neither is available, call `$website->markFailure()` and return.

4. **Parse sitemap for event URLs.** Download and parse the sitemap XML. Filter URLs by path — include paths containing: `/events`, `/whats-on`, `/what-s-on`, `/listings`, `/programme`, `/performances`, `/shows`, `/gigs`, `/tickets`. If the sitemap is a sitemap index, follow one level of child sitemaps (do not recurse further). Apply the 300-URL cap at this stage — take the first 300 matching URLs.

5. **Extract events.** For each URL:
   - Check the URL is not disallowed by robots.txt before fetching.
   - Apply the crawl delay (from robots.txt `Crawl-delay` or 2 seconds default).
   - Fetch the page HTML.
   - Find all `<script type="application/ld+json">` tags.
   - Decode each tag's content as JSON. Skip on decode failure.
   - Handle both single objects (`{"@type":"Event", ...}`) and arrays (`[{"@type":"Event"}, ...]`).
   - Handle `@graph` arrays: `{"@graph": [{"@type":"Event"}, ...]}`.
   - Collect all objects where `@type` is `"Event"` or `["Event", ...]`.

6. **Normalise.** Map schema.org fields to Eventaroo's internal format:

   | schema.org | Eventaroo field | Notes |
   |---|---|---|
   | `name` | `title` | |
   | `startDate` | `starts_at` | ISO 8601; parse with Carbon |
   | `endDate` | `ends_at` | nullable |
   | `location.name` | `venue_name` | |
   | `location.address.streetAddress` | `address_line` | |
   | `location.address.addressLocality` | `city` | |
   | `location.address.postalCode` | `postcode` | Required for geocoding; skip event if absent |
   | `url` or page URL | `url` | Prefer `url` property; fall back to the page URL it was extracted from |
   | `description` | `description` | |
   | `image.url` or `image` (string) | `image_url` | |
   | `"schema-org"` | `source` | Fixed value |
   | MD5 of `url` | `external_id` | Stable deduplication key when no native ID exists |

   Skip any event where `starts_at` is in the past. Skip any event with no UK postcode — these cannot be matched to users.

7. **Persist.** Pass each normalised event to `EventIngestionService::persist()` (inject the service via the job's `handle()` method). Track created/updated/skipped counts.

8. **Update website record.** Call `$website->markSuccess($eventsFoundCount)` regardless of whether any events were found — zero events is a valid outcome, not a failure. Only call `$website->markFailure()` if the crawl itself could not complete (network error, robots.txt block, no sitemap).

9. **Write ingestion log.** Create an `IngestionLog` record with `provider = 'schema-org:' . $website->domain` so per-domain stats are visible in the same monitoring infrastructure as DataThistle and Ticketmaster.

### New Command — `websites:crawl`

`app/Console/Commands/WebsitesCrawlCommand.php` — for manual runs and testing.

Signature: `websites:crawl {domain? : Crawl a single domain} {--dry-run}`

- With no argument: dispatches `DispatchWebsiteCrawlsJob` synchronously.
- With a domain: finds the matching `ExternalWebsite` record and runs `CrawlExternalWebsiteJob::dispatchSync()` for it.
- With `--dry-run`: runs the full crawl and outputs a count of events that would be persisted, but does not write to the database.

### Domain Activation Flow

The probe job handles the majority of domains automatically. After `DiscoverFromCommonCrawlJob` runs, the expected outcomes are:

| Outcome | `crawl_status` | `blocked_reason` | Action required |
|---|---|---|---|
| All probe checks passed | `active` | — | None — crawled automatically next Tuesday |
| Keyword match or RTA label | `blocked` | `adult-content` | None |
| Malicious domain (future) | `blocked` | `malicious` | None — reserved for manual blocks |
| robots.txt disallows | `paused` | `robots-disallowed` | None — re-check periodically if desired |
| Event markup no longer present | `pending` | `no-events-found` | Manual review — markup may have moved |
| Probe itself failed (timeout etc.) | `pending` | `probe-failed` | Manual review or re-dispatch |

The only domains requiring human attention are those stuck in `pending` after the probe — typically a small percentage where the WDC discovery URL has gone stale. These can be reviewed and manually promoted via Tinker:

```php
ExternalWebsite::where('domain', 'example.co.uk')->update(['crawl_status' => 'active']);
```

Blocked domains can be unblocked the same way if a domain has been incorrectly flagged:

```php
ExternalWebsite::where('domain', 'example.co.uk')
    ->update(['crawl_status' => 'pending', 'blocked_reason' => null]);
// Then re-dispatch the probe:
ProbeExternalWebsiteJob::dispatch(ExternalWebsite::where('domain', 'example.co.uk')->first());
```

A future admin dashboard for bulk-reviewing `pending` domains would be the natural next step, but is out of scope here.

### Scheduler Entries

Add to `routes/console.php`:

```php
// Dispatch per-domain schema.org crawl jobs on Tuesdays alongside other ingestion.
// Runs on the 'crawlers' queue — separate from the main 'default' queue.
Schedule::job(new DispatchWebsiteCrawlsJob)->weeklyOn(2, '05:30')
    ->onOneServer();
```

`05:30` places the dispatch before the DataThistle job at `06:00`. The per-domain `CrawlExternalWebsiteJob` jobs fan out across the `crawlers` queue workers at whatever rate the queue processes them — the scheduler only triggers the dispatch.

### New Queue — `crawlers`

Add `crawlers` to the list of named queues in your queue worker configuration (`.env` / `supervisord`). This ensures schema.org crawl jobs cannot starve the `default` queue where newsletter send jobs run.

```
QUEUE_WORKER_QUEUES=default,crawlers,common-crawl
```

---

## What Does Not Change

- `EventIngestionService::persist()` — called directly; no modifications
- `EventImporter` contract — not implemented by the crawler (domain state management makes it a poor fit for the stateless importer interface)
- `FetchEventsJob` — not involved in either piece of work
- DataThistle, Ticketmaster, Billetto importers — unchanged
- Deduplication logic — unchanged; `source = 'schema-org'` + `external_id = MD5(url)` slots naturally into the existing `source + external_id` unique constraint

---

## Testing

**Discovery:**
- Run `php artisan websites:discover --dry-run` and confirm it logs domain counts without writing to the database.
- Run without `--dry-run` and confirm `ProbeExternalWebsiteJob` is dispatched for each newly inserted domain.

**Probe — adult content:**
- Insert a domain containing `xxx` in its name and confirm the probe sets `crawl_status = 'blocked'` and `blocked_reason = 'adult-content'` without making any HTTP requests.
- Insert a domain whose homepage returns `<meta name="rating" content="RTA-5042-1996-1400-1577-RTA">` and confirm the same outcome.

**Probe — TLD fast-track:**
- Insert a `.ac.uk` domain and confirm step 3 is skipped — the probe should reach step 4 (event markup check) without fetching the homepage for RTA labels.

**Probe — clean domain:**
- Insert `ronniescotts.co.uk` manually with `crawl_status = 'pending'` and dispatch `ProbeExternalWebsiteJob` for it. Confirm it ends with `crawl_status = 'active'`.

**Crawl:**
- With `ronniescotts.co.uk` active, run `php artisan websites:crawl ronniescotts.co.uk --dry-run`. Confirm events are discovered and output without being persisted.
- Confirm a domain with `Disallow: /` in its robots.txt is skipped and `consecutive_failures` is incremented.
- Confirm that reaching 5 consecutive failures sets `crawl_status = 'error'` and the domain is excluded from `DispatchWebsiteCrawlsJob`.

**Scheduler:**
- Run `php artisan schedule:list` and confirm all new schedule entries appear with correct timings.
