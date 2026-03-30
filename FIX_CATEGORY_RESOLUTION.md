# Fix: Category Resolution Bugs in `EventIngestionService`

## Background

Two bugs in `app/Services/Events/EventIngestionService.php` are causing events to be matched to the wrong interests. They must both be fixed together.

---

## Bug 1 — `resolveByKeywords()` uses substring matching

**File:** `app/Services/Events/EventIngestionService.php`
**Method:** `resolveByKeywords()`

**Current code (broken):**
```php
return collect($this->interestsCache)
    ->filter(fn (array $interest): bool => str_contains($text, $interest['slug']) || str_contains($text, $interest['name']))
    ->pluck('id')
    ->values()
    ->all();
```

**The problem:** `str_contains` matches anywhere inside a word. Known false positives:
- `"techniques"` matches `"tech"`
- `"transport"` matches `"sport"`
- `"supermarkets"` matches `"markets"`
- `"dwellings"` matches `"wellness"`
- `"concerns"` matches `"concerts"`
- `"hiking"` matches `"Viking"`

**The fix:** Replace `str_contains` with a word-boundary regex match. The regex must also handle slugs that contain hyphens (e.g. `food-and-drink`, `family-days-out`) by treating hyphens as word separators.

**Required change:**

Replace the `filter` callback with:

```php
->filter(function (array $interest) use ($text): bool {
    $slugPattern = '/\b' . preg_quote(str_replace('-', ' ', $interest['slug']), '/') . '\b/i';
    $namePattern = '/\b' . preg_quote($interest['name'], '/') . '\b/i';

    return preg_match($slugPattern, $text) === 1
        || preg_match($namePattern, $text) === 1;
})
```

Note: hyphens in slugs are converted to spaces before building the pattern (`food-and-drink` → `food and drink`) so the regex matches natural language. The `\b` word boundary anchors prevent partial-word matches.

---

## Bug 2 — AI result cached against empty `external_category`

**File:** `app/Services/Events/EventIngestionService.php`
**Method:** `resolveInterestIds()`

**Current code (broken):**
```php
CategoryMapping::query()->firstOrCreate(
    ['source' => $source, 'external_category' => $category],
    ['interest_id' => $interestId, 'ai_generated' => true],
);

$this->categoryMappingCache[$source][$category] = $interestId;
```

**The problem:** When `$category` is an empty string or `"undefined"`, this writes a `category_mappings` row with `external_category = ''` (or `'undefined'`). On every subsequent run, ALL events from that source with an empty/undefined category immediately hit this cached mapping and receive the same interest — regardless of what the event actually is. This is why every empty-category Ticketmaster event currently gets `arts-and-culture`: the first one the AI saw was classified that way, and now the cache locks every future one into the same bucket.

**The fix:** Only write the `category_mappings` row and update the in-memory cache when `$category` is a non-empty, meaningful string. When the category is blank or a known junk value, the AI classification is still used to set `matched_interest_ids` on that specific event, but the result is not cached because there is nothing meaningful to key it against.

**Required change:**

Wrap the `firstOrCreate` and cache-update lines in a guard:

```php
$meaningfulCategory = filled($category)
    && ! in_array(strtolower(trim($category)), ['undefined', 'miscellaneous', 'other', 'misc'], true);

if ($meaningfulCategory) {
    CategoryMapping::query()->firstOrCreate(
        ['source' => $source, 'external_category' => $category],
        ['interest_id' => $interestId, 'ai_generated' => true],
    );

    $this->categoryMappingCache[$source][$category] = $interestId;
}
```

When `$category` is empty, `"undefined"`, or `"miscellaneous"`, the interest ID is still returned so `matched_interest_ids` gets set on the event — but nothing is written to `category_mappings` and the in-memory cache is not updated.

---

## Also Fix: The Existing Bad Cached Mapping

The row `ticketmaster | [empty string] | arts-and-culture | ai_generated=1` already exists in `category_mappings` and must be deleted. It is causing all empty-category Ticketmaster events to be mapped to `arts-and-culture`.

Add a database migration to remove it:

**Create migration:** `database/migrations/[timestamp]_remove_bad_ticketmaster_empty_category_mapping.php`

```php
public function up(): void
{
    DB::table('category_mappings')
        ->where('source', 'ticketmaster')
        ->where('external_category', '')
        ->delete();

    // Also clean up 'undefined' if present
    DB::table('category_mappings')
        ->where('source', 'ticketmaster')
        ->whereIn('external_category', ['undefined', 'miscellaneous'])
        ->where('ai_generated', true)
        ->delete();
}

public function down(): void
{
    // Non-reversible data cleanup — no rollback needed
}
```

---

## After the Fix — Run Backfill

Once both code changes and the migration are in place:

```bash
php artisan migrate

# Re-resolve interests for all events now that bad mappings are gone
php artisan events:backfill-interests
```

This will re-run `resolveInterestIds()` across all events. Events that previously got the wrong interest due to the empty-category mapping will be corrected. Events that were mis-matched by the substring keyword bug (e.g. "Mini and Junior Athletics Masterclass" → tech) will also be corrected if their `matched_interest_ids` was set by the keyword path — though note the backfill command reads the existing `category` column, so any event that had its `category` column overwritten to `"tech"` by `BackfillEventCategoriesJob` will need manual correction or a targeted `events:backfill-categories` run to fix the `category` column itself first.

To fix events where `BackfillEventCategoriesJob` wrote an incorrect `category` slug directly, run:

```bash
php artisan events:backfill-categories --source=ticketmaster
```

This re-classifies events where category is blank, undefined, or miscellaneous. With Bug 1 now fixed, the keyword tier will no longer produce false positives.

---

## Summary of Files to Change

| File | Change |
|---|---|
| `app/Services/Events/EventIngestionService.php` | Fix `resolveByKeywords()` — word-boundary regex |
| `app/Services/Events/EventIngestionService.php` | Fix `resolveInterestIds()` — guard AI cache write |
| `database/migrations/[ts]_remove_bad_ticketmaster_empty_category_mapping.php` | Delete the bad empty-string and undefined mapping rows |
