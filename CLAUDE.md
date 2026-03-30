# Eventaroo — Claude Code Project Prompt

## What This Product Is

Eventaroo is a personalised events discovery newsletter. Users sign up, choose their interests and location, and receive a weekly email curated to show them things happening near them that they'll actually care about. It is not a search product — it's a passive discovery tool delivered via email.

The core loop is: **ingest events from external APIs → match events to users by interest + location → send a weekly personalised newsletter**.

---

## Tech Stack

- Laravel 13, PHP 8.4, Inertia.js v2, Pest v4
- Magic link authentication (no passwords), Laravel Fortify for 2FA
- Postmark for transactional email (Mailhog locally)
- postcodes.io for UK postcode geocoding
- Queue-based jobs for newsletter send and event ingestion
- Two external event sources: Data Thistle and Ticketmaster

---

## What Is Already Built — Do Not Rebuild

Before making any changes, understand what exists:

**Database:** `users`, `events`, `interests`, `user_interests`, `newsletter_runs`, `newsletter_items`, `login_links` — all migrated and working.

**Event ingestion:**
- `EventIngestionService` — orchestrates all providers, handles deduplication and persistence
- `DataThistleImporter` — fetches from Data Thistle API, normalises to shared format, currently passes only `limit`
- `TicketmasterImporter` — fetches from Ticketmaster Discovery API, GB market, currently capped at 200 events per request
- `FakeImporter` — hardcoded test event for local/testing environments only
- `FetchEventsJob` — queued wrapper around `EventIngestionService::import()`
- `EventImporter` contract in `app/Contracts/EventImporter.php`

**Matching engine:**
- `EventMatcher` — filters by user interest slugs, upcoming 3-week window, user's radius; scores on category match (30pts) + distance (up to 35pts) + recency (up to 30pts) + `score_manual` boost
- `DistanceCalculator` — Haversine formula in miles
- `PostcodesIoGeocoder` / `FakeGeocoder` — converts UK postcodes to lat/lng

**Newsletter pipeline:**
- `SendWeeklyNewsletterJob` — runs matcher for a user, writes `NewsletterItem` rows, sends `WeeklyNewsletterMail`, updates run status to `sent` or `no_matches`
- `SendWeeklyNewsletterCommand` (`newsletters:send-weekly`) — loops eligible users, dispatches one job each
- `WeeklyNewsletterMail` — references Blade view `emails.newsletters.weekly` (view does not yet exist)
- `NewsletterRun` / `NewsletterItem` models with full relationships

**Auth & onboarding:** Magic links, 2FA, full multi-step onboarding (interests → postcode → radius → email), preferences screen, unsubscribe via signed URL — all complete.

**CLI:** `events:fetch`, `events:list`, `events:boost`, `events:create-manual`, `matches:preview` — all working.

**Frontend:** Dashboard (newsletter history), onboarding flow, preferences page, waiting/polling page — all complete in Inertia/Vue.

---

## What Needs Building — In Priority Order

Work through these in sequence. Each item unblocks the next.

---

### 1. `ingestion_logs` Table + `IngestionLog` Model

Create a migration for `ingestion_logs` with columns: `provider` (string), `status` (string: `success` or `failed`), `fetched`, `created`, `updated`, `skipped`, `failed` (all integers, default 0), `ran_at` (datetime). Add an `IngestionLog` model with appropriate fillable and casts.

This table is used by `DataThistleImporter` to know the timestamp of the last successful run (for the `since` parameter), and later by the monitoring/alerting logic.

---

### 2. Update `DataThistleImporter` — Incremental Fetch, Pagination, Rate Limit Awareness

The Data Thistle API supports a `since` query parameter (ISO 8601 timestamp) that returns only events created or updated after that timestamp. The importer must be updated to use this.

**Specific changes:**

`DataThistleImporter::fetch()` currently makes one HTTP request and returns. It needs to:

1. Accept a `since` option (nullable ISO 8601 string) and pass it to the API when present
2. Loop through pages using header-based pagination: after each response, check the `X-Next` response header — if it contains a URL, follow it to get the next page; stop when `X-Next` is absent or empty
3. After each page request, read `X-RateLimit-Remaining` from the response headers; if it drops below 10, log a warning and stop paginating early to protect the monthly allowance
4. Collect events from all pages and return them together

`EventIngestionService::import()` needs to:

1. Before running `DataThistleImporter`, query `IngestionLog` for the most recent successful run for provider `datathistle` and extract its `ran_at` timestamp
2. Pass that timestamp as `since` in the options array to `DataThistleImporter::fetch()`
3. On first run (no log record), omit `since` — this triggers a full backfill
4. After each provider completes (whether success or failure), write a row to `ingestion_logs` with the stats and status

---

### 3. Ticketmaster — Add `TicketmasterFeedImporter`

The existing `TicketmasterImporter` uses the Discovery API and is hard-capped at 200 events per request with no pagination. This is not viable at scale.

Ticketmaster provides a **Discovery Feed**: a daily bulk export of all events for a given market (UK), delivered as a gzipped JSONL file (one JSON object per line). This is the correct approach for complete UK coverage.

**Create `TicketmasterFeedImporter`** implementing the `EventImporter` contract:

- `name()` returns `ticketmaster-feed`
- `isConfigured()` checks that `config('services.ticketmaster.feed_url')` is set
- `fetch()` should:
  1. Download the gzipped JSONL file from `config('services.ticketmaster.feed_url')` to a temporary path in `storage/app/temp/`
  2. Open and decompress it, streaming line by line (do not load the whole file into memory — use a generator or line-by-line reading)
  3. Decode each line as JSON, apply the same field mapping as `TicketmasterImporter` (reuse or extract that logic)
  4. Pre-filter lines before returning: skip events with no UK postcode, events where `starts_at` is in the past, and any line that fails JSON decoding
  5. Delete the temporary file after processing
  6. Return all normalised events as an array (or yield in chunks if memory is a concern)

**Add `feed_url` to config:**
```php
// config/services.php — ticketmaster entry
'feed_url' => env('TICKETMASTER_FEED_URL'),
```

**In `EventIngestionService`:** register `TicketmasterFeedImporter` and include it in the importer list. When `TICKETMASTER_FEED_URL` is set, prefer `TicketmasterFeedImporter` over `TicketmasterImporter` for the `ticketmaster` provider. Keep `TicketmasterImporter` as the fallback when the feed URL is not configured.

> Note: Feed access requires a separate approval from Ticketmaster. Until that's in place, `TicketmasterFeedImporter::isConfigured()` will return false and the existing `TicketmasterImporter` will continue running. Build the class now so it's ready the moment access is granted.

---

### 4. Scheduler Entries

Add scheduler entries to `routes/console.php` (Laravel 11+ style) or `app/Console/Kernel.php`:

- **Ingestion:** dispatch `FetchEventsJob` daily (Tuesday and Wednesday work well — before the Thursday newsletter send)
- **Newsletter send:** run `newsletters:send-weekly` every Thursday morning

These must be separate schedules. Never trigger ingestion from the newsletter send command.

---

### 5. Category Mapping Table + Normalised Interest Matching

**The problem:** `EventMatcher` currently checks whether the user's interest slug (e.g. `bjj`, `food-markets`) matches the event's `category` field exactly. The `category` field is set during ingestion from external API strings like `music`, `sports`, `live-entertainment`. These almost never align with the internal interest slugs, so most events match no users.

**Create `category_mappings` table** with columns: `interest_id` (FK to `interests`), `source` (string: `datathistle`, `ticketmaster`, `ticketmaster-feed`), `external_category` (string — the raw normalised slug from the API). Add a unique constraint on `[source, external_category]`. Create a `CategoryMapping` model with the `interest` relationship.

**Seed initial mappings** for both providers. Examples:

| interest slug | source | external_category |
|---|---|---|
| music | ticketmaster | music |
| music | datathistle | live-music |
| food-markets | datathistle | food-drink |
| fitness | ticketmaster | sports |
| arts | ticketmaster | arts-theatre-comedy |
| comedy | ticketmaster | arts-theatre-comedy |

**Update `DataThistleImporter` and `TicketmasterImporter`:** when normalising events, after deriving the `category` slug, look it up in `category_mappings` to resolve the matching `interest_id`. Store the resolved interest IDs in a new `matched_interest_ids` JSON column on the `events` table (add via migration). Fall back to keyword matching on `title` and `description` if no mapping is found.

**Update `EventMatcher`:** change the interest filter to use `matched_interest_ids` (a JSON column containing resolved interest IDs) rather than comparing the raw `category` slug to interest slugs directly. This is both more accurate and more efficient.

---

### 6. Email Template — `emails.newsletters.weekly`

`WeeklyNewsletterMail` sends a Blade view at `resources/views/emails/newsletters/weekly.blade.php`. This view does not exist yet.

Build a clean, inline-CSS email template. It receives: `$user`, `$matches` (collection of `{event, score, distance_miles}`), `$unsubscribeUrl`.

The template should include:
- Personalised subject line and header: "Here's what's on near {{ $user->postcode }} this week"
- A card for each event in `$matches` (up to 8): event title, formatted date, venue name, distance from user (`{n} miles away`), category, and a styled CTA button linking to `$event->url`
- Unsubscribe link using `$unsubscribeUrl`
- Footer link to `/preferences` for managing interests and location

Use inline CSS throughout — no `<link>` tags, no external stylesheets. Email clients strip them. Test the output in Mailhog locally by triggering `newsletters:send-weekly` with a seeded user.

---

### 7. Empty Newsletter Fallback in `SendWeeklyNewsletterJob`

Currently, if `EventMatcher::forUser()` returns fewer than 3 events, the newsletter is sent sparse or marked `no_matches`. A sparse email is poor UX.

Add a fallback chain to `SendWeeklyNewsletterJob`:

1. First pass: run `EventMatcher` with the user's configured interests and radius (current behaviour)
2. If fewer than 3 results: retry with `radius_miles * 1.5`
3. If still fewer than 3: suppress the send for this week; mark the run as `no_matches` and log which fallback level was reached

Store the fallback level used in the `newsletter_items` table or as a note on the `NewsletterRun` record so it's visible in the admin dashboard later.

---

### 8. Affiliate Click Tracking

Add a route `GET /events/{event}/go` that:
1. Records a `click` event (user ID if authenticated or null, event ID, timestamp, referrer) — a simple `event_clicks` table with `event_id`, `user_id` (nullable), `clicked_at`
2. Redirects to `$event->url`

Update the email template (step 6) to use `/events/{event}/go` URLs instead of direct ticket URLs. This is the foundation for measuring affiliate revenue and improving matching with real engagement data.

---

### 9. Ingestion Monitoring / Alerting

With `ingestion_logs` in place (step 1), add alerting logic to `EventIngestionService::import()` (or a dedicated listener):

After writing each provider's log record, query the last two log entries for that provider. If both have `status = failed`, or if both have `fetched = 0`, log a `critical` level log entry and optionally send a notification (a Slack webhook or admin email). The specific notification channel can be configured later — the important thing is the check exists.

---

### 10. Postcode Geocoding Resilience

**The problem:** Both `OnboardingController::store()` and `PreferenceController::update()` call `PostcodesIoGeocoder::geocode()` and write the result directly to the user record. If geocoding fails — whether because the user mistyped their postcode or because postcodes.io is temporarily unavailable — `latitude` and `longitude` are stored as `null`. `EventMatcher::forUser()` bails immediately when either coordinate is null, so the user silently never receives a newsletter. There is currently no error shown to the user and no retry mechanism.

**Fix in four parts:**

**Part 0 — Remove `FakeGeocoder` from local environments**

`AppServiceProvider` currently binds `PostcodeGeocoder` to `FakeGeocoder` in both `local` and `testing` environments. `FakeGeocoder` only knows a handful of hardcoded postcode districts and falls back to central London coordinates (`51.507351, -0.127758`) for everything else — meaning any user who signs up locally with a real postcode outside that list gets placed in London and receives entirely wrong event matches.

postcodes.io is a free public API with no authentication required, so there is no reason to avoid it in local development. Update `AppServiceProvider` to use `FakeGeocoder` only in the `testing` environment:

```php
$this->app->bind(PostcodeGeocoder::class, function () {
    if ($this->app->environment('testing')) {
        return new FakeGeocoder;
    }

    return new PostcodesIoGeocoder;
});
```

`FakeGeocoder` should be kept for automated tests where real HTTP calls are unwanted, but local development should always use the real geocoder so behaviour matches production.

**Part 1 — Client-side postcode format validation**

Add a UK postcode regex validator to the onboarding and preferences forms (Vue/Inertia frontend). The standard UK postcode pattern is:

```
/^[A-Z]{1,2}[0-9][0-9A-Z]?\s?[0-9][A-Z]{2}$/i
```

This catches the most common failure cause — typos and malformed entries — before the request reaches the server. Show an inline validation error on the postcode field. This is the highest-leverage fix and requires no backend changes.

**Part 2 — Queue a geocoding retry job when geocoding fails**

If `PostcodesIoGeocoder::geocode()` returns `null` in either controller, do not block the request. Save the postcode as entered (normalised to uppercase), leave `latitude` and `longitude` as `null`, and dispatch a `GeocodeUserJob` for that user.

Create `app/Jobs/GeocodeUserJob.php`:
- Accepts a `User $user` (or user ID)
- Calls `PostcodesIoGeocoder::geocode($user->postcode)`
- If successful, updates `latitude`, `longitude`, and `postcode` (normalised) on the user record
- If it fails again, allows Laravel's built-in retry mechanism to handle it: set `public int $tries = 5` and `public int $backoff = 300` (5 retries, 5-minute backoff)
- After all retries are exhausted, log a warning with the user ID and postcode so it's visible in the logs

This job follows the same `ShouldQueue` + `Queueable` pattern as `FetchEventsJob`.

**Part 3 — Local postcode cache**

Add a `postcodes` table with columns: `postcode` (string, primary key), `latitude` (decimal), `longitude` (decimal), `created_at`. Create a `Postcode` model.

Update `PostcodesIoGeocoder::geocode()` to check this table before hitting the API. On a successful API response, write the result to the table. This means:
- Repeat lookups (multiple users in the same area) hit the DB instead of the API
- If postcodes.io is temporarily down, recently looked-up postcodes still resolve
- `GeocodeUserJob` retries also benefit from the cache if another user with the same postcode has since been successfully geocoded

---

### 11. EventMatcher — Bounding Box Pre-filter

**The problem:** `EventMatcher::forUser()` currently fetches every upcoming event from the database into PHP memory, then filters by distance using the Haversine formula in a `map()` call. At small scale this is fine. With tens of thousands of events in the database, loading all of them into memory on every newsletter send — once per user — will become a significant bottleneck.

**The fix:** Add a bounding box pre-filter to the Eloquent query so that SQL does the rough geographic cull before any rows are hydrated into PHP. A bounding box converts the user's radius into a lat/lng rectangle, which can be applied as simple `BETWEEN` comparisons against indexed columns. The Haversine calculation that follows will still discard the corners of the box (events that are within the rectangle but outside the actual radius), but the dataset passed to PHP is dramatically smaller.

**How to calculate the bounding box:**

Given a user at `($lat, $lng)` with a radius of `$miles`:

```php
$latDelta = $miles / 69.0;
$lngDelta = $miles / (69.0 * cos(deg2rad($lat)));

$minLat = $lat - $latDelta;
$maxLat = $lat + $latDelta;
$minLng = $lng - $lngDelta;
$maxLng = $lng + $lngDelta;
```

One degree of latitude is approximately 69 miles everywhere. One degree of longitude varies by latitude, hence the `cos(deg2rad($lat))` correction. The box is intentionally a slight overestimate — the corners extend a little beyond the true radius, which is fine because Haversine handles the final trim.

**Changes required in `EventMatcher::forUser()`:**

Add four `whereBetween` clauses to the existing query, wrapping the bounding box values computed from the user's coordinates and radius:

```php
->whereBetween('latitude', [$minLat, $maxLat])
->whereBetween('longitude', [$minLng, $maxLng])
```

These go after the existing `starts_at` window filters and before `->get()`. No changes are needed to `scoreEvent()` — the Haversine check already handles precise distance filtering and radius enforcement.

**Index to add:** Ensure `latitude` and `longitude` are indexed on the `events` table for this to have any effect. Add a migration to create a composite index on `[latitude, longitude]` if one does not already exist.

The `DistanceCalculator` and `scoreEvent()` logic remain unchanged — this is purely a query optimisation that reduces how many rows reach PHP, not a change to how events are scored.

---

### 12. AI Fallback for Category Classification

**The problem:** When `resolveInterestIds()` finds no match in `category_mappings` and the keyword fallback also returns empty, the event is stored with `matched_interest_ids = null` and will never be shown to any user. Categories like `kids`, `children`, `outdoor-cinema`, or any novel category from a new API will silently vanish from the newsletter.

**The fix:** Add a third tier to the resolution chain in `resolveInterestIds()`: if both the mapping table and keyword fallback return empty, call an AI model to classify the event into the most appropriate interest. The result is written back into `category_mappings` immediately, so the same category is never sent to the AI again — the table self-populates over time and AI calls become rarer with each ingestion run.

**The resolution chain becomes:**
1. Check `category_mappings` table → return if matched
2. Keyword fallback on title + description → return if matched
3. AI classification → write result to `category_mappings`, return matched interest IDs

**Create `app/Services/Events/AiCategoryClassifier.php`:**

- Constructor accepts an HTTP client (use Laravel's `Http` facade internally)
- Single public method: `classify(string $title, string $description, string $category, array $interests): ?int` — returns an interest ID or null if nothing fits
- Calls the Anthropic Messages API (`claude-haiku-4-5-20251001` — fast and cheap for classification tasks)
- Builds a prompt that includes:
  - The event title, description, and raw category slug
  - The full list of available interests as a numbered list of `slug: name` pairs
  - A clear instruction to return only a JSON object: `{"interest": "slug"}` or `{"interest": null}` if nothing fits
- Parses the JSON response and looks up the returned slug in the interests array to get the ID
- On any failure (API error, malformed JSON, unknown slug), returns `null` and logs a warning — never throws

**Config required:**

```php
// config/services.php
'anthropic' => [
    'api_key' => env('ANTHROPIC_API_KEY'),
    'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
],
```

**In `resolveInterestIds()`:** after the keyword fallback returns empty, call `AiCategoryClassifier::classify()`. If it returns an interest ID, write a new row to `category_mappings` (`source`, `external_category`, `interest_id`, `ai_generated = true`) before returning the result. If it returns null, return empty as before.

**Add `ai_generated` column to `category_mappings`:** add a boolean column `ai_generated` (default false) via migration so AI-generated mappings are distinguishable from hand-curated ones in the admin dashboard.

**Cost control:** the classifier is only called when both the mapping table and keyword fallback fail. As the mapping table fills from AI results, call frequency drops. Log the number of AI classifications per ingestion run in the ingestion stats so you can monitor usage.

**Degrade gracefully:** if `ANTHROPIC_API_KEY` is not set or the API call fails, `AiCategoryClassifier::classify()` returns null and `resolveInterestIds()` falls through to returning empty — identical behaviour to the current state. The AI is a bonus, not a dependency.

---

### 13. Add Arts & Culture Interest + Category Mappings

**What needs building:** Add a 13th interest, Arts & Culture, to `InterestSeeder`:

```php
['name' => 'Arts & Culture', 'slug' => 'arts-and-culture'],
```

**Add category mappings** for this interest in `CategoryMappingSeeder`:

| source | external_category |
|---|---|
| ticketmaster | arts & theatre |
| ticketmaster-feed | arts & theatre |
| datathistle | arts |
| datathistle | visual-arts |
| datathistle | exhibitions |
| datathistle | gallery |
| datathistle | culture |
| datathistle | heritage |

Note: Ticketmaster's `arts & theatre` currently maps to the `theatre` interest. Both Theatre and Arts & Culture are valid for this segment, so add a second mapping pointing to `arts-and-culture` as well — `category_mappings` supports multiple interests per external category via separate rows with the same `source + external_category` but different `interest_id` values.

Also expand the `kids` gap while in this seeder — add the following mappings to `family-days-out`:

| source | external_category |
|---|---|
| datathistle | kids |
| datathistle | children |
| datathistle | kids-activities |
| datathistle | family-fun |
| ticketmaster | family |
| ticketmaster-feed | family |

---

## Key Architecture Rules

- **Never make per-user API calls at newsletter send time.** Events must already be in the database. The send pipeline reads from `events`, not from external APIs.
- **Ingestion and newsletter send are separate scheduled jobs on separate days.** Never call `FetchEventsJob` from within the newsletter send pipeline.
- **Provider failures are isolated.** A DataThistle failure must not prevent Ticketmaster from running, and vice versa. Each provider runs and logs independently.
- **Deduplication happens at ingestion time.** `EventIngestionService::persist()` already handles `source + external_id` deduplication. Do not duplicate this logic in importers.
- **`matched_interest_ids` is populated at ingestion, not at query time.** The matcher reads a pre-resolved JSON column, not a runtime category lookup.

---

## Existing Patterns to Follow

- New importers implement `App\Contracts\EventImporter` — check that interface before creating any importer
- Jobs use `ShouldQueue` with `Queueable` trait — check `FetchEventsJob` for the pattern
- Services are injected via the constructor with property promotion — see `EventIngestionService`
- Config values always go through `config()` — never `env()` directly in service classes
- All validation lives in Form Request classes, not controllers
- Check sibling files before creating anything new — the pattern is almost certainly already established
