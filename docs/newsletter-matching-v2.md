# Newsletter Matching v2 — Scope & Design

## Overview

This document covers the second generation of Eventaroo's newsletter curation engine. The changes affect four interconnected areas: how events are ranked, how recurring events are deduplicated, how the newsletter adapts to the day it's sent, and how the email template surfaces personalised vs. suggested content.

All changes are backward-compatible. The fallback chain, ingestion pipeline, and click-tracking route are unchanged.

---

## 1. Popularity Scoring

### Problem

The previous scoring formula ranked events purely on interest match (30 pts), distance (up to 35 pts), and recency (up to 30 pts). Two events the same distance away that both match a user's interests would score identically regardless of whether one is Coldplay at Wembley and the other is an open mic at a corner pub.

### Approach: AI baseline + click engagement

Popularity is scored in two tiers, contributing a combined maximum of 10 points to an event's total ranking score.

**Tier 1 — AI baseline (0–8 pts)**

When `ClassifyEventJob` runs for a newly ingested event, `AiEventClassifier` now also asks Claude to estimate the event's broad appeal on a 1–10 scale. The score is stored in a new `popularity_score` column on the `events` table and computed once — never recalculated at newsletter send time.

The scoring guide given to the model:

| Score | Meaning |
|-------|---------|
| 1–2 | Very small recurring local event (weekly pub quiz, open mic at a pub) |
| 3–4 | Local/community event with modest appeal (amateur theatre, small town fête) |
| 5–6 | Regional interest; moderate demand (mid-size touring act, food festival) |
| 7–8 | High demand; well-known act or prestigious venue (UK headline tour dates) |
| 9–10 | Major national/international event (stadium shows, premier league, major festival) |

Claude has strong training-data awareness of artist reputations, venue prestige (O2 Arena vs. a pub back room), and event category demand. This is a more meaningful signal than anything the DataThistle or Ticketmaster APIs expose in their raw payloads — neither provider returns popularity metrics of any kind.

Unclassified events (job not yet run, or API key unavailable) default to a neutral 5.0 midpoint so they are not inadvertently penalised in rankings.

**Tier 2 — click engagement boost (0–2 pts)**

Each click recorded in `event_clicks` in the last 30 days adds 0.5 pts, capped at 2. Early in the product's life this is close to zero. As engagement data accumulates it nudges events the community is actually clicking above events the AI over-estimated, and vice versa.

**Formula**

```
aiScore       = min(8.0,  popularity_score * 0.8)   // AI contributes 0–8 pts
clickBoost    = min(2.0,  recent_clicks * 0.5)       // clicks contribute 0–2 pts
popularityScore = min(10.0, aiScore + clickBoost)    // combined cap: 10 pts
```

**Why not raw API data?**

Both DataThistle and Ticketmaster responses were inspected. Neither returns view counts, sales velocity, demand signals, or trending flags in their standard payloads. The `raw_payload` column holds the full API response, but there is nothing popularity-related to extract. AI classification is the only viable path on day one.

### Schema change

```sql
ALTER TABLE events ADD COLUMN popularity_score FLOAT(4,1) NULL
  COMMENT 'AI-estimated popularity 1.0–10.0; null = not yet scored';
```

Migration: `2026_03_29_000001_add_popularity_score_to_events_table.php`

### Files changed

| File | Change |
|------|--------|
| `database/migrations/2026_03_29_000001_...php` | New migration |
| `app/Models/Event.php` | Added `popularity_score` to `$fillable` and `casts` |
| `app/Services/Events/AiEventClassifier.php` | Prompt extended; response parsing returns `popularity_score` |
| `app/Jobs/ClassifyEventJob.php` | Stores `popularity_score` from classifier result |
| `app/Services/Events/EventMatcher.php` | New two-tier formula replaces click-only scoring |

---

## 2. Recurring Event Deduplication

### Problem

Events like "Hill Dickinson Stadium Tours" are stored in the database once per performance date. When several dates fall within the same newsletter window, the same event can appear four times in a single bucket, consuming all four slots with identical content.

### Root cause

The previous `candidateSeriesKey()` used the event's stored `slug` field as the primary identity. Providers (DataThistle, Ticketmaster) frequently set slugs to include the performance date — e.g. `stadium-tours-2026-04-05` — which makes every dated instance unique and defeats deduplication entirely.

### Fix

Always derive the series key from `Str::slug($event->title)` rather than the stored slug. Titles are stable across recurring performances; slugs are not.

```php
private function candidateSeriesKey(array $candidate): string
{
    $event     = $candidate['event'];
    $titleSlug = Str::slug(Str::lower(trim((string) $event->title)));

    return implode('|', [
        $titleSlug,
        Str::lower(trim((string) $event->venue_name)),
        Str::lower(trim((string) ($event->postcode ?: $event->city))),
    ]);
}
```

The key is `title|venue|location`. Two events with the same title at the same venue collapse to one, with the highest-scored / soonest occurrence winning. Events with the same title at different venues remain separate entries.

Deduplication is applied globally across all three buckets, so a recurring event cannot appear in both "This Weekend" and "Coming Soon" either.

### Files changed

| File | Change |
|------|--------|
| `app/Services/Events/NewsletterCurator.php` | `candidateSeriesKey()` rewritten |

---

## 3. Newsletter Bucket Filling — Suggestions

### Problem

The previous curation strategy required exactly 4 events per bucket (weekend / week / coming soon) from the user's own selected interests before it would send. If any bucket came up short, it triggered a multi-level fallback chain (wider radius, sibling interests). A newsletter was only sent once all three buckets hit 4.

This was overly strict. It meant users with niche interests or who live in lower-density areas received no newsletter at all rather than a partially filled one supplemented with well-chosen suggestions.

### New strategy: two-pass bucket filling

Each bucket is now filled in two passes:

**Pass 1 — Direct matches**
Events matching the user's selected interests, sorted by score descending. Up to 4 events per bucket.

**Pass 2 — "We thought you'd like" suggestions**
If Pass 1 produces fewer than 4 events for a given bucket, the remaining slots are filled from events matching any interest the user did *not* select. These items are tagged `match_type: 'suggestion'` and rendered with a distinct badge in the email.

The fallback radius expansion (×1.5) is retained, but it now triggers only when there are genuinely zero events across all three buckets — not just because a single bucket is under-filled.

**Suggestion pool**

The suggestion pool is all interest IDs not in the user's selected set. It is loaded lazily — the DB query only runs if at least one bucket needs topping up. If Pass 1 fills all three buckets completely, no suggestion query is made.

### Fallback levels

| Level | Condition |
|-------|-----------|
| 0 | Normal radius, direct interests filled all buckets (possibly with suggestions) |
| 1 | Radius ×1.5, same filling logic |
| `no_matches` | Zero events after both attempts — newsletter suppressed |

### Email rendering

Suggestion items render with an amber pill badge:

> ✨ We thought you'd like · *Interest Name*

Direct matches continue to render with:

> Matched: *Interest Name*

### Files changed

| File | Change |
|------|--------|
| `app/Services/Events/NewsletterCurator.php` | `curate()`, `attempt()`, `fillBuckets()` rewritten; `relatedInterestIds()` removed |
| `resources/views/emails/newsletters/weekly.blade.php` | Suggestion badge added |

---

## 4. Day-Aware Newsletter Timing

### Problem

The newsletter was sent with fixed section headings and fixed slot counts regardless of when it was dispatched. A Sunday send with "This Weekend" headers felt hollow — the weekend was nearly over. A Saturday send with four weekend events listed could easily include events that had already started hours earlier.

### Solution: timing context block

`NewsletterCurator::buildNewsletterContext()` inspects `now()` at send time and returns a context block that drives the email content:

```php
[
    'day_type'       => 'normal' | 'friday' | 'saturday' | 'sunday',
    'intro_line'     => string,
    'bucket_labels'  => ['weekend' => string, 'week' => string, 'coming_soon' => string],
    'max_per_bucket' => ['weekend' => int, 'week' => int, 'coming_soon' => int],
]
```

### Variants by day

| Day | Weekend label | Weekend max | Intro line |
|-----|--------------|-------------|-----------|
| Mon–Thu | THIS WEEKEND | 4 | Here's what's happening near you |
| Fri | HAPPENING THIS WEEKEND | 4 | Here's what's happening near you this weekend and beyond |
| Sat | STILL ON THIS WEEKEND | **2** | You're just in time — here's what's still on this weekend |
| Sun | STILL ON TODAY | **2** | Here's what's coming up near you — plan your week ahead |

On Saturday and Sunday the weekend bucket is capped at 2 (not 4). The goal is quality over quantity — showing only the most compelling events still bookable, rather than a full grid that includes events starting in an hour.

The "week" and "coming_soon" buckets always show 4 events regardless of send day, since those dates are unaffected by timing.

### Subject line

`WeeklyNewsletterMail::envelope()` also varies the subject by day type:

| Day | Subject |
|-----|---------|
| Sat | Still happening near {postcode} this weekend |
| Sun | Plan your week — events near {postcode} |
| Fri | This weekend near {postcode} — what's on |
| Other | Your weekly events near {postcode} |

### Bucket boundaries

The existing `bucketBoundaries()` logic already handles mid-weekend sends correctly:
- On Saturday: the weekend bucket runs from Saturday midnight to Sunday end-of-day
- On Sunday: the weekend bucket runs from Saturday to Sunday end-of-day
- Past events are excluded at the query level (`starts_at >= now()`), so only remaining events surface

No changes to boundary logic were needed.

### Files changed

| File | Change |
|------|--------|
| `app/Services/Events/NewsletterCurator.php` | `buildNewsletterContext()` added; `curate()` returns context |
| `app/Jobs/SendWeeklyNewsletterJob.php` | Extracts `newsletter_context` from result; passes to mail |
| `app/Mail/WeeklyNewsletterMail.php` | Accepts `$newsletterContext`; dynamic `envelope()` subject |
| `resources/views/emails/newsletters/weekly.blade.php` | Dynamic labels, intro line, and h1 copy |

---

## 5. Scoring Formula Summary

The complete scoring formula after all changes:

```
total_score = category_score
            + distance_score
            + recency_score
            + popularity_score
            + score_manual

category_score   = 30                                    (flat, any matching event)
distance_score   = max(0, 35 - distance_miles * 1.2)    (0–35 pts)
recency_score    = max(0, 30 - hours_until_start / 8)   (0–30 pts)
popularity_score = min(10, ai_base + click_boost)        (0–10 pts)
  ai_base        = min(8.0, popularity_score * 0.8)
  click_boost    = min(2.0, recent_clicks * 0.5)
score_manual     = float (admin override, additive)

theoretical max  = 30 + 35 + 30 + 10 = 105 (plus manual boost)
```

---

## 6. Dependency Map

```
ClassifyEventJob
  └─ AiEventClassifier.classify()
       ├─ fetches event URL
       ├─ builds prompt (interests + popularity guide)
       ├─ calls Claude Haiku
       └─ returns { interest_ids, url_status, popularity_score }
            └─ stored on Event: matched_interest_ids, url_status, popularity_score

SendWeeklyNewsletterJob
  └─ NewsletterCurator.curate(user)
       ├─ buildCandidates(direct interests)    → EventMatcher.forInterestIds()
       ├─ buildCandidates(suggestion interests) → EventMatcher.forInterestIds() [lazy]
       ├─ fillBuckets() — deduplicates by title|venue|location
       ├─ trims to max_per_bucket from context
       └─ buildNewsletterContext() → day-aware labels, intro, slot counts
            └─ WeeklyNewsletterMail($user, $buckets, $unsubscribeUrl, $newsletterContext)
                 └─ emails.newsletters.weekly.blade.php
```

---

## 7. What Was Not Changed

- `EventIngestionService` — ingestion pipeline is unchanged
- `FetchEventsJob` / scheduler entries — unchanged
- `DistanceCalculator` — unchanged
- `PostcodesIoGeocoder` / geocoding resilience — unchanged
- `event_clicks` tracking route (`/events/{id}/go`) — unchanged
- Category mapping table and seeder — unchanged
- Frontend onboarding and preferences flows — unchanged
- All existing migrations — no alterations

---

## 8. Migration Checklist

Before deploying to production:

- [ ] Run `php artisan migrate` to add `popularity_score` to `events`
- [ ] Existing events will have `popularity_score = null`; they score as 5.0 (neutral midpoint) until `ClassifyEventJob` runs for them
- [ ] To backfill: dispatch `ClassifyEventJob` for events with `popularity_score IS NULL` — a simple Artisan command or Tinker loop is sufficient
- [ ] No other data migrations required
