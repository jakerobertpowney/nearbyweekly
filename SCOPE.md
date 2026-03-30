# NearbyWeekly — Event Curation & Newsletter Scope

> This document outlines the full scope for the event curation pipeline: what's already built, what needs completing, and what's worth planning for now before it becomes a problem later.

---

## What's Already Built

The foundation is strong. Before planning what's next, it's worth being clear on what exists and works:

**Data layer** — All core migrations are in place: `users`, `events`, `interests`, `user_interests`, `newsletter_runs`, `newsletter_items`, and `login_links`. The schema is well-structured and scale-aware.

**Ingestion layer** — `EventIngestionService` orchestrates fetching from multiple providers. `DataThistleImporter` and `TicketmasterImporter` both exist and normalise their respective API responses into a shared event format. A `FakeImporter` handles local development. Events are deduplicated by `source + external_id`.

**Matching engine** — `EventMatcher` filters events by the user's interest slugs, upcoming window (next 3 weeks), and radius from their geocoded postcode. It scores events across four dimensions: category match (30pts), proximity (up to 35pts), recency (up to 30pts), and manual boost (`score_manual`).

**Distance calculation** — `DistanceCalculator` implements the Haversine formula. `PostcodesIoGeocoder` calls postcodes.io to convert UK postcodes into lat/lng coordinates at onboarding time.

**Newsletter pipeline** — `SendWeeklyNewsletterJob` is a queued job that runs the matcher, records `NewsletterItem` rows, and sends `WeeklyNewsletterMail` per user. `SendWeeklyNewsletterCommand` (`newsletters:send-weekly`) loops over eligible users and dispatches one job per user.

**Auth & onboarding** — Magic link auth, 2FA, full onboarding flow (interests → location → radius → email), and a preferences management screen are all complete.

**CLI tooling** — `events:fetch`, `events:list`, `events:boost`, `events:create-manual`, and `matches:preview` are all working commands.

---

## Phase 1 — Ingestion Pipeline

This is the most important system to get right. Everything downstream depends on the quality and coverage of the events stored in the database.

### 1.1 Scheduled Ingestion

**What needs building:** The ingestion job exists (`FetchEventsJob`) but there's no Laravel scheduler entry for it. Events need to be fetched on a recurring schedule, independently of when newsletters go out.

**Recommended approach:** Run ingestion once daily, scheduled via `app/Console/Kernel.php`. Each provider should run as a separate dispatched job so a failure in one doesn't block the others. After each run, write a summary to an `ingestion_logs` table (provider, fetched, created, updated, skipped, failed, ran_at).

**Worth planning now:** The newsletter send and the ingestion run must be decoupled. Don't trigger ingestion at send time — events should already be in the database when the send job runs. Run ingestion Tuesday/Wednesday; send the newsletter Thursday.

### 1.2 Data Thistle — Incremental Fetching via `since`

**Current state:** `DataThistleImporter::fetch()` only passes a `limit` to the API. Every run re-fetches the same pool of events, which is wasteful and means you're bounded by whatever `limit` you set rather than getting everything new.

**What needs building:** The Data Thistle API supports a `since` query parameter (ISO 8601 timestamp) that returns only events created or updated after that point. This should be used on every run after the first.

**Recommended approach:**

Add an `ingestion_logs` table with columns: `provider` (string), `ran_at` (datetime), `fetched`, `created`, `updated`, `skipped`, `failed` (integers), and `status` (success/failed). At the start of each DataThistle ingestion run, query this table for the most recent successful run's `ran_at` timestamp and pass it as the `since` parameter. After a successful run, insert a new log row.

```
GET /events?since=2025-11-01T09:00:00Z&limit=500
```

On the very first run (no previous log), omit `since` entirely to get a full backfill, or pass a sensible default (e.g., 90 days ago) to avoid importing historical events that are already past.

**Pagination:** The Data Thistle API uses response headers for pagination rather than a `page` parameter in the URL. After each request, check the `X-Next` header — if it contains a URL, follow it to retrieve the next page. Continue until `X-Next` is absent or empty. This means a single ingestion run may make multiple HTTP requests, and the importer needs to loop rather than making a single call. The `limit` parameter controls how many events are returned per page.

**Rate limiting:** The API also returns `X-RateLimit-Limit` (monthly cap), `X-RateLimit-Remaining` (requests left this month), and `X-RateLimit-Reset` (Unix timestamp when the window resets). The importer should read `X-RateLimit-Remaining` after each page request and abort pagination early — logging a warning — if the remaining count drops below a safe threshold (e.g., 10). This prevents a single large ingestion run from exhausting the monthly allowance.

**Changes required in code:**
- Create `ingestion_logs` migration and `IngestionLog` model
- Update `DataThistleImporter::fetch()` to: accept a `since` option; loop through pages using the `X-Next` response header; respect rate limit headers and bail early if nearly exhausted; return all collected events across all pages
- Update `EventIngestionService::import()` to look up the last successful run timestamp per provider and pass it as `since`
- After each provider completes, write the log record regardless of outcome (so you can detect consecutive failures)

### 1.3 Ticketmaster — Switch to the Discovery Feed

**Current state:** `TicketmasterImporter` calls the Discovery API (`/events.json`) with `countryCode: GB` and `size: min(limit, 200)`. The Discovery API caps responses at 200 events per request, and pagination would be required to get more — which isn't currently implemented. In practice this means you're only ever getting 200 Ticketmaster events per run, which is a small fraction of what's available in the UK.

**What needs building:** Ticketmaster provides a **Discovery Feed** — a bulk data export of all events for a given market, delivered as a downloadable compressed file (JSONL format, one event per line) updated daily. This is the correct approach for getting complete UK event coverage without pagination or rate limit concerns.

**How the Discovery Feed works:**
- Ticketmaster generates a fresh feed file each day for each market (e.g., UK)
- The file is hosted at a stable URL (provided when you request feed access) and is typically a gzipped JSONL file
- You download the file once per day, stream through it line by line, and process each event
- The feed includes the full event payload in the same structure as the Discovery API response, so the existing field mapping in `TicketmasterImporter` can be largely reused
- Feed access requires a separate approval from Ticketmaster (it's not available on the standard API key) — this should be requested early as approval can take time

**Recommended implementation:**

Create a new `TicketmasterFeedImporter` (keeping the existing `TicketmasterImporter` as a fallback while feed access is pending). The feed importer should:

1. Download the compressed feed file to a temporary path in storage
2. Decompress and stream through it line by line using a generator to avoid loading the entire file into memory
3. Apply basic pre-filtering before persisting (e.g., skip events without a UK postcode or with a `starts_at` in the past) to reduce unnecessary DB writes
4. Reuse the existing field mapping logic from `TicketmasterImporter`
5. Delete the temporary file after processing

Because the feed file can be large (tens or hundreds of thousands of events for the whole UK), the processing step should be chunked — persist in batches of 100–200 rather than one at a time, and dispatch as a dedicated queue job separate from DataThistle ingestion.

**Config addition required:**

```php
// config/services.php
'ticketmaster' => [
    'api_key'   => env('TICKETMASTER_API_KEY'),
    'base_url'  => env('TICKETMASTER_BASE_URL', 'https://app.ticketmaster.com/discovery/v2'),
    'feed_url'  => env('TICKETMASTER_FEED_URL'),  // provided by Ticketmaster on approval
],
```

**Worth planning now:** While waiting for feed access approval, keep the existing `TicketmasterImporter` running. The `EventIngestionService` can select between the API importer and the feed importer based on whether `TICKETMASTER_FEED_URL` is configured.

### 1.4 Geographic Coverage Strategy

**What needs building:** Even with the feed approach for Ticketmaster (which gives you all UK events), DataThistle API calls can still be scoped geographically to concentrate coverage where your users are.

**Recommended approach:** Before each DataThistle ingestion run, query the distinct cities where newsletter-enabled users are located and pass them as location filters in the API request. As the user base grows into new cities, coverage expands automatically.

**Worth planning now:** Store a normalised `city` field on users (derived from their postcode via postcodes.io at onboarding time — postcodes.io returns the admin district as a reliable grouping key). This field is already available via the geocoding response but isn't currently persisted on the user record.

### 1.5 Cross-Source Deduplication

**What needs building:** The current deduplication uses `source + external_id` within a single provider. If the same event appears on both Ticketmaster and DataThistle (common for large events), two records will be created.

**Recommended approach:** Add a secondary deduplication pass that matches on `title + starts_at + postcode` (or lat/lng proximity) after the primary source-based check. If a likely match is found, prefer the record with more complete data (e.g., the one with an image, a description, or a ticket URL).

**Worth planning now:** Add a `canonical_event_id` nullable foreign key to the events table so duplicate records can be soft-linked rather than deleted, preserving the source provenance.

### 1.6 Ingestion Monitoring

**What needs building:** With the `ingestion_logs` table in place (introduced in section 1.2), the raw data for monitoring already exists. What needs building on top of it is alerting logic.

**Recommended approach:** After each provider run completes, check the last two log entries for that provider. If both show zero events fetched, or if the most recent run has `status = failed`, dispatch a notification to an admin Slack channel or email. This catches silent failures — a DataThistle API key expiring or a feed URL becoming stale — before they quietly starve the newsletter of events for days.

---

## Phase 2 — Category Normalisation

This is the highest product risk in the system. The matching engine is only as good as the link between user interests and event categories.

### 2.1 The Problem

Users pick interests during onboarding using your internal taxonomy (e.g., `music`, `food-markets`, `bjj`, `hiking`). Events come in from Ticketmaster with categories like `Music > Rock > Alternative` and from DataThistle with labels like `Live Entertainment` or `Wellness`. These don't match.

The current matcher checks whether the user's interest slug matches the event's `category` field exactly. This means most events will never match any user — the category strings from external APIs will almost never equal `bjj` or `food-markets`.

### 2.2 Recommended Approach: Category Mapping Table

Build a `category_mappings` table with three columns: `interest_id`, `source` (e.g., `ticketmaster`, `datathistle`), and `external_category` (the raw string from the API). When an event is ingested, look up its category in this table to find the corresponding internal interest slug, and store that on the event.

This lets you incrementally improve matching quality over time by adding mappings. For example:

| interest | source | external_category |
|---|---|---|
| music | ticketmaster | Music |
| music | ticketmaster | Music > Rock |
| music | datathistle | Live Music |
| food-markets | datathistle | Food & Drink |
| fitness | ticketmaster | Sports > Fitness & Wellness |

### 2.3 Keyword Fallback

For events whose category doesn't appear in the mapping table, add a keyword-based fallback: scan the event title and description for interest-related keywords. Store a `matched_interest_ids` JSON column on events (populated at ingestion time) to avoid doing this at query time.

### 2.4 Worth Planning Now

The interest slugs you choose during onboarding should be designed with mapping in mind. Broad categories (`music`, `sport`, `food`) are easier to map reliably than niche ones (`bjj`, `trail-running`). For niche interests, the keyword fallback matters more.

---

## Phase 3 — Matching Engine

The matching engine exists and works, but a few gaps need addressing before scale.

### 3.1 Matching Window

**Current state:** Events are matched if they start within the next 3 weeks. This is reasonable but may be too narrow — if an ingestion run happens Monday and the newsletter goes Thursday, events starting the following Saturday (10 days away) are included, which is fine. But you may want to extend to 4 weeks to give users more time to plan ahead.

**Worth planning now:** Make this configurable per-user in future (some people plan ahead, others are spontaneous). For MVP, 3–4 weeks is the right window.

### 3.2 Empty Newsletter Fallback

**Current state:** If a user has fewer than 3 matched events, the newsletter is sent anyway (or marked `no_matches` if zero). A sparse newsletter is a poor experience.

**Recommended approach:** Build a fallback chain:
1. First pass: match on interests + radius (current behaviour)
2. If fewer than 3 results: broaden radius by 50% and try again
3. If still fewer than 3: relax interest matching to adjacent categories
4. If still fewer than 3: suppress the newsletter for this user this week and flag them as `no_matches`

Log which fallback level was used so you can identify users who are consistently underserved (by interest or by location).

### 3.3 Scoring Improvements

**Current state:** Scoring is interest match (30) + distance (35) + recency (30) + manual boost. This works for MVP.

**Worth planning for later:**
- Weight categories differently per user (if a user has 5 interests, events matching their top-picked interest should score higher)
- Penalise events the user has already seen in a previous newsletter (stored in `newsletter_items`)
- Add a popularity signal (if many users in the same area got matched to an event, it's likely a strong event)

---

## Phase 4 — Newsletter Pipeline

### 4.1 Weekly Scheduling

**What needs building:** `newsletters:send-weekly` works as a CLI command but there's no scheduler entry to run it automatically each week.

**Recommended approach:** Schedule it to run every Thursday morning (or whichever day you choose) via `app/Console/Kernel.php`. The schedule should create one `NewsletterRun` and dispatch one `SendWeeklyNewsletterJob` per eligible user.

**Worth planning now:** The newsletter send and ingestion run should be on different days — run ingestion on Tuesday/Wednesday so the database is fresh when Thursday's send goes out.

### 4.2 Email Template

**What needs building:** `WeeklyNewsletterMail` references the view `emails.newsletters.weekly`. This view needs to be built and tested across email clients.

**Recommended approach:** Build a simple, clean email template with:
- Personalised header ("Here's what's on near {postcode} this week")
- 6–8 event cards, each with: event title, date, venue, distance from user, category badge, and a CTA button to the ticket URL
- Unsubscribe link (signed URL, already supported in routes)
- Footer with preference management link

Use inline CSS (not external stylesheets) for maximum email client compatibility. Test in Gmail, Outlook, and Apple Mail.

### 4.3 Job Failure Handling

**What needs building:** If `SendWeeklyNewsletterJob` fails for a specific user (bad email address, API error), it should retry with backoff rather than silently drop. Laravel's built-in `tries` and `backoff` properties on the job class handle this.

**Worth planning now:** Add a `failed_jobs` monitor and set up alerts if jobs fail repeatedly. A single failed send is fine; 100 silent failures is a data quality problem.

### 4.4 Rate Limiting for Sends

**Worth planning now:** If you have 1,000+ users and dispatch all jobs at once, your mail provider (Postmark) may rate-limit you. Dispatch jobs with a small delay between them or use a dedicated queue with a rate limiter in `config/queue.php`.

---

## Phase 5 — Admin Dashboard

`EventAdminController` exists but is empty. For operational purposes, you need visibility into the system.

### Minimum Viable Admin Features

**Event browser** — List, search, and filter stored events. View raw payload. Ability to manually boost or unpublish an event.

**Newsletter run viewer** — See each week's run: how many users were sent to, how many got `no_matches`, which events were included most, which users have been getting sparse results.

**Ingestion log viewer** — Last run per provider, counts, any errors.

**User overview** — How many active subscribers. Distribution by city. Distribution by interest. Users who haven't received a newsletter in 2+ weeks (likely broken match configuration).

---

## Phase 6 — Future Considerations (Plan Now, Build Later)

These don't need to be built in MVP, but the decisions you make now should leave room for them.

### Event Deduplication Page

A public-facing "browse events near you" page would let users discover beyond the newsletter. The matching and event data infrastructure already supports this — it's just a frontend page on top of what exists.

### Affiliate Link Tracking

When a user clicks a ticket URL in a newsletter, you want to know. Add a redirect route (`/events/{event}/go`) that logs the click (user, event, timestamp) and then redirects to the external ticket URL. This is the foundation for affiliate revenue measurement and for improving the matching algorithm with behavioural data.

### Semantic / AI-Assisted Matching

Once the category mapping table exists and the keyword fallback is built, you have a clean interface to slot in a more sophisticated matcher. The `EventMatcher` service is already isolated — you can swap in an AI-based scorer without touching the rest of the pipeline.

### Multi-Region API Coverage

As users in new cities join, the geographic ingestion strategy (Phase 1.2) handles this automatically. The thing to plan now is that Ticketmaster's API has separate regions (UK, US, etc.) — make sure the importer is parameterised by market so you can expand without rewriting it.

---

## Recommended Build Order

Work in this sequence to unlock value quickly and avoid wasting effort on things built on shaky foundations:

1. **`ingestion_logs` table + DataThistle `since` parameter** — The single highest-leverage ingestion change. Replaces the arbitrary `limit` cap with a proper incremental fetch that guarantees you catch every new and updated event since the last run.
2. **Request Ticketmaster Discovery Feed access** — This isn't a code task but needs to happen immediately, as Ticketmaster approval can take weeks. Submit the request while building everything else.
3. **Category mapping table + initial mappings for DataThistle and Ticketmaster** — Fixes the matching engine's biggest gap. Without this, most events will never match any user regardless of how many you ingest.
4. **Email template** (`emails.newsletters.weekly`) — Without this, the newsletter can't actually be sent and tested end-to-end.
5. **Scheduler entries** — Wire up ingestion (daily) and newsletter send (weekly) to the Laravel scheduler. At this point you have a working end-to-end pipeline.
6. **`TicketmasterFeedImporter`** — Build this once feed access is approved. Replaces the 200-event-per-run API cap with a full daily UK event dump.
7. **Empty newsletter fallback** — Protect the experience for users in sparse areas.
8. **Affiliate click tracking** — Low effort, high value. One route, one log entry.
9. **Admin dashboard (minimal)** — Ingestion log viewer, event browser, newsletter run stats. You need visibility to operate the system confidently.
10. **Geographic ingestion targeting for DataThistle** — Scope API calls to cities where users actually live.
11. **Cross-source deduplication** — Important at scale, less critical at early stage.
12. **Ingestion monitoring / alerting** — Alerts if a provider stops returning events. The `ingestion_logs` table makes this straightforward.
13. **Scoring improvements** — Once real users exist and you can measure what's working.
