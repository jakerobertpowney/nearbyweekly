# Scope: Seasonal Highlights in the Weekly Newsletter

## Overview

Add a dedicated seasonal layer to the newsletter pipeline that injects 1–2 curated events tied to major UK occasions — Easter, Halloween, Christmas, etc. — regardless of a user's chosen interests. The feature is built on a `tags` JSON column added to the `events` table, populated by `AiEventClassifier` at ingestion time. A separate `GenerateEventTagsJob` allows the existing events backlog to be tagged without re-running full classification.

---

## Why Tags, Not Interests

The `matched_interest_ids` column encodes what a user *selects* as their standing interests. Seasonal relevance cuts across all interests — an Easter egg hunt is relevant to a family-days-out subscriber and to a food-drink subscriber alike. Encoding seasonality through the interest system would pollute it, and making users subscribe to a "Seasonal Events" interest defeats the point: these picks should appear automatically during the window, for every user.

Tags are freeform, plural, and additive. A Christmas market can hold `["christmas", "festive", "food-drink"]` alongside its standard `matched_interest_ids`. Tags don't replace the interest pipeline — they run parallel to it.

---

## What Is Already in Place

- `AiEventClassifier` (`app/Services/Events/AiEventClassifier.php`) calls Claude Haiku for every new event via `ClassifyEventJob`, returning `interests` (slugs) and `popularity` (int). The prompt sends title, description, venue, category, and scraped URL content.
- `ClassifyEventJob` is dispatched from `EventIngestionService::persist()` for every newly created event, with a rate limiter on the `anthropic-classification` key (40 req/min).
- `BackfillPopularityScoreJob` / `EventsBackfillPopularityCommand` establish the batch backfill pattern to follow.
- `NewsletterCurator` is the single orchestration point for newsletter assembly. It already accepts a `newsletter_context` block that travels through to `WeeklyNewsletterMail`.

---

## Changes Required

### 1. Migration — `tags` column on `events`

Add a nullable JSON column `tags` to the `events` table with a default of `null`.

```php
$table->json('tags')->nullable()->after('matched_interest_ids');
```

Add `tags` to `Event::$fillable` and cast it as `array` in `Event::casts()`.

No index is needed on a JSON column at this scale. The `whereJsonContains` queries used by `SeasonalSlotFiller` will do a full scan against the bounding-box pre-filtered result set, which is already small.

---

### 2. `AiEventClassifier` — extend to return tags

**Add `tagEvent(Event $event): array` method.**

This is a lighter, focused prompt used by the backfill job. It does not re-classify interests or re-check the URL — it only asks for tags. The prompt sends:

- Event title
- Description (truncated to 300 chars)
- Raw category slug
- Venue and city

And returns `{"tags": ["halloween", "outdoor", "family-friendly"]}` or `{"tags": []}`.

Tag vocabulary guidance to include in the prompt (to keep tags consistent and query-able):

```
Seasonal: easter, halloween, bonfire-night, christmas, new-year, valentines, bank-holiday, summer
Descriptive: outdoor, indoor, family-friendly, free-entry, dog-friendly, live-music, food-drink, arts, fitness
```

The model should only return tags from the vocabulary or close equivalents — not freeform prose tags. Constrain the output to 0–5 tags.

**Extend `classify()` to also return tags (for new events going forward).**

Extend the existing `buildPrompt()` to add a third key to the requested JSON response:

```
"tags": array of 0–5 lowercase slug tags (seasonal and/or descriptive)
```

Update `parseResponse()` to extract `tags` from the decoded JSON and return it alongside `interest_ids` and `popularity_score`.

**Return type of `classify()` becomes:**
```php
array{interest_ids: list<int>, url_status: string, popularity_score: float|null, tags: list<string>}
```

---

### 3. `ClassifyEventJob` — write tags

After the existing writes to `matched_interest_ids`, `popularity_score`, and `url_status`, also write the `tags` result from `AiEventClassifier::classify()` to the event record.

```php
if (! empty($result['tags'])) {
    $event->tags = $result['tags'];
}
```

No change is needed to the rate limiter, retry config, or queue.

---

### 4. `GenerateEventTagsJob` — dedicated tag generation job

A new job at `app/Jobs/GenerateEventTagsJob.php` that *only* generates tags for events that have `tags = null`. It does not touch `matched_interest_ids` or `popularity_score`.

**Pattern:** follows `BackfillPopularityScoreJob` exactly — accepts `array $eventIds`, iterates, calls a single classifier method per event.

```php
class GenerateEventTagsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(public readonly array $eventIds) {}

    public function handle(AiEventClassifier $classifier): void
    {
        $events = Event::query()
            ->whereIn('id', $this->eventIds)
            ->whereNull('tags')
            ->where(fn ($q) => $q
                ->where('url_status', '!=', 'dead')
                ->orWhereNull('url_status')
            )
            ->get();

        foreach ($events as $event) {
            $tags = $classifier->tagEvent($event);

            $event->tags = $tags; // write even if empty array — marks as processed
            $event->save();

            Log::debug('GenerateEventTagsJob: tagged', [
                'event_id' => $event->id,
                'tags'     => $tags,
            ]);
        }
    }
}
```

**Rate limiter:** use a separate key `anthropic-tagging` (40 req/min) so backfill jobs don't consume the `anthropic-classification` budget used by `ClassifyEventJob` for live ingestion. Wrap the inner loop with `RateLimiter::attempt()` and `$this->release(5)` on throttle, mirroring `ClassifyEventJob`.

**Queue:** dispatch on the `classification` queue so it runs with the same worker pool.

---

### 5. `EventsBackfillTagsCommand` — artisan command

New command at `app/Console/Commands/EventsBackfillTagsCommand.php`:

- Signature: `events:backfill-tags {--batch=50 : Events per job} {--limit= : Max events to process}`
- Queries all events where `tags IS NULL` and `url_status != 'dead'`, ordered by `starts_at DESC` (upcoming first — most useful to tag now)
- Chunks into batches of `--batch` size and dispatches `GenerateEventTagsJob` for each
- Outputs a summary: total events queued, batches dispatched

This is the mechanism to backfill the existing events table. Run once manually:

```bash
php artisan events:backfill-tags
```

For large tables, use `--limit` to process in controlled waves.

---

### 6. `SeasonalCalendar` service

New service at `app/Services/Events/SeasonalCalendar.php`. No database dependency — seasons are code-defined for v1. The service exposes one method:

```php
public function activeSeasons(Carbon $date): array
```

Returns an array of active season objects. Each object is a plain associative array:

```php
[
    'slug'  => 'easter',
    'label' => 'Easter picks near you',
    'emoji' => '🐣',
    'tags'  => ['easter', 'easter-egg-hunt'],
]
```

**UK seasons to define:**

| Slug | Label | Emoji | Tags | Window logic |
|---|---|---|---|---|
| `valentines` | Valentine's picks near you | 💝 | `valentines`, `romantic` | Feb 11–14 |
| `easter` | Easter picks near you | 🐣 | `easter`, `easter-egg-hunt`, `spring-fair` | Good Friday → Easter Monday (computed via `easter_date()`) |
| `may-bank-holiday` | Bank holiday weekend picks | 🌸 | `bank-holiday`, `spring` | First Monday of May ± 3 days |
| `halloween` | Halloween near you | 🎃 | `halloween`, `spooky`, `horror` | Oct 25–31 |
| `bonfire-night` | Bonfire Night near you | 🎆 | `bonfire-night`, `fireworks` | Nov 3–7 |
| `christmas` | Christmas events near you | 🎄 | `christmas`, `christmas-market`, `festive` | Dec 1 – Dec 28 |
| `new-year` | New Year events near you | 🥂 | `new-year`, `nye` | Dec 28 – Jan 3 |
| `summer-bank-holiday` | Bank holiday weekend picks | ☀️ | `bank-holiday`, `summer` | Last Monday of August ± 3 days |

Easter window calculation:
```php
$easterTimestamp = easter_date($date->year);
$easterSunday    = Carbon::createFromTimestamp($easterTimestamp);
$goodFriday      = $easterSunday->copy()->subDays(2);
$easterMonday    = $easterSunday->copy()->addDay();
```

`activeSeasons()` checks whether `$date` falls within each window and returns all matching seasons. Multiple seasons can be active simultaneously (e.g. Christmas and New Year overlap). Callers combine the tags from all active seasons into one query.

---

### 7. `SeasonalSlotFiller` service

New service at `app/Services/Events/SeasonalSlotFiller.php`. Injected into `NewsletterCurator`.

```php
public function pick(
    User $user,
    Collection $excludeEventIds,
    float $radius,
): Collection
```

Returns a Collection of 0–2 match arrays with `match_type = 'seasonal'` and an additional `season_label` key. Returns an empty Collection immediately if no seasons are currently active.

**Internal logic:**

1. Call `SeasonalCalendar::activeSeasons(now())`. If empty, return `collect()`.
2. Collect all tags from all active seasons into a flat, deduplicated array.
3. Compute the bounding box from `$user->latitude`, `$user->longitude`, and `$radius` (same formula as `EventMatcher`).
4. Query `events`:
   ```php
   Event::query()
       ->where('starts_at', '>=', now())
       ->where('starts_at', '<=', now()->addWeeks(3))
       ->where('url_status', '!=', 'dead')
       ->whereBetween('latitude', [$minLat, $maxLat])
       ->whereBetween('longitude', [$minLng, $maxLng])
       ->whereNotIn('id', $excludeEventIds)
       ->where(function ($q) use ($seasonalTags) {
           foreach ($seasonalTags as $tag) {
               $q->orWhereJsonContains('tags', $tag);
           }
       })
       ->orderBy('starts_at')
       ->limit(20)
       ->get()
   ```
5. For each candidate, run the Haversine distance check against `$radius` (reuse `DistanceCalculator`). Discard events outside the true radius.
6. Score each surviving event using a simplified formula: `distance_score + recency_score` (same sub-scores as `EventMatcher::scoreEvent()`, but without `category_score` since interest relevance is irrelevant here). Add `popularity_score` weighting.
7. Sort by score descending. Take up to 2.
8. Map to the standard match array shape, adding `match_type = 'seasonal'` and `season_label` (derived from the first active season whose tags overlap with the event's tags).

`SeasonalSlotFiller` does **not** use `EventMatcher::forInterestIds()` — it bypasses the interest filter entirely. That's the point.

---

### 8. `NewsletterCurator` — integrate seasonal picks

Inject `SeasonalSlotFiller` via constructor property promotion alongside the existing `EventMatcher`.

At the bottom of `curate()`, after the bucket trimming loop and before building the return array, call:

```php
$allSelectedIds = collect($result['buckets'])
    ->flatten(1)
    ->pluck('event.id');

$seasonalPicks = $this->seasonalSlotFiller->pick(
    $user,
    $allSelectedIds,
    (float) $attempt['radius'],
);
```

Attach seasonal picks to the return array and newsletter context:

```php
return [
    'buckets'          => $result['buckets'],
    'seasonal_picks'   => $seasonalPicks->all(),
    'fallback_level'   => ...,
    'bucket_summary'   => ...,
    'newsletter_context' => array_merge($context, [
        'seasonal_label' => $seasonalPicks->isNotEmpty()
            ? $seasonalPicks->first()['season_label']
            : null,
    ]),
];
```

`SendWeeklyNewsletterJob` passes `seasonal_picks` and `newsletter_context` through to `WeeklyNewsletterMail` unchanged — it already passes the full context block, so no changes are needed in the job itself.

**Also write seasonal picks to `newsletter_items`** in `SendWeeklyNewsletterJob`, using the same `firstOrCreate` loop that handles the main buckets. The `ranking_score` for seasonal picks can be stored as `null` or `0` to distinguish them in any future admin view.

---

### 9. Email template — seasonal section

In `resources/views/emails/newsletters/weekly.blade.php`, add a conditional block after the main buckets section:

```blade
@if(!empty($newsletterContext['seasonal_label']) && !empty($seasonalPicks))
    <!-- Seasonal section -->
    <tr>
        <td style="padding: 24px 0 8px;">
            <p style="font-size: 11px; font-weight: 700; letter-spacing: 0.08em;
                       color: #666; text-transform: uppercase; margin: 0;">
                {{ $newsletterContext['seasonal_label'] }}
            </p>
        </td>
    </tr>
    @foreach($seasonalPicks as $pick)
        {{-- same event card partial as main buckets --}}
    @endforeach
@endif
```

The section only renders when `seasonal_label` is non-null and there are picks. During non-seasonal periods, nothing changes in the email.

---

## What Is Not Changing

- `EventMatcher` — no changes. The interest-based matching pipeline is untouched.
- `category_mappings` table — no changes. It exists but is not in the active pipeline; this feature doesn't depend on it.
- `ClassifyEventJob` rate limiter budget — `GenerateEventTagsJob` uses its own `anthropic-tagging` key so backfill jobs don't compete with live ingestion classification.
- Newsletter bucket logic, scoring, diversity allocation — untouched. Seasonal picks are purely additive.
- User preferences — no opt-in or opt-out for seasonal picks in v1. These are editorially always-on. A `seasonal_picks_enabled` boolean on `users` is a trivial addition later if needed.

---

## Delivery Order

1. Migration (`tags` column on `events`)
2. `AiEventClassifier` — add `tagEvent()` method and extend `classify()` return shape
3. `ClassifyEventJob` — write `tags` from classify result
4. `GenerateEventTagsJob` + `EventsBackfillTagsCommand`
5. `SeasonalCalendar` service
6. `SeasonalSlotFiller` service
7. `NewsletterCurator` integration
8. Template seasonal section
9. Run `events:backfill-tags` to populate existing events

Steps 1–4 are the foundation and can ship independently. Steps 5–9 depend on tags being populated in the events table to be testable end-to-end.

---

## Testing the End-to-End Flow

Once tags are backfilled:

1. Manually update a handful of local events with `tags = ["easter"]` (or whichever season is approaching).
2. Temporarily adjust `SeasonalCalendar` window dates to include today.
3. Run `php artisan matches:preview {user}` — seasonal picks won't appear here (it uses `EventMatcher` only), so inspect `NewsletterCurator::curate()` via a test or `tinker`.
4. Trigger `php artisan newsletters:send-weekly` against a seeded user and check the Mailhog preview for the seasonal section.
