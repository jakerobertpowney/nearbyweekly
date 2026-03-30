# Popularity Scoring — Scope

## Problem

Events are currently ranked on interest match, distance, and recency. Two events the same distance away that both match a user's interests score identically — there is nothing to distinguish a Coldplay show at the O2 from a weekly pub quiz. The newsletter fills slots in date order rather than by likely appeal.

---

## Why the APIs Can't Help

The obvious first move was checking whether DataThistle or Ticketmaster expose any popularity signals in their responses. They don't. Both APIs return title, description, category, venue, dates, and images. No view counts, no sales velocity, no demand indicators, no trending flags. The `raw_payload` column holds the full API response for both providers and there is nothing useful in it for popularity.

---

## Solution: AI Scoring Piggybacked on ClassifyEventJob

Every newly ingested event already passes through `ClassifyEventJob`, which calls `AiEventClassifier` to assign interest categories. That job:

- Fetches the live event URL and reads the page content
- Passes event title, description, venue, and page text to Claude Haiku
- Parses the JSON response and stores matched interest IDs

The API call is already happening. Adding a popularity estimate to the same prompt costs a handful of extra output tokens and nothing else.

Claude has strong training-data knowledge of artist reputations, venue sizes, and event category demand. It knows the O2 Arena holds 20,000 people. It knows who Coldplay are. It knows a weekly pub quiz is not a sold-out stadium show. This contextual knowledge is exactly what's needed — and it's available for free on a call already being made.

---

## What Gets Added

**A new field in the classifier prompt:**

```
Also estimate the event's broad popularity on a scale of 1–10:
  1–2  Very small recurring local event (weekly pub quiz, open mic at a pub)
  3–4  Local/community event with modest appeal (amateur theatre, small town fête)
  5–6  Regional interest; moderate demand (mid-size touring act, food festival)
  7–8  High demand; well-known act or prestigious venue (UK headline tour dates)
  9–10 Major national/international event (stadium shows, premier league, major festival)
```

**The response shape changes from:**

```json
{"interests": ["live-music", "festivals"]}
```

**To:**

```json
{"interests": ["live-music", "festivals"], "popularity": 7}
```

The classifier parses the new field, clamps it strictly to 1.0–10.0, and returns it alongside the existing interest IDs. `ClassifyEventJob` then stores it in a new `popularity_score` column on the `events` table.

---

## Schema Change

```sql
ALTER TABLE events
  ADD COLUMN popularity_score FLOAT(4,1) NULL
  COMMENT 'AI-estimated popularity 1.0–10.0; null = not yet scored';
```

Existing events get `NULL` until their `ClassifyEventJob` runs. The scorer treats `NULL` as a neutral 5.0 midpoint so unscored events aren't penalised before their job has run.

---

## Scoring Formula

Popularity contributes a maximum of 10 points to the total event score, split across two tiers:

```
ai_base      = min(8.0,  popularity_score * 0.8)   // AI contributes 0–8 pts
click_boost  = min(2.0,  recent_clicks * 0.5)       // last-30-day clicks add 0–2 pts
popularity   = min(10.0, ai_base + click_boost)
```

The AI score is the primary signal. It's meaningful from day one because it's based on Claude's knowledge, not accumulated user behaviour. The click boost is a small correction layer — as real engagement data grows, events the AI over-estimated drift down and events the community genuinely loves drift up.

The 8/2 split is intentional: the AI baseline should dominate early while clicks are sparse, but clicks should never be irrelevant once the product has traction.

---

## Cost

Negligible. `ClassifyEventJob` already calls Claude Haiku per event. The prompt grows by roughly 10 lines and the response grows by one field. At Haiku pricing this is a fraction of a cent per event. The score is stored permanently — it is never recomputed at newsletter send time.

---

## Graceful Degradation

- If `ANTHROPIC_API_KEY` is not set, `AiEventClassifier` returns `popularity_score: null` and the event scores as 5.0 (neutral)
- If the API call fails or returns malformed JSON, same fallback
- If the model returns a value outside 1–10, it is clamped before storage
- Dead URLs suppress the event entirely (existing behaviour, unchanged)

---

## Files Changed

| File | Change |
|------|--------|
| `database/migrations/2026_03_29_000001_add_popularity_score_to_events_table.php` | New migration |
| `app/Models/Event.php` | `popularity_score` added to `$fillable` and `casts` |
| `app/Services/Events/AiEventClassifier.php` | Prompt extended; response parsing returns `popularity_score` |
| `app/Jobs/ClassifyEventJob.php` | Stores `popularity_score` from classifier result |
| `app/Services/Events/EventMatcher.php` | New two-tier popularity formula replaces click-only scoring |

---

## Deployment

1. Run `php artisan migrate`
2. Existing events score as 5.0 until classified — no degradation in newsletter quality
3. To backfill, dispatch `ClassifyEventJob` for events where `popularity_score IS NULL`
