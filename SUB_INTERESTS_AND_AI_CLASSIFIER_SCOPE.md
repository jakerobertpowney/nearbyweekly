# Sub-Interests, Grouped Onboarding & AI Event Classifier Scope

## Overview

This scope covers two changes that are tightly coupled and should be built together:

1. **Sub-interests** — the flat `interests` table grows a `parent_id` column, creating a
   two-level hierarchy. The 13 current interests are reorganised as children under 7 parent
   groups. The onboarding interest picker becomes a grouped accordion matching the Meetup UX
   pattern: select the group to get everything, or expand to cherry-pick sub-interests.

2. **AI event classifier** — a new `ClassifyEventJob` replaces the category_mappings lookup as
   the primary mechanism for populating `matched_interest_ids` on events. It reads the event's
   title, description, raw category, and optionally fetches the event URL for richer content,
   then asks Claude to classify the event against the full sub-interest list. This runs as a
   background job after ingestion — fast to ingest, precise classification follows shortly after.

### Does this replace `category_mappings`?

**Yes, largely.** The `category_mappings` table was a workaround for the coarseness of API
categories — it translated strings like `"music"` or `"arts-theatre-comedy"` into interest IDs.
With AI reading the full event content and classifying directly to sub-interests, the lookup table
adds no precision and is no longer the primary mechanism.

What replaces it: the presence of data in `matched_interest_ids` on the event row. A populated
`matched_interest_ids` means the event has been classified. An empty one means the
`ClassifyEventJob` hasn't run yet (or the event had insufficient data to classify).

**Migration plan for `category_mappings`:** stop writing to the table once the new classifier is
deployed. Keep it in place for a few weeks as existing events are backfilled via
`ClassifyEventJob`. Drop the table once the backfill is complete and `matched_interest_ids` is
populated across all events. The `AiCategoryClassifier` service (step 12 in CLAUDE.md) can be
retired alongside it.

---

## Part 1 — Data Model: `parent_id` on `interests`

### Migration

```php
// add_parent_id_to_interests_table
Schema::table('interests', function (Blueprint $table) {
    $table->foreignId('parent_id')
          ->nullable()
          ->constrained('interests')
          ->nullOnDelete()
          ->after('id');
});
```

Parent interests have `parent_id = null`. Sub-interests have `parent_id = <parent's id>`.
No other tables change — `user_interests`, `matched_interest_ids`, and `EventMatcher` all
reference interest IDs, which remain stable.

### `Interest` Model

Add the relationship and a helper:

```php
public function parent(): BelongsTo
{
    return $this->belongsTo(Interest::class, 'parent_id');
}

public function children(): HasMany
{
    return $this->hasMany(Interest::class, 'parent_id');
}

public function isParent(): bool
{
    return $this->parent_id === null;
}
```

### Revised `InterestSeeder`

The 13 existing interests become sub-interests, reorganised under 7 parent groups. Parents are
inserted first (to get their IDs), then children reference them.

**The 7 parent groups:**

| Parent name | Slug | Emoji |
|---|---|---|
| Music | `music` | 🎵 |
| Arts & Entertainment | `arts-and-entertainment` | 🎭 |
| Food & Drink | `food-and-drink` | 🍽️ |
| Health & Fitness | `health-and-fitness` | 💪 |
| Outdoors & Nature | `outdoors-and-nature` | 🌿 |
| Family | `family` | 👨‍👩‍👧 |
| Tech & Professional | `tech-and-professional` | 💼 |

**Sub-interests by group:**

```php
'music' => [
    ['name' => 'Live Gigs',          'slug' => 'live-gigs'],
    ['name' => 'Concerts',           'slug' => 'concerts'],        // was top-level
    ['name' => 'Festivals',          'slug' => 'festivals'],       // was top-level
    ['name' => 'Electronic & Dance', 'slug' => 'electronic-dance'],
    ['name' => 'Indie & Rock',       'slug' => 'indie-rock'],
    ['name' => 'Hip-hop & R&B',      'slug' => 'hip-hop-rnb'],
    ['name' => 'Folk & Acoustic',    'slug' => 'folk-acoustic'],
    ['name' => 'Soul & Funk',        'slug' => 'soul-funk'],
    ['name' => 'Classical & Opera',  'slug' => 'classical-opera'],
    ['name' => 'Jazz & Blues',       'slug' => 'jazz-blues'],
],

'arts-and-entertainment' => [
    ['name' => 'Theatre',            'slug' => 'theatre'],         // was top-level
    ['name' => 'Arts & Culture',     'slug' => 'arts-and-culture'], // was top-level
    ['name' => 'Comedy',             'slug' => 'comedy'],          // was top-level
    ['name' => 'Film & Cinema',      'slug' => 'film-cinema'],
    ['name' => 'Spoken Word',        'slug' => 'spoken-word'],
],

'food-and-drink' => [
    ['name' => 'Food Events',        'slug' => 'food-events'],
    ['name' => 'Markets',            'slug' => 'markets'],         // was top-level
    ['name' => 'Wine & Cocktails',   'slug' => 'wine-cocktails'],
    ['name' => 'Beer & Brewing',     'slug' => 'beer-brewing'],
    ['name' => 'Cooking Classes',    'slug' => 'cooking-classes'],
],

'health-and-fitness' => [
    ['name' => 'Sports',             'slug' => 'sports'],          // was top-level
    ['name' => 'Wellness',           'slug' => 'wellness'],        // was top-level
    ['name' => 'Yoga & Pilates',     'slug' => 'yoga-pilates'],
    ['name' => 'Running & Cycling',  'slug' => 'running-cycling'],
    ['name' => 'Martial Arts',       'slug' => 'martial-arts'],
],

'outdoors-and-nature' => [
    ['name' => 'Hiking',             'slug' => 'hiking'],          // was top-level
    ['name' => 'Farming & Rural',    'slug' => 'farming-and-rural'], // was top-level
    ['name' => 'Outdoor Adventures', 'slug' => 'outdoor-adventures'],
    ['name' => 'Wildlife & Nature',  'slug' => 'wildlife-nature'],
],

'family' => [
    ['name' => 'Family Days Out',    'slug' => 'family-days-out'], // was top-level
    ['name' => 'Kids Activities',    'slug' => 'kids-activities'],
    ['name' => 'Educational Days',   'slug' => 'educational-days'],
],

'tech-and-professional' => [
    ['name' => 'Tech',               'slug' => 'tech'],            // was top-level
    ['name' => 'Business & Networking', 'slug' => 'business-networking'],
    ['name' => 'Conferences',        'slug' => 'conferences'],
    ['name' => 'Workshops',          'slug' => 'workshops'],
    ['name' => 'Startup & Entrepreneurship', 'slug' => 'startup'],
],
```

Use `updateOrCreate` on `slug` so re-running the seeder is safe. The 13 slugs that existed
before (e.g. `concerts`, `theatre`, `wellness`) keep the same slug — their IDs may change but
`updateOrCreate` handles that. **Existing `user_interests` rows must be migrated** — see below.

### Existing User Migration

Users who selected top-level interests (e.g., `sports`, `concerts`) need their `user_interests`
rows updated to the new sub-interest IDs after the seeder runs. Since slugs are preserved, this
is a safe lookup:

```php
// In a post-seeder migration or artisan command
// user_interests rows pointing to interests whose slugs still exist are unaffected
// (the interest ID may be the same or different depending on insertion order)
// Safest approach: after seeding, verify all user_interests.interest_id values
// still point to a valid interest — the updateOrCreate on slug preserves IDs
// if the row already existed, so no migration should be needed.
```

> **Note for Claude Code:** Run `InterestSeeder` against the existing database and verify that
> `user_interests` rows remain valid. If interest IDs change (they should not with `updateOrCreate`
> on an existing slug), write a data migration to remap them.

---

## Part 2 — Onboarding UI: Grouped Accordion

### What changes

`Onboarding/Start.vue` step 1 currently renders a flat emoji grid of all 13 interests. It
becomes a grouped accordion — each parent group is a collapsible row, expanding to show its
sub-interests as a pill grid.

### Behaviour

- **Collapsed state:** parent row shows group name, emoji, count of selected sub-interests
  within it, and a chevron
- **Expanded state:** sub-interest pills appear in a wrapping grid below the parent row
- **Selecting a parent row** (clicking the row itself, not just the chevron): toggles all
  sub-interests in the group on or off. If any are selected, clicking the parent clears all.
  If none are selected, clicking the parent selects all.
- **Selecting individual sub-interests:** standard toggle, adds/removes from selected set
- **Parent row visual state:**
  - All children selected → orange background, filled check icon
  - Some children selected → orange border, partial indicator
  - None selected → default slate border
- **Minimum selection:** 1 sub-interest required to proceed (reduce from current "at least 1
  interest" — users can be as specific or broad as they want)
- **Selection counter** at bottom: `{n} interest{s} selected` (existing pattern, already in
  the step)

### Props from backend

The `Onboarding/Start.vue` page receives `interests` from the controller. This prop needs to
change shape from a flat array to a grouped structure:

```ts
// Before
interests: Array<{ id: number; name: string; slug: string }>

// After
interests: Array<{
    id: number
    name: string
    slug: string
    emoji: string
    children: Array<{ id: number; name: string; slug: string }>
}>
```

Update `OnboardingController` (or whatever controller passes interests to the page) to eager
load `children` and group by parent, returning only parent interests with their children nested.
Filter to `whereNull('parent_id')` for the top-level query.

### Component structure

Extract the interest picker to a new component `InterestGroupPicker.vue` so it can be reused
on the Preferences page (which also has an interest picker that will need the same treatment):

```
resources/js/components/InterestGroupPicker.vue
```

Props: `groups` (the nested array above), `modelValue` (array of selected sub-interest IDs).
Emits: `update:modelValue`.

### Visual spec

Each parent row:
```html
<div class="border-b border-slate-100 last:border-b-0">
    <!-- Parent row — clickable to expand/collapse + select-all on direct click -->
    <button class="flex w-full items-center gap-3 px-1 py-3.5" @click="toggleGroup">
        <span class="text-xl">{{ group.emoji }}</span>
        <span class="flex-1 text-left font-medium text-slate-800">{{ group.name }}</span>
        <!-- selected count badge, shows only when > 0 selected -->
        <span v-if="selectedCount > 0" class="rounded-full bg-orange-100 px-2 py-0.5 text-xs font-semibold text-orange-600">
            {{ selectedCount }}
        </span>
        <ChevronDown class="h-4 w-4 text-slate-400 transition-transform" :class="{ 'rotate-180': isOpen }" />
    </button>

    <!-- Sub-interests — shown when expanded -->
    <div v-if="isOpen" class="flex flex-wrap gap-2 pb-4 pt-1">
        <button
            v-for="sub in group.children"
            :key="sub.id"
            @click="toggleSubInterest(sub.id)"
            class="rounded-full border px-3 py-1.5 text-sm transition-colors"
            :class="isSelected(sub.id)
                ? 'border-orange-400 bg-orange-50 font-medium text-orange-700'
                : 'border-slate-200 text-slate-600 hover:border-slate-300'"
        >
            {{ sub.name }}
        </button>
    </div>
</div>
```

> **Note for Claude Code:** Use `v-show` rather than `v-if` for the sub-interest list if
> animation is desired later — `v-show` retains DOM state for CSS transitions. Match the
> existing step-1 animation style.

---

## Part 3 — `ClassifyEventJob` (AI + URL Fetch)

### Architecture

This job is the replacement for the `category_mappings` lookup. It runs **after** initial
ingestion, as a queued background job — one job per event. The ingestion pipeline stays fast;
classification happens asynchronously.

**Trigger points:**
1. `EventIngestionService::persist()` — after persisting a new event, dispatch
   `ClassifyEventJob::dispatch($event->id)` if `matched_interest_ids` is null
2. The existing `events:backfill-categories` command — dispatch for all events where
   `matched_interest_ids` is null (covers events already in the DB before this feature lands)
3. `BillettoWebhookController::handleUpsert()` — dispatch after persisting a webhook event

**Skip condition:** if `matched_interest_ids` is already populated on the event, do not
dispatch a new job (the event has already been classified). This prevents redundant re-classification
on every daily re-ingestion of the same event.

### `ClassifyEventJob`

Create `app/Jobs/ClassifyEventJob.php`:

```php
<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\Interest;
use App\Services\Events\AiEventClassifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ClassifyEventJob implements ShouldQueue
{
    use Queueable;

    public int $tries   = 3;
    public int $backoff = 60;

    public function __construct(public readonly int $eventId) {}

    public function handle(AiEventClassifier $classifier): void
    {
        $event = Event::find($this->eventId);

        if (! $event || ! empty($event->matched_interest_ids)) {
            return; // already classified or deleted
        }

        $subInterests = Interest::whereNotNull('parent_id')
            ->select('id', 'name', 'slug', 'parent_id')
            ->with('parent:id,name')
            ->get();

        $interestIds = $classifier->classify($event, $subInterests);

        if (! empty($interestIds)) {
            $event->update(['matched_interest_ids' => $interestIds]);
        } else {
            Log::info('ClassifyEventJob: no sub-interest match', [
                'event_id' => $event->id,
                'title'    => $event->title,
            ]);
        }
    }
}
```

### `AiEventClassifier` Service

Create `app/Services/Events/AiEventClassifier.php`. This is distinct from the existing
`AiCategoryClassifier` — it classifies a full `Event` model against sub-interests, not a
raw category string against top-level interests.

```php
<?php

namespace App\Services\Events;

use App\Models\Event;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiEventClassifier
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const MODEL   = 'claude-haiku-4-5-20251001';
    private const TIMEOUT = 20;

    public function classify(Event $event, Collection $subInterests): array
    {
        if (! filled(config('services.anthropic.api_key'))) {
            return [];
        }

        $urlContent    = $this->fetchUrlContent($event->url);
        $interestList  = $this->buildInterestList($subInterests);
        $prompt        = $this->buildPrompt($event, $urlContent, $interestList);

        try {
            $response = Http::withHeaders([
                'x-api-key'         => config('services.anthropic.api_key'),
                'anthropic-version' => '2023-06-01',
            ])->timeout(self::TIMEOUT)->post(self::API_URL, [
                'model'      => self::MODEL,
                'max_tokens' => 256,
                'messages'   => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

            if (! $response->successful()) {
                Log::warning('AiEventClassifier: API error', ['status' => $response->status()]);
                return [];
            }

            $text = $response->json('content.0.text', '');
            return $this->parseResponse($text, $subInterests);

        } catch (ConnectionException $e) {
            Log::warning('AiEventClassifier: connection failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function fetchUrlContent(string $url): string
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return '';
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders(['User-Agent' => 'NearbyWeekly/1.0 (events discovery newsletter)'])
                ->get($url);

            if ($response->successful()) {
                // Strip tags, collapse whitespace, truncate to ~1500 chars
                $text = strip_tags($response->body());
                $text = preg_replace('/\s+/', ' ', $text);
                return mb_substr(trim($text), 0, 1500);
            }
        } catch (\Throwable) {
            // URL fetch is best-effort — silently fall back to no URL content
        }

        return '';
    }

    private function buildInterestList(Collection $subInterests): string
    {
        return $subInterests->map(fn ($i) =>
            sprintf('- %s (slug: %s, group: %s)', $i->name, $i->slug, $i->parent->name ?? '')
        )->implode("\n");
    }

    private function buildPrompt(Event $event, string $urlContent, string $interestList): string
    {
        $parts = [
            "You are classifying a UK event for a personalised newsletter.",
            "",
            "Event details:",
            "Title: {$event->title}",
            "Raw category from source API: " . ($event->category ?? 'none'),
            "Description: " . mb_substr($event->description ?? '', 0, 500),
        ];

        if ($urlContent) {
            $parts[] = "Additional content from event page: {$urlContent}";
        }

        $parts[] = "";
        $parts[] = "Available sub-interests (pick 1 to 3 that best fit):";
        $parts[] = $interestList;
        $parts[] = "";
        $parts[] = 'Return ONLY a JSON object: {"interests": ["slug1", "slug2"]}';
        $parts[] = 'Return {"interests": []} if nothing fits.';
        $parts[] = 'Do not explain. Return only the JSON.';

        return implode("\n", $parts);
    }

    private function parseResponse(string $text, Collection $subInterests): array
    {
        // Extract JSON from response (Claude sometimes adds a brief preamble)
        preg_match('/\{.*\}/s', $text, $matches);
        if (empty($matches[0])) {
            return [];
        }

        $data   = json_decode($matches[0], true);
        $slugs  = $data['interests'] ?? [];
        $lookup = $subInterests->keyBy('slug');

        return collect($slugs)
            ->map(fn ($slug) => $lookup->get($slug)?->id)
            ->filter()
            ->values()
            ->all();
    }
}
```

### Rate limiting and cost controls

- URL fetching adds ~500ms per event — fine for background jobs, not for inline processing
- At 100 events/minute queue throughput, a 5,000-event ingestion run classifies in ~50 minutes
- Haiku cost at ~1,500 token prompts: ~£0.30–£0.50 per full ingestion run
- Add a `CLASSIFICATION_ENABLED` env flag (default true) so it can be toggled off immediately
  if costs grow unexpectedly:

```php
// In ClassifyEventJob::handle()
if (! config('app.classification_enabled', true)) {
    return;
}
```

```php
// config/app.php
'classification_enabled' => env('CLASSIFICATION_ENABLED', true),
```

---

## Part 4 — `EventMatcher` Update

The matcher currently does a direct intersection between user interest IDs and event
`matched_interest_ids`. With the two-level hierarchy, this needs one change: **if the user
selected a parent interest, expand it to all its children's IDs before matching.**

This lets users who selected "Music" (the parent) match against events classified as
`live-gigs`, `concerts`, `jazz-blues`, etc. Users who selected `jazz-blues` specifically only
match jazz events.

```php
public function forUser(User $user, int $limit = 8, ?float $radiusOverride = null): Collection
{
    $selectedIds = $user->interests()->pluck('interests.id');

    if ($selectedIds->isEmpty() || $user->latitude === null || $user->longitude === null) {
        return collect();
    }

    // Expand any parent interest IDs to include all their children
    $expandedIds = $this->expandInterestIds($selectedIds);

    // ... rest of query unchanged, use $expandedIds instead of $selectedIds
    ->get()
    ->map(fn (Event $event) => $this->scoreEvent($user, $event, $expandedIds, $radius))
    // ...
}

private function expandInterestIds(Collection $selectedIds): Collection
{
    // Load children of any selected parent interests
    $childIds = \App\Models\Interest::whereIn('parent_id', $selectedIds)
        ->pluck('id');

    return $selectedIds->merge($childIds)->unique()->values();
}
```

This means:
- Selecting a parent = match all events in that group
- Selecting sub-interests = match only those specific events
- Mixed (some parents, some sub-interests) = union of all applicable IDs

---

## Part 5 — Preferences Page

`InterestGroupPicker.vue` (built in Part 2) replaces the current flat grid on `Preferences/Edit.vue`.
The props shape and behaviour are identical. No separate implementation needed — this is the
payoff for extracting the component.

---

## Migration Strategy for Existing Events

All existing events in the database have `matched_interest_ids` values pointing to old top-level
interest IDs (e.g., interest ID 3 = "Comedy"). After the seeder runs, "Comedy" becomes a
sub-interest with the same slug — if `updateOrCreate` preserves the ID (it will if the row
exists), `matched_interest_ids` values remain valid automatically.

After deploying `ClassifyEventJob`, run:

```bash
php artisan events:backfill-categories
```

This dispatches `ClassifyEventJob` for all events where `matched_interest_ids` is null, clearing
the backlog from before this feature. Existing classified events are skipped.

---

## What Gets Retired

| Component | Status after this scope |
|---|---|
| `category_mappings` table | Stop writing to it. Drop after backfill completes. |
| `CategoryMappingSeeder` | Stop running. Archive. |
| `AiCategoryClassifier` | Superseded by `AiEventClassifier`. Remove. |
| `resolveInterestIds()` in `EventIngestionService` | Remove the 3-tier resolution chain — `matched_interest_ids` is now populated by `ClassifyEventJob`, not at ingestion time. |
| `resolveByKeywords()` | Remove alongside `resolveInterestIds()`. |

The ingestion pipeline simplifies significantly: importers normalise events, `persist()` saves
them, `ClassifyEventJob` is dispatched. No inline category resolution at all.
