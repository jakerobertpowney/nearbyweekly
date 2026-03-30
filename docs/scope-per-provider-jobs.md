# Scope: Per-Provider Ingestion Jobs

## Summary

Split event ingestion from a single monolithic `FetchEventsJob` (which runs all providers sequentially) into separate per-provider jobs dispatched independently. This gives each provider its own timeout budget, retry lifecycle, and queue slot — allowing parallel execution and preventing a slow or failing provider from blocking others.

---

## Background

`events:fetch` currently dispatches a single `FetchEventsJob` with no `$provider` argument. That job calls `EventIngestionService::import()`, which loops through all configured providers — DataThistle, Ticketmaster (or the Feed variant), and Billetto — sequentially within the same queue worker process.

The key problems with this:

- **DataThistle is a multi-page generator.** Each page can be 30–40 MB of JSON, the importer already bumps `memory_limit` to 512 MB, and a full run follows `X-Next` pagination headers across potentially dozens of HTTP requests. This can run for several minutes inside a single job, eating into any queue worker timeout that applies to all providers.
- **A single timeout kills all providers.** If the worker's `timeout` fires during DataThistle pagination, Ticketmaster and Billetto never run. The only protection currently is a `try/catch` that isolates provider *exceptions* — it does nothing for a process-level timeout.
- **Retries re-run everything.** Laravel's built-in job retry will re-run the whole combined job on failure, meaning DataThistle and Ticketmaster both restart even if only one of them failed.
- **No parallel execution.** With multiple queue workers available, all three providers still run back-to-back rather than concurrently.

`FetchEventsJob` already accepts a `?string $provider` parameter and `EventIngestionService::selectedImporters()` already filters by provider name — so the infrastructure to support this change is essentially in place.

---

## What Changes

### 1. `routes/console.php` — Scheduler

Replace the two combined `FetchEventsJob` schedule entries with six entries: one per provider, per day.

**Before:**
```php
Schedule::job(new FetchEventsJob)->weeklyOn(2, '06:00');
Schedule::job(new FetchEventsJob)->weeklyOn(3, '06:00');
```

**After:**
```php
// Tuesday
Schedule::job(new FetchEventsJob('datathistle'))->weeklyOn(2, '06:00');
Schedule::job(new FetchEventsJob('ticketmaster'))->weeklyOn(2, '06:30');
Schedule::job(new FetchEventsJob('billetto'))->weeklyOn(2, '07:00');

// Wednesday
Schedule::job(new FetchEventsJob('datathistle'))->weeklyOn(3, '06:00');
Schedule::job(new FetchEventsJob('ticketmaster'))->weeklyOn(3, '06:30');
Schedule::job(new FetchEventsJob('billetto'))->weeklyOn(3, '07:00');
```

The staggered start times on the same day are optional — if the queue has enough workers they can all be dispatched at `06:00` simultaneously. Staggering is a conservative default to avoid hitting external API rate limits concurrently from the same IP.

### 2. `FetchEventsJob` — Per-Provider Timeout

Add a `$timeout` property so DataThistle's multi-page run gets a generous ceiling without that ceiling applying to the faster providers.

```php
public int $timeout = match($this->provider) { ... }
```

Because PHP doesn't allow expressions in property initialisers, set the timeout in the constructor instead:

```php
public function __construct(
    public ?string $provider = null,
    public ?int $limit = 50,
) {
    $this->timeout = match ($provider) {
        'datathistle' => 1800,  // 30 min — multi-page pagination
        'ticketmaster-feed' => 900,  // 15 min — large gzipped file download
        default => 300,             // 5 min — standard API calls
    };
}
```

### 3. No Changes to `EventIngestionService`

The service already runs one provider when `$options['provider']` is set, and already writes independent `IngestionLog` records per provider. No changes needed there.

### 4. `events:fetch` CLI — No Changes Required

The command already supports `--provider=` as an option. Running `php artisan events:fetch` with no flag continues to run all providers (useful for local development and manual backfills). Per-provider runs remain possible via `php artisan events:fetch --provider=datathistle`.

---

## What Does Not Change

- `EventIngestionService::import()` — no changes
- `DataThistleImporter`, `TicketmasterImporter`, `TicketmasterFeedImporter`, `BillettoImporter` — no changes
- Ingestion log writes, alert checks, deduplication logic — no changes
- The `events:fetch` CLI command — no changes

---

## Testing

- Dispatch `new FetchEventsJob('datathistle')` manually and confirm only DataThistle runs (check `ingestion_logs.provider`).
- Confirm a thrown exception in one provider job does not affect the other jobs (they are now entirely separate queue entries).
- Confirm `php artisan events:fetch` (no `--provider`) still runs all providers as before — this path is unchanged.
- Confirm the scheduler entries resolve to the correct provider names by running `php artisan schedule:list`.

---

## Risks & Notes

- **`ticketmaster` vs `ticketmaster-feed` naming:** `selectedImporters()` selects the Feed importer when `TICKETMASTER_FEED_URL` is set, and it returns under the name `ticketmaster-feed`. Dispatching `new FetchEventsJob('ticketmaster')` will resolve to whichever Ticketmaster variant is active, since `selectedImporters()` picks one and the `$provider` filter matches on name. Verify this lookup still works correctly when the Feed importer is active, since its `name()` returns `ticketmaster-feed` not `ticketmaster`. If needed, add an alias or adjust the filter to match both names.
- **Queue worker timeout config:** Per-job `$timeout` only works if the queue driver respects it (Redis/database do; `sync` does not). Ensure production workers are started with `--timeout` set to at least as long as the longest per-job timeout.
- **Backfill / manual runs:** For a one-off full backfill across all providers, `php artisan events:fetch` (no flag) remains the correct tool.
