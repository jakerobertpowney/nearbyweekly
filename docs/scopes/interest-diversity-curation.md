# Scope: Interest Diversity Curation

## Problem

The current newsletter pipeline runs `EventMatcher::forUser()`, which returns the top 8 highest-scoring events across all of a user's interests. Scoring is purely a function of category match, distance, recency, and popularity — there is no concept of balance across interest groups.

This means a user who has selected three Music sub-interests (Live Gigs, Concerts, DJ Sets) alongside one Food & Drink sub-interest and one Outdoors sub-interest can realistically receive a newsletter with all eight slots filled by music events. The other interests they selected are effectively invisible. The newsletter becomes one-dimensional and, over time, signals to the user that Eventaroo only knows about one type of event — even though it doesn't.

The newsletter's job is discovery across the breadth of what the user cares about, not a ranked list of the single best-performing category.

---

## Goal

Ensure the 8 newsletter slots are distributed across the user's selected parent interest groups, so no single group can crowd out all others, regardless of how many sub-interests they have selected within that group or how abundant/highly-scored those events are.

---

## Proposed Solution: Proportional Bucketed Curation

After `EventMatcher` scores and returns all candidate matches, a new curation layer groups those events by parent interest and allocates the 8 slots proportionally, with a hard per-group cap. This curation step is **post-scoring** — the matcher runs exactly as today and returns the full scored candidate pool. Only the final selection logic changes.

### Slot Allocation Logic

Given `N` parent interest groups with at least one matched event:

1. Each group is guaranteed a **base allocation**: `floor(8 / N)` slots.
2. Any remainder (`8 % N` slots) is distributed one extra slot at a time to the groups whose top unselected event has the highest score — this rewards groups with genuinely good events without handing them a structural advantage.
3. Within each group's allocation, events are selected in descending score order.
4. If a group cannot fill its allocation (not enough matched events), the unused slots are redistributed to remaining groups by score — no slot is ever wasted.

**Example:** User has 3 parent groups with matches (Music, Food & Drink, Outdoors).
- Base allocation: `floor(8/3)` = 2 slots each = 6 slots assigned.
- Remainder: 2 extra slots → go to the 2 groups whose next best event scores highest.
- Result: one group gets 3 events, two groups get 2 events, or two groups get 3 and one gets 2, depending on scores.

**Edge cases:**
- If only 1 group has matches, it gets all 8 slots. No change from today.
- If 8+ groups have matches, each gets exactly 1 slot and the 8 highest-scoring individual events win within that constraint.
- If a group has fewer events than its allocation, the shortfall is redistributed — iteratively, until all 8 slots are filled or the pool is exhausted.

---

## Implementation

### 1. Create `NewsletterCurator` Service

Create `app/Services/Newsletter/NewsletterCurator.php`. This class owns the curation step and is the only place the diversity logic lives.

```
app/Services/Newsletter/NewsletterCurator.php
```

**Constructor:** inject `EventMatcher`.

**Primary method:** `curate(User $user, int $limit = 8, ?float $radiusOverride = null): Collection`

The method:
1. Calls `EventMatcher::forUser()` with a **larger internal limit** than 8 (use `$limit * 6` as the candidate pool, minimum 48) so there are enough events to spread across groups after bucketing. Passing only 8 to the matcher would leave nothing to redistribute.
2. Calls a private `bucketByParentInterest(Collection $matches): Collection` method that groups each match by its parent interest ID. An event that matches multiple parent interests is placed in the group whose interest ID produces the highest score overlap (or the first matched parent if tied — keep it simple).
3. Runs the proportional slot allocation described above.
4. Returns a flat `Collection` of the selected matches in descending score order (for rendering in the email template, overall score order is still the right display order).

**Private `bucketByParentInterest()`:**
- For each match, resolve which parent interest group it belongs to. `$event->matched_interest_ids` contains child interest IDs. Load the parent for each matched child interest and select the parent that appears most frequently across the event's matched IDs (i.e. the group the event most strongly belongs to). If an event has matched interest IDs spanning multiple parent groups, assign it to the group with the most matched children — this favours specificity. Break ties by lowest parent ID (deterministic).
- Return a keyed Collection: `parent_interest_id => Collection<match>`, each sub-collection sorted by score descending.

### 2. Update `SendWeeklyNewsletterJob`

`SendWeeklyNewsletterJob` currently calls `EventMatcher` directly (or will once `NewsletterCurator` is introduced). Swap the call to use `NewsletterCurator::curate()` instead.

The fallback chain (radius ×1.5 on sparse results) stays in `SendWeeklyNewsletterJob` — it operates on the curated result count, not the raw matcher output. No change to that logic.

### 3. Update `matches:preview` Artisan Command

The `matches:preview` command calls `EventMatcher::forUser()` directly for debugging. Add an optional `--curated` flag that routes through `NewsletterCurator::curate()` instead, so engineers can preview exactly what the newsletter would contain, including the diversity step.

### 4. Store Bucket Summary on `NewsletterRun`

`NewsletterRun` already has a `bucket_summary` or metadata column (confirm, add via migration if absent). After curation, write a summary of how many events came from each parent group, e.g.:

```json
{
  "music": 3,
  "food-drink": 2,
  "outdoors-nature": 2,
  "health-fitness": 1
}
```

This makes the allocation visible in the admin dashboard and aids debugging when a user reports a poor newsletter.

---

## What Does Not Change

- `EventMatcher` is unchanged. It still returns a scored flat list. The diversity logic is entirely post-scoring.
- Scoring weights (category, distance, recency, popularity) are unchanged.
- The fallback radius expansion in `SendWeeklyNewsletterJob` is unchanged.
- The email template receives the same data shape: a flat collection of `{event, score, distance_miles}` tuples.
- The 8-event limit is unchanged.

---

## Files Affected

| File | Change |
|---|---|
| `app/Services/Newsletter/NewsletterCurator.php` | **Create** — owns all curation logic including diversity bucketing |
| `app/Jobs/SendWeeklyNewsletterJob.php` | **Update** — call `NewsletterCurator::curate()` instead of `EventMatcher::forUser()` directly |
| `app/Console/Commands/PreviewMatchesCommand.php` | **Update** — add `--curated` flag |
| `database/migrations/..._add_bucket_summary_to_newsletter_runs.php` | **Create** — add `bucket_summary` JSON column if not already present |
| `app/Models/NewsletterRun.php` | **Update** — add `bucket_summary` to `$casts` |

`EventMatcher.php` is **not modified**.

---

## Edge Case Handling

**User selects sub-interests from only one parent group.** They get up to 8 events from that group, sorted by score. Identical to today's behaviour.

**A parent group has matches but only 1 event.** It gets its base allocation or 1, whichever is lower, and the remaining slots flow to other groups.

**Two events score identically.** Within a group, order by `starts_at` ascending as a tiebreaker (sooner events first).

**Event matches interests across two parent groups.** The event is assigned to one group only (the strongest match by child count, as described above). It cannot fill slots in two groups simultaneously — this prevents a single multi-category event from gaming the allocation.

**Candidate pool too small.** If `EventMatcher` returns fewer events than the target (across all groups combined), the curator just returns everything. No artificial padding.

---

## Testing Considerations

- Unit test `NewsletterCurator` with a mocked `EventMatcher` that returns a known set of matches spanning multiple parent groups — assert the slot counts per group match the proportional formula.
- Test the shortfall redistribution: if one group only has 1 event but its allocation was 3, the extra 2 slots should go to other groups.
- Test the single-group case produces identical output to the current matcher (no regression).
- Test the `--curated` flag on `matches:preview` produces different output from the raw matcher when the user has multi-group interests.
