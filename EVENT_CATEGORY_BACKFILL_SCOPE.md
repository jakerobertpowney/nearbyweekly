# NearbyWeekly — Event Category Backfill Scope

## The Problem

The database currently has **3,949 events with no `matched_interest_ids`** (54% of all events). These events are invisible to every user — they will never appear in any newsletter. The root causes split cleanly into two distinct problems:

---

## Root Cause 1: Missing Category Mappings (DataThistle — ~1,500 events)

DataThistle events arrive with valid, specific category slugs, but most of them don't exist in `category_mappings`. The current table only covers the handful of categories that were mapped during initial setup. The rest fall through to the AI classifier — but since `ANTHROPIC_API_KEY` may not be set locally, they silently get no match.

**Breakdown of unmatched DataThistle categories:**

| Category | Count | Suggested Interest |
|---|---|---|
| `days-out` | 385 | `family-days-out` |
| `clubs` | 222 | `concerts` |
| `activities` | 133 | `family-days-out` |
| `easter` | 51 | `family-days-out` |
| `drama` | 43 | `theatre` |
| `film` | 36 | `arts-and-culture` |
| `books` | 35 | `arts-and-culture` |
| `workshops` | 29 | `arts-and-culture` |
| `pub-night` | 28 | `food-and-drink` |
| `alternative` | 28 | `concerts` |
| `party` | 27 | `concerts` |
| `indie` | 27 | `concerts` |
| `folk` | 26 | `concerts` |
| `classical` | 24 | `concerts` |
| `musical` | 19 | `theatre` |
| `dance` | 19 | `arts-and-culture` |
| `jazz` | 17 | `concerts` |
| `animation` | 17 | `arts-and-culture` |
| `visual-art` | 16 | `arts-and-culture` |
| `exhibition` | 16 | `arts-and-culture` |
| `electronica` | 16 | `concerts` |
| `adventure` | 15 | `hiking` |
| `dj` | 14 | `concerts` |
| `talks-lectures` | 13 | `arts-and-culture` |
| `blues` | 13 | `concerts` |
| `health` | 11 | `wellness` |
| `documentary` | 11 | `arts-and-culture` |
| `action` | 11 | `sports` |
| `country` | 10 | `concerts` |
| `biography` | 10 | `arts-and-culture` |
| `metal` | 9 | `concerts` |
| `art-craft` | 9 | `arts-and-culture` |
| `afrobeats` | 9 | `concerts` |
| `cabaret` | 6 | `theatre` |
| `drum-bass` | 6 | `concerts` |
| `k-pop` | 6 | `concerts` |
| `lgbt` | 5 | `concerts` |
| `hip-hop` | 5 | `concerts` |
| `acoustic` | 5 | `concerts` |
| `pub-crawl` | 3 | `food-and-drink` |
| `motorsport` | 3 | `sports` |
| `painter-drawing` | 2 | `arts-and-culture` |
| `installation` | 2 | `arts-and-culture` |
| `gardens` | 2 | `hiking` |
| `cycling` | 2 | `sports` |
| `ballet` | 2 | `arts-and-culture` |
| `singer-songwriter` | 1 | `concerts` |
| `quiz-night` | 1 | `food-and-drink` |
| `quiz` | 1 | `food-and-drink` |
| `pub-quiz` | 1 | `food-and-drink` |
| `football` | 1 | `sports` |
| `photography` | 1 | `arts-and-culture` |
| `karaoke` | 1 | `food-and-drink` |
| `crafts` | 7 | `arts-and-culture` |
| `charity` | 7 | `arts-and-culture` |
| `attractions` | 7 | `family-days-out` |
| `social` | 7 | `food-and-drink` |
| `class` | 2 | `wellness` |
| `courses` | 3 | `wellness` |
| `spiritual` | 1 | `wellness` |
| `crazy-golf` | 1 | `family-days-out` |
| `circus` | 1 | `family-days-out` |
| `disney` | 1 | `family-days-out` |
| `animals` | 1 | `family-days-out` |
| `seasonal` | 1 | `family-days-out` |
| `event-cinema` | 3 | `arts-and-culture` |
| `arthouse` | 1 | `arts-and-culture` |
| `art-and-design` | 1 | `arts-and-culture` |
| `art` | 2 | `arts-and-culture` |
| `contemporary` | 3 | `arts-and-culture` |
| `experimental` | 3 | `concerts` |
| `current-affairs` | 1 | `arts-and-culture` |
| `history` | 1 | `arts-and-culture` |
| `pottery-ceramics` | 1 | `arts-and-culture` |
| `embroidery` | 1 | `arts-and-culture` |
| `jewellery` | 1 | `arts-and-culture` |
| `prints` | 1 | `arts-and-culture` |
| `physical-theatre` | 1 | `theatre` |
| `pantomime` | 2 | `theatre` |
| `play` | 1 | `theatre` |
| `burlesque` | 3 | `theatre` |
| `magic` | 1 | `theatre` |
| `choir` | 1 | `concerts` |
| `choral` | 1 | `concerts` |
| `easy-listening` | 3 | `concerts` |
| `edm` | 1 | `concerts` |
| `funk` | 2 | `concerts` |
| `disco` | 4 | `concerts` |
| `soul` | 3 | `concerts` |
| `dubstep` | 2 | `concerts` |
| `breaks` | 2 | `concerts` |
| `hardcore` | 1 | `concerts` |
| `grunge` | 1 | `concerts` |
| `hard-rock` | 1 | `concerts` |
| `heavy-metal` | 1 | `concerts` |
| `grindcore` | 1 | `concerts` |
| `ambient` | 1 | `concerts` |
| `bluegrass` | 1 | `concerts` |
| `celtic` | 1 | `concerts` |
| `african` | 1 | `concerts` |
| `acid-jazz` | 1 | `concerts` |
| `alt` | 1 | `concerts` |
| `alternative-pop` | 2 | `concerts` |
| `brit-pop` | 1 | `concerts` |
| `madchester` | 1 | `concerts` |
| `cheesy-dance` | 2 | `concerts` |
| `chart-party` | 1 | `concerts` |
| `70s` | 3 | `concerts` |
| `80s` | 2 | `concerts` |
| `60s` | 1 | `concerts` |
| `2000s` | 3 | `concerts` |
| `1970s` | 1 | `concerts` |
| `1990s` | 1 | `concerts` |

> Note: Artist-specific slugs like `led-zeppelin`, `abba`, `coldplay`, `david-bowie`, etc. should map to `concerts`. Decade-era slugs (`70s`, `80s`, etc.) are DJ/dance night categories and also map to `concerts`.

**The Fix:** Expand `CategoryMappingSeeder` to include all of the above. This requires no AI and immediately unblocks ~1,500 events on the next backfill run.

---

## Root Cause 2: Ticketmaster Events with Empty or "undefined" Category (~4,300 events)

Ticketmaster events get their `category` field during ingestion from the `classificationSegment` field in the raw API payload. For 4,305 events (2,393 empty + 1,912 undefined), the API returned `null` for all classification fields — meaning `classificationSegment`, `classificationGenre`, and `classificationSubGenre` are all null in `raw_payload`.

These events therefore arrive in the database with a blank or "undefined" `category`, and since neither the mapping table nor the keyword fallback operate on title/description alone (they start from `category`), they get no `matched_interest_ids`.

**Inspecting sample events**, the titles and descriptions are clearly classifiable:
- "A Night at the Opera by Candlelight" → `concerts`
- "Ella and Friends by Candlelight" → `concerts`
- "Deeper Purple - Space Truckers Tour" → `concerts`
- "Soul Night" → `concerts`
- "Rugby Camp (Mixed)" → `sports`
- "Planet V Presents: V Classics" → `concerts`

The event content is rich — the classification data just wasn't present in the API response.

**The Fix:** A new backfill command that:
1. Identifies events where `category` is null, empty, or "undefined"
2. Runs keyword matching directly on `title` + `description` (bypassing the category lookup tier)
3. Falls back to `AiCategoryClassifier` for events that keyword matching can't resolve
4. Writes a corrected `category` slug back to the `events` table
5. Re-runs `resolveInterestIds()` to populate `matched_interest_ids`

---

## The Plan

### Step 1 — Expand `CategoryMappingSeeder`

Add all DataThistle categories from the table above to `CategoryMappingSeeder`. This is a pure data change — no new code, no AI calls.

The seeder should use `firstOrCreate` (not `insert`) so it's safe to re-run. After running, execute `events:backfill-interests` to pick up the new mappings for ~1,500 DataThistle events immediately.

Artist-specific slugs (`led-zeppelin`, `coldplay`, `abba`, etc.) don't need individual rows — they'll be caught by the AI classifier since they're in a long tail. Add the most common patterns only.

---

### Step 2 — New Command: `events:backfill-categories`

**Signature:** `events:backfill-categories {--dry-run} {--limit=} {--source=} {--skip-ai}`

**What it does:**

This command targets only events with problematic `category` values and attempts to assign a better one, then re-resolves `matched_interest_ids`. It is distinct from `events:backfill-interests` (which re-resolves interests from existing category values) — this command fixes the category value itself first.

**Processing pipeline per event:**

```
1. Load event (source, title, description, category)

2. Determine if event needs reprocessing:
   - category IS NULL
   - category = '' (empty string)
   - LOWER(TRIM(category)) = 'undefined'
   - LOWER(TRIM(category)) = 'miscellaneous'  [Ticketmaster catch-all]
   → Skip if none of the above

3. Try Tier 1: Keyword matching on title + description
   - Run the existing keyword fallback logic directly
   - Uses the interest keyword map (music, sport, food, etc.)
   → If matched: set $resolvedCategory, continue to Step 5

4. Try Tier 2: AI classification (unless --skip-ai)
   - Call AiCategoryClassifier::classify(title, description, '', $allInterests)
   - Passes empty string as category (since it's useless)
   → If matched: set $resolvedCategory, write to category_mappings with ai_generated=true

5. Update the event:
   - Set category = $resolvedCategory (the interest slug that matched)
   - Set matched_interest_ids = [$interestId]
   - Save (unless --dry-run)

6. Track stats: processed / keyword_matched / ai_matched / unresolved
```

**Output (summary table):**
```
Stat                    Count
--------------------------
Events scanned          4305
Already had category    -
Needed reprocessing     4305
Keyword resolved        1840
AI resolved             1923
Still unresolved        542
Updated in DB           3763
Dry run                 no
```

---

### Step 3 — Keyword Map (used in the command)

The keyword matching in Step 3 above uses a simple map of interest slugs to trigger words checked against title + description. This is already partially implemented in `EventIngestionService::resolveByKeywords()` — the backfill command should reuse that method directly rather than reimplementing it.

If `resolveByKeywords()` is not currently public, expose it (or extract it to a shared trait/service) so it can be called from the command.

---

### Step 4 — Suggested Order of Operations

Run these commands in sequence once the code is in place:

```bash
# 1. Reseed category mappings (adds all new DataThistle rows)
php artisan db:seed --class=CategoryMappingSeeder

# 2. Re-resolve interests for DataThistle events using new mappings
php artisan events:backfill-interests

# 3. Fix Ticketmaster events with empty/undefined category
php artisan events:backfill-categories --source=ticketmaster

# 4. Optional: dry run first to preview
php artisan events:backfill-categories --source=ticketmaster --dry-run
```

---

## Code Changes Required

| File | Change |
|---|---|
| `database/seeders/CategoryMappingSeeder.php` | Add all new DataThistle category rows from the table above |
| `app/Console/Commands/EventsBackfillCategoriesCommand.php` | New command — the main deliverable of this scope |
| `app/Services/Events/EventIngestionService.php` | Make `resolveByKeywords()` public (if it isn't already) so the command can call it |

The existing `EventsBackfillInterestsCommand` and `AiCategoryClassifier` are **unchanged** — the new command reuses them as-is.

---

## Expected Outcome

After running all four commands above, unmatched events should drop from **3,949** to an estimated **400–600** (events that genuinely have no relevant interest: adult-only events, conferences, corporate events, niche categories with no user-facing interest).

These residual events are fine to leave unmatched — they represent content that genuinely doesn't fit NearbyWeekly's interest taxonomy.
