# Scope: URL Status Checking on Event Classification

## Problem

Events are ingested from external APIs and stored with a `url` field pointing to the ticketing or event page. No check is ever made to verify that URL is still live. Dead links in a newsletter — pointing to sold-out, cancelled, or removed event pages — damage trust and provide a poor user experience.

`AiEventClassifier::fetchUrlContent()` already makes an HTTP request to the event URL as part of classification. The HTTP status code is available at that point but is currently discarded. This scope adds a lightweight URL health check as a by-product of the existing fetch, marks dead events, and excludes them from newsletters.

---

## What Needs Building

### 1. Migration — `url_status` and `url_checked_at` on `events`

Add two columns to the `events` table:

```php
$table->string('url_status')->default('unchecked'); // unchecked | ok | dead
$table->timestamp('url_checked_at')->nullable();
```

No index needed — `EventMatcher` will filter on this column but the cardinality is low enough that a full scan on the already-filtered result set is fine. Add one if query profiling later suggests otherwise.

**Do not backfill existing rows.** They default to `unchecked`, which is correct — no check has been run on them yet.

---

### 2. Update `AiEventClassifier::fetchUrlContent()`

Currently the method returns `string` (the page text, or empty string on any failure). It needs to also communicate whether the URL was conclusively dead (404) versus merely unreachable or unreadable.

**Change the return type** to an array:

```php
private function fetchUrlContent(string $url): array // ['content' => string, 'status' => string]
```

Return values for `status`:
- `'ok'` — response was 2xx, content extracted successfully
- `'dead'` — response was exactly 404
- `'unchecked'` — URL was empty/invalid, timed out, threw an exception, or returned any non-404 error (5xx, 403, etc.)

The distinction between `dead` and `unchecked` is intentional:
- **404** is the only status code that unambiguously means "this resource does not exist". It is safe to act on.
- **5xx, 403, 429, timeouts** could be transient. Marking an event dead because of a momentary server error or a bot-blocking response would incorrectly suppress real events. These stay `unchecked` so a future re-check can resolve them.
- **No URL / invalid URL** — the event was ingested without a valid link. Mark as `unchecked`, not `dead`. A missing URL is a data quality issue, not a dead link.

Updated logic:

```php
private function fetchUrlContent(string $url): array
{
    if (! filter_var($url, FILTER_VALIDATE_URL)) {
        return ['content' => '', 'status' => 'unchecked'];
    }

    try {
        $response = Http::timeout(8)
            ->withHeaders(['User-Agent' => 'NearbyWeekly/1.0 (events discovery newsletter)'])
            ->get($url);

        if ($response->status() === 404) {
            return ['content' => '', 'status' => 'dead'];
        }

        if ($response->successful()) {
            $text = strip_tags($response->body());
            $text = preg_replace('/\s+/', ' ', $text);
            return ['content' => mb_substr(trim($text), 0, 1500), 'status' => 'ok'];
        }
    } catch (\Throwable) {
        // Timeout, DNS failure, connection refused — treat as unchecked
    }

    return ['content' => '', 'status' => 'unchecked'];
}
```

Update the `classify()` method to destructure the new return value:

```php
['content' => $urlContent, 'status' => $urlStatus] = $this->fetchUrlContent((string) ($event->url ?? ''));
```

Return `$urlStatus` alongside the classified interest IDs so `ClassifyEventJob` can act on it:

```php
// classify() return type changes from array to array with two keys
return ['interest_ids' => $interestIds, 'url_status' => $urlStatus];
```

---

### 3. Update `ClassifyEventJob::handle()`

After calling `$classifier->classify()`, act on the returned `url_status`:

```php
$result = $classifier->classify($event, $subInterests);

$urlStatus    = $result['url_status'];
$interestIds  = $result['interest_ids'];

// Always record the URL check result
$event->url_checked_at = now();
$event->url_status     = $urlStatus;

if ($urlStatus === 'dead') {
    $event->save();
    Log::info('ClassifyEventJob: dead URL, event suppressed', [
        'event_id' => $event->id,
        'title'    => $event->title,
        'url'      => $event->url,
    ]);
    return;
}

if (! empty($interestIds)) {
    $event->matched_interest_ids = $interestIds;
}

$event->save();

if (empty($interestIds)) {
    Log::info('ClassifyEventJob: no sub-interest match', [
        'event_id' => $event->id,
        'title'    => $event->title,
    ]);
}
```

Key points:
- `url_checked_at` is always written, even for dead events. This timestamps when the check ran.
- Dead events return early — no interest classification is attempted, no `matched_interest_ids` is written. There is no point classifying an event that cannot be linked to.
- Non-dead events continue through the classification flow unchanged.

---

### 4. Update `EventMatcher::forUser()`

Add one filter clause to the existing Eloquent query to exclude events with dead URLs:

```php
->where(fn ($q) => $q
    ->where('url_status', '!=', 'dead')
    ->orWhereNull('url_status')
)
```

The `orWhereNull` handles any legacy rows that predate the column being added (though the migration default of `'unchecked'` makes this redundant — include it as a safety net).

This goes alongside the existing `starts_at` window filters and the bounding box `whereBetween` clauses, before `->get()`.

---

### 5. `EventsBackfillInterestsCommand` — Exclude Dead Events

Update the query in the command to skip events already confirmed dead:

```php
$query = Event::query()
    ->whereNull('matched_interest_ids')
    ->where('url_status', '!=', 'dead')
    ->orderBy('id')
    ->select('id');
```

No point dispatching `ClassifyEventJob` for events already known to be dead.

---

## What This Does Not Cover

**Re-checking previously dead events.** Some events are marked dead because the ticketer temporarily 404s during a transfer or re-platform. An `events:recheck-urls` command that queries `where url_status = dead` and re-runs just the URL fetch (without classification) would be a sensible follow-up, but is out of scope here. The `url_checked_at` timestamp makes it trivial to add later — filter by `url_checked_at < now()->subDays(7)` to only re-check stale dead events.

**Events with no URL at all.** These stay `unchecked` indefinitely. They will still appear in newsletters if they have `matched_interest_ids`. That is arguably fine — some manually-created events may not have a ticket URL. If this becomes a problem, a separate flag (`url_missing`) or a newsletter-send check on `url` being non-null can be added independently.

**JS-rendered ticket pages.** Pages that return 200 with a React/Vue shell and hydrate via client-side JS will appear as `ok` with empty content. The URL is live — that is the correct result. Classification just won't have page content to work with, which is the existing behaviour.

---

## Summary of Changes

| File | Change |
|---|---|
| New migration | Add `url_status` (string, default `unchecked`) and `url_checked_at` (nullable timestamp) to `events` |
| `AiEventClassifier` | `fetchUrlContent()` returns `['content', 'status']`; `classify()` returns `['interest_ids', 'url_status']` |
| `ClassifyEventJob` | Acts on `url_status`; writes `url_checked_at` always; returns early and suppresses dead events |
| `EventMatcher` | Adds `where url_status != dead` filter to newsletter query |
| `EventsBackfillInterestsCommand` | Skips events already marked dead |
