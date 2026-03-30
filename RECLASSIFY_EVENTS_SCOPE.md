# Event Reclassification Scope

## What This Does

Once the sub-interests migration and `ClassifyEventJob` from `SUB_INTERESTS_AND_AI_CLASSIFIER_SCOPE.md`
are deployed, all existing events need their `matched_interest_ids` cleared and rewritten using
the new AI classifier. The old values point to top-level interest IDs under the previous flat
taxonomy — they are incompatible with the new sub-interest structure.

This scope covers:
1. A migration to wipe `matched_interest_ids` across all events
2. Repurposing `EventsBackfillCategoriesCommand` and retiring `BackfillEventCategoriesJob`
3. The one-time re-classification run
4. Ongoing use (every new event dispatches `ClassifyEventJob` automatically)

---

## Deployment Order

The following must happen in sequence. Running them out of order will cause matcher failures.

```
1. php artisan migrate          # adds parent_id to interests, clears matched_interest_ids
2. php artisan db:seed --class=InterestSeeder   # inserts new parent groups + sub-interests
3. php artisan events:reclassify                # dispatches ClassifyEventJob for all events
4. (queue worker processes jobs in background)
```

Do not run `events:reclassify` before the seeder — `ClassifyEventJob` loads sub-interests from
the database, and they must exist before classification can run.

---

## Part 1 — Migration: Clear `matched_interest_ids`

Create a new migration `clear_matched_interest_ids_on_events`:

```php
public function up(): void
{
    DB::table('events')->update(['matched_interest_ids' => null]);
}

public function down(): void
{
    // Irreversible — matched_interest_ids will be repopulated by ClassifyEventJob
}
```

This runs as part of `php artisan migrate` in the deployment sequence above. After it runs,
every event has `matched_interest_ids = null` and the `EventMatcher` will return no results
until `ClassifyEventJob` has processed the backlog. This is expected — the queue worker
repopulates matches within minutes of the command running.

> **Note:** If a zero-match window is not acceptable, run `events:reclassify` on staging first
> and let the queue drain before deploying to production. Match quality will be zero during
> the drain window (typically 30–60 minutes for a 5,000-event backlog).

---

## Part 2 — Retire `BackfillEventCategoriesJob`

`BackfillEventCategoriesJob` processes batches of events using the old `resolveByKeywords()` +
`AiCategoryClassifier` approach and writes back to both `category` and `matched_interest_ids`.
This is fully superseded by `ClassifyEventJob`.

**Delete:**
- `app/Jobs/BackfillEventCategoriesJob.php`
- `app/Jobs/BackfillEventInterestsJob.php` (same generation, same approach)

`AiCategoryClassifier` can be deleted at the same time — it is replaced by `AiEventClassifier`
from the sub-interests scope.

---

## Part 3 — Repurpose `EventsBackfillCategoriesCommand`

Rename the command signature from `events:backfill-categories` to `events:reclassify` and
rewrite its body. The existing options (`--limit`, `--source`, `--chunk`) are all worth keeping.
Add a `--force` flag to re-dispatch jobs for events that already have `matched_interest_ids`
populated — needed for the initial migration run where all events have just been cleared.

```php
#[Signature('events:reclassify {--limit= : Only process this many events} {--source= : Only process events from this source} {--force : Reclassify events that already have matched_interest_ids} {--chunk=50 : Events dispatched per batch}')]
#[Description('Dispatch ClassifyEventJob for events with missing or stale matched_interest_ids')]
class EventsReclassifyCommand extends Command
{
    public function handle(): int
    {
        $limit     = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $source    = $this->option('source');
        $force     = (bool) $this->option('force');
        $chunkSize = max(1, (int) $this->option('chunk'));

        $query = Event::query()->orderBy('id')->select('id');

        if (! $force) {
            // Default: only events not yet classified
            $query->whereNull('matched_interest_ids');
        }

        if ($source !== null) {
            $query->where('source', $source);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        $total = min($query->count(), $limit ?? PHP_INT_MAX);

        if ($total === 0) {
            $this->line('No events need reclassification.');
            return self::SUCCESS;
        }

        $dispatched = 0;

        $query->pluck('id')
            ->chunk($chunkSize)
            ->each(function ($ids) use (&$dispatched): void {
                foreach ($ids as $id) {
                    ClassifyEventJob::dispatch($id);
                }
                $dispatched += $ids->count();
            });

        $this->line("Dispatched {$dispatched} ClassifyEventJob(s).");

        return self::SUCCESS;
    }
}
```

> **Note for Claude Code:** `ClassifyEventJob` is defined in `SUB_INTERESTS_AND_AI_CLASSIFIER_SCOPE.md`.
> Import it at the top of this command. Also keep `EventsBackfillCategoriesCommand.php` as the
> filename (renamed internally) or create a new file — either is fine as long as the old command
> class is fully removed.

---

## Part 4 — `ClassifyEventJob` Skip Guard

`ClassifyEventJob` already has a skip guard for events with `matched_interest_ids` already
populated. For the re-classification run this guard must be bypassed — since the migration
clears all values first, the guard is automatically satisfied and no change to the job is needed.

For ongoing use after the migration: any newly ingested event dispatches `ClassifyEventJob`
with `matched_interest_ids = null`, so the guard passes naturally.

---

## Part 5 — `EventIngestionService` Dispatch

After the backfill completes, new events entering via `persist()` should automatically dispatch
`ClassifyEventJob`. Add the dispatch call to `persist()` when a new event is created:

```php
// In EventIngestionService::persist(), after the event is created (not updated)
if ($created) {
    ClassifyEventJob::dispatch($event->id);
}
```

This ensures the pipeline is self-sustaining — no manual backfill needed for events ingested
after the migration.

---

## Cost Estimate for the Re-classification Run

| Metric | Value |
|---|---|
| Estimated event count | ~5,000–7,000 |
| Haiku cost per event | ~£0.0001 |
| URL fetch per event | ~500ms, best-effort |
| Total AI cost | ~£0.50–£0.70 |
| Queue drain time (50 workers) | ~30–60 minutes |
| Queue drain time (10 workers) | ~2–3 hours |

Run during off-peak hours. The newsletter send on Thursdays is the only time queue load matters —
avoid scheduling the reclassify command within 2 hours of the newsletter dispatch.

---

## Useful Commands After Deployment

```bash
# Full reclassification of all events (initial migration run)
php artisan events:reclassify

# Check progress — count events still awaiting classification
# (run directly against DB or via tinker)
Event::whereNull('matched_interest_ids')->count();

# Re-run for a specific source only
php artisan events:reclassify --source=ticketmaster

# Re-run a small batch for testing before the full run
php artisan events:reclassify --limit=50

# Force reclassify already-classified events (e.g. after taxonomy changes)
php artisan events:reclassify --force
```

---

## What Gets Retired by This Scope

| File | Action |
|---|---|
| `app/Jobs/BackfillEventCategoriesJob.php` | Delete |
| `app/Jobs/BackfillEventInterestsJob.php` | Delete |
| `app/Console/Commands/EventsBackfillCategoriesCommand.php` | Replace with `EventsReclassifyCommand` |
| `app/Console/Commands/EventsBackfillInterestsCommand.php` | Delete |
| `app/Services/Events/AiCategoryClassifier.php` | Delete (replaced by `AiEventClassifier`) |
