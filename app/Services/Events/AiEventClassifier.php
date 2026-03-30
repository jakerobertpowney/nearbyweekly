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

    private const MODEL = 'claude-haiku-4-5-20251001';

    private const TIMEOUT = 20;

    /**
     * Classify an event into sub-interests and estimate its popularity.
     *
     * Returns:
     *  - interest_ids:     array of matched sub-interest IDs
     *  - url_status:       'ok' | 'dead' | 'unchecked'
     *  - popularity_score: float 1.0–10.0 | null (if API unavailable or call failed)
     *  - tags:             array of 0–5 lowercase slug tags (seasonal and/or descriptive)
     *
     * The popularity score reflects expected broad appeal based on artist/act
     * reputation, venue size, and event type — not personal relevance to any
     * individual user.  It is stored once at ingestion and used as a ranking
     * signal in EventMatcher alongside live click data.
     *
     * Scoring guide given to the model:
     *   1–2  Very small, recurring local event (weekly pub quiz, open mic)
     *   3–4  Local/community event with modest appeal (local theatre, town fair)
     *   5–6  Regional interest; moderate demand (mid-sized touring act, food festival)
     *   7–8  High demand; well-known act or prestigious venue (UK tour headline shows)
     *   9–10 Major national/international event (stadium shows, premier league, etc.)
     *
     * @param  Collection<int, \App\Models\Interest>  $subInterests
     * @return array{interest_ids: list<int>, url_status: string, popularity_score: float|null, tags: list<string>}
     */
    public function classify(Event $event, Collection $subInterests): array
    {
        if (! filled(config('services.anthropic.api_key'))) {
            return ['interest_ids' => [], 'url_status' => 'unchecked', 'popularity_score' => null, 'tags' => []];
        }

        ['content' => $urlContent, 'status' => $urlStatus] = $this->fetchUrlContent((string) ($event->url ?? ''));
        $interestList = $this->buildInterestList($subInterests);
        $prompt = $this->buildPrompt($event, $urlContent, $interestList);

        try {
            $response = Http::withHeaders([
                'x-api-key' => config('services.anthropic.api_key'),
                'anthropic-version' => '2023-06-01',
            ])->timeout(self::TIMEOUT)->post(self::API_URL, [
                'model' => self::MODEL,
                'max_tokens' => 256,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

            if (! $response->successful()) {
                Log::warning('AiEventClassifier: API error', ['status' => $response->status()]);

                return ['interest_ids' => [], 'url_status' => $urlStatus, 'popularity_score' => null, 'tags' => []];
            }

            $text = $response->json('content.0.text', '');
            ['interest_ids' => $interestIds, 'popularity_score' => $popularityScore, 'tags' => $tags] = $this->parseResponse($text, $subInterests);

            return [
                'interest_ids'     => $interestIds,
                'url_status'       => $urlStatus,
                'popularity_score' => $popularityScore,
                'tags'             => $tags,
            ];
        } catch (ConnectionException $e) {
            Log::warning('AiEventClassifier: connection failed', ['error' => $e->getMessage()]);

            return ['interest_ids' => [], 'url_status' => $urlStatus, 'popularity_score' => null, 'tags' => []];
        }
    }

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

    private function buildInterestList(Collection $subInterests): string
    {
        return $subInterests->map(fn ($i) => sprintf(
            '- %s (slug: %s, group: %s)',
            $i->name,
            $i->slug,
            $i->parent->name ?? '',
        ))->implode("\n");
    }

    private function buildPrompt(Event $event, string $urlContent, string $interestList): string
    {
        $parts = [
            'You are classifying a UK event for a personalised newsletter.',
            '',
            'Event details:',
            "Title: {$event->title}",
            'Raw category from source API: '.($event->category ?? 'none'),
            'Venue: '.($event->venue_name ?? 'unknown').', '.($event->city ?? 'UK'),
            'Description: '.mb_substr((string) ($event->description ?? ''), 0, 500),
        ];

        if ($urlContent) {
            $parts[] = "Additional content from event page: {$urlContent}";
        }

        $parts[] = '';
        $parts[] = 'Available sub-interests (pick 1 to 3 that best fit):';
        $parts[] = $interestList;
        $parts[] = '';
        $parts[] = 'Also estimate the event\'s broad popularity on a scale of 1–10:';
        $parts[] = '  1–2  Very small recurring local event (weekly pub quiz, regular open mic)';
        $parts[] = '  3–4  Local/community event with modest appeal (amateur theatre, small town fête)';
        $parts[] = '  5–6  Regional interest; moderate demand (mid-size touring act, food festival)';
        $parts[] = '  7–8  High demand; well-known act or prestigious venue (major club night, UK headline tour)';
        $parts[] = '  9–10 Major national/international event (stadium show, premier league, famous festival)';
        $parts[] = '';
        $parts[] = 'Also tag the event with 0–5 labels from the following vocabulary only:';
        $parts[] = '  Seasonal: easter, halloween, bonfire-night, christmas, new-year, valentines, bank-holiday, summer';
        $parts[] = '  Descriptive: outdoor, indoor, family-friendly, free-entry, dog-friendly, live-music, food-drink, arts, fitness';
        $parts[] = '';
        $parts[] = 'Return ONLY a JSON object with three keys:';
        $parts[] = '  "interests": array of 1–3 slugs (or [] if nothing fits)';
        $parts[] = '  "popularity": integer 1–10';
        $parts[] = '  "tags": array of 0–5 lowercase slug tags from the vocabulary above';
        $parts[] = 'Example: {"interests": ["live-music", "festivals"], "popularity": 7, "tags": ["outdoor", "summer"]}';
        $parts[] = 'Do not explain. Return only the JSON.';

        return implode("\n", $parts);
    }

    /**
     * Score just the popularity of an event without re-classifying its interests.
     *
     * Used by the backfill command to score existing events that were ingested
     * before popularity scoring existed.  The prompt is intentionally minimal —
     * no interest list is included — which keeps token usage and cost low.
     *
     * Returns a float in the range 1.0–10.0, or null if the API is unavailable
     * or the call fails for any reason.
     */
    public function scorePopularity(Event $event): ?float
    {
        if (! filled(config('services.anthropic.api_key'))) {
            return null;
        }

        $parts = [
            'Rate the likely popularity of this UK event on a scale of 1–10.',
            '',
            'Event details:',
            "Title: {$event->title}",
            'Venue: '.($event->venue_name ?? 'unknown').', '.($event->city ?? 'UK'),
            'Description: '.mb_substr((string) ($event->description ?? ''), 0, 400),
            '',
            'Scoring guide:',
            '  1–2  Very small recurring local event (weekly pub quiz, open mic at a pub)',
            '  3–4  Local/community event with modest appeal (amateur theatre, small town fête)',
            '  5–6  Regional interest; moderate demand (mid-size touring act, food festival)',
            '  7–8  High demand; well-known act or prestigious venue (UK headline tour dates)',
            '  9–10 Major national/international event (stadium shows, premier league, major festival)',
            '',
            'Return ONLY a JSON object: {"popularity": 7}',
            'Do not explain. Return only the JSON.',
        ];

        $prompt = implode("\n", $parts);

        try {
            $response = Http::withHeaders([
                'x-api-key' => config('services.anthropic.api_key'),
                'anthropic-version' => '2023-06-01',
            ])->timeout(self::TIMEOUT)->post(self::API_URL, [
                'model' => self::MODEL,
                'max_tokens' => 32,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

            if (! $response->successful()) {
                Log::warning('AiEventClassifier::scorePopularity: API error', [
                    'status'   => $response->status(),
                    'event_id' => $event->id,
                ]);

                return null;
            }

            $text = $response->json('content.0.text', '');
            preg_match('/\{.*\}/s', $text, $matches);

            if (empty($matches[0])) {
                return null;
            }

            $data = json_decode($matches[0], true);
            $raw  = $data['popularity'] ?? null;

            if (! is_numeric($raw)) {
                return null;
            }

            return (float) max(1.0, min(10.0, (float) $raw));
        } catch (\Throwable $e) {
            Log::warning('AiEventClassifier::scorePopularity: exception', [
                'error'    => $e->getMessage(),
                'event_id' => $event->id,
            ]);

            return null;
        }
    }

    /**
     * Generate tags for an existing event without re-classifying interests.
     *
     * Used by GenerateEventTagsJob to backfill tags on events that were ingested
     * before tagging was introduced. Minimal prompt — title, description, category,
     * venue — keeps token usage low.
     *
     * Returns an array of 0–5 lowercase slug tags, or an empty array on failure.
     *
     * @return list<string>
     */
    public function tagEvent(Event $event): array
    {
        if (! filled(config('services.anthropic.api_key'))) {
            return [];
        }

        $parts = [
            'Tag this UK event with 0–5 labels from the vocabulary below.',
            '',
            'Event details:',
            "Title: {$event->title}",
            'Category: '.($event->category ?? 'none'),
            'Venue: '.($event->venue_name ?? 'unknown').', '.($event->city ?? 'UK'),
            'Description: '.mb_substr((string) ($event->description ?? ''), 0, 300),
            '',
            'Vocabulary — use only these labels:',
            '  Seasonal: easter, halloween, bonfire-night, christmas, new-year, valentines, bank-holiday, summer',
            '  Descriptive: outdoor, indoor, family-friendly, free-entry, dog-friendly, live-music, food-drink, arts, fitness',
            '',
            'Return ONLY a JSON object: {"tags": ["christmas", "outdoor"]}',
            'Return {"tags": []} if no labels fit. Do not explain.',
        ];

        try {
            $response = Http::withHeaders([
                'x-api-key'         => config('services.anthropic.api_key'),
                'anthropic-version' => '2023-06-01',
            ])->timeout(self::TIMEOUT)->post(self::API_URL, [
                'model'      => self::MODEL,
                'max_tokens' => 64,
                'messages'   => [
                    ['role' => 'user', 'content' => implode("\n", $parts)],
                ],
            ]);

            if (! $response->successful()) {
                Log::warning('AiEventClassifier::tagEvent: API error', [
                    'status'   => $response->status(),
                    'event_id' => $event->id,
                ]);

                return [];
            }

            $text = $response->json('content.0.text', '');
            preg_match('/\{.*\}/s', $text, $m);

            if (empty($m[0])) {
                return [];
            }

            $data = json_decode($m[0], true);
            $tags = is_array($data['tags'] ?? null) ? $data['tags'] : [];

            return array_values(array_filter(array_map('strval', $tags)));
        } catch (\Throwable $e) {
            Log::warning('AiEventClassifier::tagEvent: exception', [
                'error'    => $e->getMessage(),
                'event_id' => $event->id,
            ]);

            return [];
        }
    }

    /**
     * Parse the model response into interest IDs, a popularity score, and tags.
     *
     * @param  Collection<int, \App\Models\Interest>  $subInterests
     * @return array{interest_ids: list<int>, popularity_score: float|null, tags: list<string>}
     */
    private function parseResponse(string $text, Collection $subInterests): array
    {
        preg_match('/\{.*\}/s', $text, $matches);

        if (empty($matches[0])) {
            return ['interest_ids' => [], 'popularity_score' => null, 'tags' => []];
        }

        $data = json_decode($matches[0], true);

        if (! is_array($data)) {
            return ['interest_ids' => [], 'popularity_score' => null, 'tags' => []];
        }

        // ── Interest IDs ──────────────────────────────────────────────────────
        $slugs  = is_array($data['interests'] ?? null) ? $data['interests'] : [];
        $lookup = $subInterests->keyBy('slug');

        $interestIds = collect($slugs)
            ->map(fn ($slug) => $lookup->get($slug)?->id)
            ->filter()
            ->values()
            ->all();

        // ── Popularity score ──────────────────────────────────────────────────
        $raw             = $data['popularity'] ?? null;
        $popularityScore = null;

        if (is_numeric($raw)) {
            $popularityScore = (float) max(1.0, min(10.0, (float) $raw));
        }

        // ── Tags ──────────────────────────────────────────────────────────────
        $rawTags = is_array($data['tags'] ?? null) ? $data['tags'] : [];
        $tags    = array_values(array_filter(array_map('strval', $rawTags)));

        return [
            'interest_ids'     => $interestIds,
            'popularity_score' => $popularityScore,
            'tags'             => $tags,
        ];
    }
}
