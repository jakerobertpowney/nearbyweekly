# NearbyWeekly — Newsletter Matching & Curation Scope

## Objective

Define how `SendWeeklyNewsletterJob` selects and orders events for each user's weekly newsletter. The goal is a newsletter that feels curated rather than algorithmic — varied in day, interest, and time horizon — while still being personalised to each user's preferences and location.

---

## The Problem With the Current Approach

The existing `EventMatcher::forUser()` returns a flat list of events ordered by score (category match + distance + recency). This means:

- A user who follows both "Concerts" and "Food & Drink" might receive 8 concerts and no food events if concerts score higher
- All 8 events might fall on the same Saturday if that day has the highest density of events
- There is no sense of temporal structure — the email reads like a raw list rather than a curated weekly guide

---

## Curation Goals

1. **At least one event per matched interest** (where events exist for that interest)
2. **Events spread across different days** — no more than 2 events on the same date
3. **Events grouped by time horizon** — This Weekend → This Week → This Month
4. **Maximum 10 events total** in the final newsletter
5. **Minimum 3 events** before the newsletter is sent (existing fallback logic applies)

---

## Time Bucket Definitions

These are evaluated relative to the newsletter send date (typically Thursday morning):

| Bucket | Definition | Example (sent Thursday 27 March) |
|---|---|---|
| **This Weekend** | Saturday and Sunday of the current calendar week | Sat 29 March, Sun 30 March |
| **This Week** | Monday–Friday of the _following_ calendar week | Mon 31 March – Fri 4 April |
| **This Month** | Any remaining dates within the current calendar month that fall after "This Week" | 5 April – 30 April |

> **Note:** "This Week" intentionally refers to the _next_ Mon–Fri window rather than the current one. By Thursday, Monday–Wednesday of the current week have already passed and Thursday/Friday are too soon to plan for most users. The forward-looking Mon–Fri gives users 4–10 days to plan.

Events beyond the end of the current month are excluded unless all three buckets are empty, in which case events up to 6 weeks out may fill the gaps.

---

## Selection Algorithm

### Phase 1 — Interest-First Pass

For each of the user's interests (ordered by their original selection, or alphabetically as a tiebreaker):

1. Query `EventMatcher` for events matching **only that interest**, within the user's radius
2. Take the **top 2 scoring events** for that interest
3. Add them to a candidate pool, tagged with the interest they matched

This guarantees representation for each interest. If a user has 5 interests and there are events for all 5, the candidate pool starts with up to 10 events.

### Phase 2 — Score and Deduplicate

The candidate pool may contain duplicates (an event matching two interests appears twice). Deduplicate by `event.id`, keeping the highest-scoring occurrence and preserving both matched interest tags for the "Matched because you follow" indicator in the email.

### Phase 3 — Day-Spread Filter

Sort candidates by score descending. Walk the sorted list and build the final selection:

- Maintain a count of events selected per calendar date
- Accept an event if `events_on_this_date < 2`
- Skip (do not permanently discard) if the date already has 2 events
- Continue until either the list is exhausted or 10 events are selected

If skipping events causes the selection to drop below 3, relax the per-day cap to 3 and retry the walk.

### Phase 4 — Bucket Assignment

Assign each selected event to a bucket based on `starts_at`:

```
This Weekend  → starts_at falls on Sat or Sun of the current calendar week
This Week     → starts_at falls Mon–Fri of the following calendar week
This Month    → starts_at falls within the remainder of the current month
```

Any event that does not fall into one of the three buckets (e.g. next month) is placed in a fourth catch-all bucket labelled **"Coming Up"** and rendered after "This Month". This bucket only appears if it contains events.

### Phase 5 — Fallback When Buckets Are Sparse

If after Phase 4 the selection has fewer than 3 events:

1. **First fallback:** expand the radius by 1.5× and re-run Phase 1–4
2. **Second fallback:** remove the per-interest constraint and run `EventMatcher` in its original flat-score mode, capped at the expanded radius
3. **Final fallback:** if still fewer than 3 events, mark the `NewsletterRun` as `no_matches` and skip the send for this user (existing behaviour)

The fallback level used should be stored on the `NewsletterRun` record (e.g. a `fallback_level` tinyint column: 0 = no fallback, 1 = expanded radius, 2 = flat mode) for visibility in the admin dashboard.

---

## Final Sort Order (Within Each Bucket)

Within each bucket, events are ordered:

1. **Score descending** (highest relevance first)
2. **`starts_at` ascending** as a tiebreaker (earlier events first within the same score)

This means the most relevant event of the weekend appears first, but events within the same relevance tier are shown in chronological order.

---

## Data Structures

### Input to the curation layer

```php
// Already available on the User model
$user->interests       // Collection of Interest models (with slug, id)
$user->latitude
$user->longitude
$user->radius_miles

// Available in SendWeeklyNewsletterJob
$run                   // NewsletterRun model
```

### Candidate event shape (internal, during curation)

```php
[
    'event'              => Event,       // Eloquent model
    'score'              => float,       // from EventMatcher
    'distance_miles'     => float,       // from EventMatcher
    'matched_interest_ids' => int[],     // which interest(s) this event matched
    'bucket'             => string,      // 'weekend' | 'week' | 'month' | 'coming_up'
]
```

### Output to the Blade template

```php
// $matches passed to WeeklyNewsletterMail
// Keyed by bucket for easy iteration in the template
$matches = [
    'weekend'    => [...],   // ordered candidates
    'week'       => [...],
    'month'      => [...],
    'coming_up'  => [...],   // only if non-empty
];
```

The template iterates buckets in order: weekend → week → month → coming_up. Each bucket renders a labelled section header (e.g. "This Weekend") followed by its event cards. Buckets with no events are silently omitted.

---

## Implementation Location

All curation logic should live in a new service class: `app/Services/Events/NewsletterCurator.php`.

`SendWeeklyNewsletterJob` calls `NewsletterCurator::curate(User $user): array` which returns the bucketed `$matches` structure above, or throws a `NoMatchesException` if the final selection has fewer than 3 events after all fallbacks are exhausted.

`EventMatcher::forUser()` continues to exist unchanged — `NewsletterCurator` calls it once per interest in Phase 1, and again in flat mode during Phase 2 fallback if needed. Keeping the matcher unaware of curation logic preserves separation of concerns.

---

## `NewsletterRun` Schema Additions

Add two columns to `newsletter_runs` via migration:

| Column | Type | Default | Purpose |
|---|---|---|---|
| `fallback_level` | tinyint | 0 | 0 = no fallback, 1 = expanded radius, 2 = flat mode |
| `bucket_summary` | json | null | e.g. `{"weekend": 2, "week": 3, "month": 1}` — counts per bucket for admin visibility |

---

## Newsletter Template Changes

The `emails.newsletters.weekly` Blade template (covered in `NEWSLETTER_DESIGN_SCOPE.md`) needs the following additions to support bucketed output:

1. **Section header row** above each non-empty bucket:
   - Label: `THIS WEEKEND`, `THIS WEEK`, `THIS MONTH`, or `COMING UP`
   - Style: Poppins 600, 11px, uppercase, tracked, `#f97316` (matches the eyebrow style from the hero)
   - Followed by a 1px `#f1f5f9` divider

2. **"Matched because you follow" line** on each card (already in the design scope as optional) is now standard — use the first entry in `matched_interest_ids` to resolve the interest name.

3. The hero subtext changes from `{N} events matched to your interests` to `{N} events across {bucket_count} time windows, curated just for you.`

---

## Edge Cases

| Scenario | Handling |
|---|---|
| User has one interest | All 10 slots may be filled by that interest; day-spread still applies |
| Interest has no matching events | Skip silently; do not show empty section |
| All events fall on the same day | Day-spread cap relaxed to 3; if still same-day, accept — better a full email than a sparse one |
| User's send date is Saturday or Sunday | "This Weekend" = today + tomorrow; "This Week" = following Mon–Fri |
| No events in "This Month" bucket | Bucket is omitted from the email entirely |
| Event matches multiple interests | Appears once in the newsletter, shows the highest-scoring matched interest in the badge |
