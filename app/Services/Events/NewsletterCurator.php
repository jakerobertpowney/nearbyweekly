<?php

namespace App\Services\Events;

use App\Exceptions\NoMatchesException;
use App\Models\Event;
use App\Models\Interest;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class NewsletterCurator
{
    public function __construct(
        private EventMatcher $eventMatcher,
        private SeasonalSlotFiller $seasonalSlotFiller,
    ) {}

    /**
     * Curate a personalised, bucketed event selection for the given user.
     *
     * Strategy:
     *  1. Fill each bucket (weekend / week / coming_soon) with up to 4 events
     *     matching the user's own interests — highest-scored first.
     *  2. If any bucket still has fewer than 4 slots, top it up from events
     *     matching interests the user did NOT select. Those extras are tagged
     *     match_type = 'suggestion' and rendered as "We thought you'd like".
     *  3. If no events are found at all within the user's radius, widen the
     *     radius by 1.5× and try again (fallback_level 1).
     *  4. Bucket labels and max slot counts are dynamically adjusted based on
     *     the day of the week so the email always feels relevant right now.
     *
     * @return array{
     *     buckets: array{
     *         weekend: list<array>,
     *         week: list<array>,
     *         coming_soon: list<array>
     *     },
     *     fallback_level: int,
     *     bucket_summary: array{weekend: int, week: int, coming_soon: int},
     *     newsletter_context: array{
     *         day_type: string,
     *         intro_line: string,
     *         bucket_labels: array<string, string>,
     *         max_per_bucket: array<string, int>
     *     }
     * }
     *
     * @throws NoMatchesException
     */
    public function curate(User $user): array
    {
        $directInterestIds = $user->interests()->pluck('interests.id')
            ->map(fn (mixed $id): int => (int) $id);

        // All interests the user has NOT selected — the suggestion pool.
        $allInterestIds = Interest::query()
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id);

        $suggestionInterestIds = $allInterestIds->diff($directInterestIds)->values();

        $attempts = [
            ['radius' => $user->radius_miles,        'fallback_level' => 0],
            ['radius' => $user->radius_miles * 1.5,  'fallback_level' => 1],
        ];

        foreach ($attempts as $attempt) {
            $result = $this->attempt(
                $user,
                $directInterestIds,
                $suggestionInterestIds,
                (float) $attempt['radius'],
            );

            if ($result !== null) {
                $context = $this->buildNewsletterContext();

                // Trim each bucket to the context-defined maximum.
                foreach ($context['max_per_bucket'] as $bucketKey => $max) {
                    if (isset($result['buckets'][$bucketKey])) {
                        $result['buckets'][$bucketKey] = array_slice($result['buckets'][$bucketKey], 0, $max);
                    }
                }

                $allSelectedIds = collect($result['buckets'])
                    ->flatten(1)
                    ->pluck('event.id');

                $seasonalPicks = $this->seasonalSlotFiller->pick(
                    $user,
                    $allSelectedIds,
                    (float) $attempt['radius'],
                );

                return [
                    'buckets'          => $result['buckets'],
                    'seasonal_picks'   => $seasonalPicks->all(),
                    'fallback_level'   => $attempt['fallback_level'],
                    'bucket_summary'   => [
                        'weekend'     => count($result['buckets']['weekend']),
                        'week'        => count($result['buckets']['week']),
                        'coming_soon' => count($result['buckets']['coming_soon']),
                        'by_interest' => $result['interest_summary'] ?? [],
                    ],
                    'newsletter_context' => array_merge($context, [
                        'seasonal_label' => $seasonalPicks->isNotEmpty()
                            ? $seasonalPicks->first()['season_label']
                            : null,
                    ]),
                ];
            }
        }

        throw new NoMatchesException("No matches found for user [{$user->id}] after all fallbacks.");
    }

    /**
     * Run a single curation attempt with the given radius.
     *
     * Returns null only when there are genuinely zero matching events across
     * all three buckets, which triggers the next fallback attempt.
     *
     * @param  Collection<int, int>  $directInterestIds
     * @param  Collection<int, int>  $suggestionInterestIds
     */
    private function attempt(
        User $user,
        Collection $directInterestIds,
        Collection $suggestionInterestIds,
        float $radius,
    ): ?array {
        $directCandidates = $this->buildCandidates($user, $directInterestIds, $radius, 'direct');

        // Build the interest→parent map once from the direct candidate pool so we can
        // apply the same diversity logic consistently across all three time-buckets.
        $interestParentMap = $this->buildInterestParentMap($directCandidates);

        // Suggestion candidates are loaded lazily — only if a bucket needs topping up.
        $suggestionCandidates = null;
        $getSuggestions = function () use ($user, $suggestionInterestIds, $radius, &$suggestionCandidates): Collection {
            if ($suggestionCandidates === null) {
                $suggestionCandidates = $suggestionInterestIds->isNotEmpty()
                    ? $this->buildCandidates($user, $suggestionInterestIds, $radius, 'suggestion')
                    : collect();
            }

            return $suggestionCandidates;
        };

        $buckets = $this->fillBuckets($directCandidates, $getSuggestions, $interestParentMap);

        $totalEvents = array_sum(array_map('count', $buckets));

        if ($totalEvents === 0) {
            return null;
        }

        return [
            'buckets'          => $buckets,
            'interest_summary' => $this->buildInterestSummary($buckets, $interestParentMap),
        ];
    }

    /**
     * Build a scored, typed candidate list for the given interest IDs.
     *
     * @param  Collection<int, int>  $interestIds
     * @param  'direct'|'suggestion'  $matchType
     * @return Collection<int, array{
     *     event: Event,
     *     score: float,
     *     distance_miles: float,
     *     matched_interest_ids: array<int, int>,
     *     display_interest_id: int|null,
     *     match_type: string
     * }>
     */
    private function buildCandidates(
        User $user,
        Collection $interestIds,
        float $radius,
        string $matchType,
    ): Collection {
        if ($interestIds->isEmpty()) {
            return collect();
        }

        $expandedInterestIds = $this->expandInterestIds($interestIds);

        return $this->eventMatcher
            ->forInterestIds($user, $interestIds, 200, $radius)
            ->map(function (array $match) use ($expandedInterestIds, $matchType): array {
                $displayInterestId = collect($match['event']->matched_interest_ids ?? [])
                    ->intersect($expandedInterestIds)
                    ->map(fn (mixed $id): int => (int) $id)
                    ->first();

                return [
                    'event'                => $match['event'],
                    'score'                => $match['score'],
                    'distance_miles'       => $match['distance_miles'],
                    'matched_interest_ids' => array_map(
                        fn (mixed $id): int => (int) $id,
                        $match['event']->matched_interest_ids ?? [],
                    ),
                    'display_interest_id'  => $displayInterestId,
                    'match_type'           => $matchType,
                ];
            });
    }

    // ── Interest-diversity helpers ────────────────────────────────────────────

    /**
     * Select up to $limit events from $candidates using proportional
     * parent-interest-group allocation.
     *
     * Algorithm:
     *  1. Group candidates by their primary parent interest.
     *  2. Give each group a base allocation of floor($limit / N) slots.
     *  3. Distribute any remainder (and any shortfall from under-full groups)
     *     one slot at a time to whichever group has the highest-scoring
     *     next-available event.
     *  4. Return the selected events sorted by score descending.
     *
     * Edge cases:
     *  - Single group or limit ≤ 1 → skip bucketing, return top-N by score.
     *  - A group with fewer events than its allocation contributes to the
     *    "remainder" pool, which is redistributed to other groups.
     *  - If the whole pool is exhausted before $limit is reached, return what
     *    exists — no artificial padding.
     *
     * @param  Collection<int, array>  $candidates  Pre-sorted by score desc.
     * @param  array<int, int>         $interestParentMap  interest_id → parent_id
     * @return Collection<int, array>
     */
    private function applyDiversity(Collection $candidates, int $limit, array $interestParentMap): Collection
    {
        if ($candidates->isEmpty() || $limit <= 0) {
            return collect();
        }

        // Group by primary parent interest and sort each group by score desc.
        $groups = $candidates
            ->groupBy(fn (array $m): int => $this->primaryParentInterestId(
                $m['matched_interest_ids'] ?? [],
                $interestParentMap,
            ))
            ->map(fn (Collection $g): Collection => $g->sortByDesc('score')->values());

        $n = $groups->count();

        // With only one group, diversity is irrelevant — just take the best.
        if ($n <= 1) {
            return $candidates->sortByDesc('score')->take($limit)->values();
        }

        // Track how many events have been selected from each group.
        $slots = [];
        $totalAssigned = 0;

        foreach ($groups as $pid => $events) {
            $base     = (int) floor($limit / $n);
            $assigned = min($base, $events->count());
            $slots[$pid]   = $assigned;
            $totalAssigned += $assigned;
        }

        // Distribute the remainder (including slots freed by under-full groups)
        // to whoever has the highest-scoring next available event.
        $toAssign = $limit - $totalAssigned;

        while ($toAssign > 0) {
            $bestScore = -1.0;
            $bestPid   = null;

            foreach ($groups as $pid => $events) {
                $next = $events->get($slots[$pid]);

                if ($next === null) {
                    continue; // group exhausted
                }

                if ((float) $next['score'] > $bestScore) {
                    $bestScore = (float) $next['score'];
                    $bestPid   = $pid;
                }
            }

            if ($bestPid === null) {
                break; // all groups exhausted
            }

            $slots[$bestPid]++;
            $toAssign--;
        }

        // Collect and re-sort the final selection.
        $selected = collect();

        foreach ($groups as $pid => $events) {
            $selected = $selected->merge($events->take($slots[$pid]));
        }

        return $selected->sortByDesc('score')->values();
    }

    /**
     * Determine the primary parent interest ID for a set of matched interest IDs.
     *
     * The parent that appears most frequently across the matched IDs wins.
     * Ties are broken by the lowest parent ID (deterministic).
     * Returns 0 if no parent can be resolved.
     *
     * @param  array<int, int>  $matchedInterestIds
     * @param  array<int, int>  $interestParentMap  interest_id → parent_id
     */
    private function primaryParentInterestId(array $matchedInterestIds, array $interestParentMap): int
    {
        if (empty($matchedInterestIds)) {
            return 0;
        }

        $parentCounts = [];

        foreach ($matchedInterestIds as $id) {
            $parentId = $interestParentMap[(int) $id] ?? 0;
            $parentCounts[$parentId] = ($parentCounts[$parentId] ?? 0) + 1;
        }

        $maxCount = max($parentCounts);
        $tied     = array_keys(array_filter($parentCounts, fn (int $c): bool => $c === $maxCount));
        sort($tied);

        return (int) $tied[0];
    }

    /**
     * Build a flat map of interest_id → parent_id from the candidates pool.
     *
     * Only child interests (those with a parent) appear in the map; parent
     * interests themselves have no entry so they don't accidentally resolve
     * to their own IDs.
     *
     * @param  Collection<int, array>  $candidates
     * @return array<int, int>
     */
    private function buildInterestParentMap(Collection $candidates): array
    {
        $allIds = $candidates
            ->flatMap(fn (array $m): array => array_map('intval', $m['matched_interest_ids'] ?? []))
            ->unique()
            ->values()
            ->all();

        if (empty($allIds)) {
            return [];
        }

        return Interest::query()
            ->whereIn('id', $allIds)
            ->whereNotNull('parent_id')
            ->pluck('parent_id', 'id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * Summarise which parent interest groups are represented in the curated output.
     *
     * Returns an array keyed by parent interest slug, e.g.:
     *   ['music' => 3, 'food-drink' => 2, 'outdoors-nature' => 2]
     *
     * @param  array<string, list<array>>  $buckets
     * @param  array<int, int>             $interestParentMap  interest_id → parent_id
     * @return array<string, int>
     */
    private function buildInterestSummary(array $buckets, array $interestParentMap): array
    {
        $counts = [];

        foreach ($buckets as $events) {
            foreach ($events as $match) {
                $parentId = $this->primaryParentInterestId(
                    $match['matched_interest_ids'] ?? [],
                    $interestParentMap,
                );
                $counts[$parentId] = ($counts[$parentId] ?? 0) + 1;
            }
        }

        if (empty($counts)) {
            return [];
        }

        $slugs = Interest::query()
            ->whereIn('id', array_keys($counts))
            ->pluck('slug', 'id')
            ->all();

        $summary = [];

        foreach ($counts as $parentId => $count) {
            $slug            = $slugs[$parentId] ?? "group-{$parentId}";
            $summary[$slug]  = $count;
        }

        arsort($summary);

        return $summary;
    }

    /**
     * Expand parent interest IDs to include their children.
     *
     * @param  Collection<int, int>  $interestIds
     * @return Collection<int, int>
     */
    private function expandInterestIds(Collection $interestIds): Collection
    {
        $childIds = Interest::query()
            ->whereIn('parent_id', $interestIds)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id);

        return $interestIds->merge($childIds)->unique()->values();
    }

    /**
     * Fill each time-bucket with up to 4 events.
     *
     * Direct-match events fill first, distributed proportionally across the
     * user's parent interest groups so no single category can monopolise all
     * slots.  Any remaining slots are topped up from interests the user didn't
     * select ("We thought you'd like" suggestions), also diversity-allocated.
     *
     * Recurring-event deduplication is applied globally across all buckets so
     * the same event series can only appear once in the entire newsletter.
     *
     * @param  Collection<int, array>  $directCandidates
     * @param  array<int, int>         $interestParentMap  interest_id → parent_id
     * @return array{weekend: list<array>, week: list<array>, coming_soon: list<array>}
     */
    private function fillBuckets(Collection $directCandidates, callable $getSuggestions, array $interestParentMap): array
    {
        $buckets            = [];
        $selectedSeriesKeys = [];

        foreach (['weekend', 'week', 'coming_soon'] as $bucketKey) {
            // ── Pass 1: direct-match candidates, deduplicated, diversity-allocated ─
            $directPool = $this->bucketCandidates($directCandidates, $bucketKey)
                ->filter(fn (array $c): bool => ! in_array($this->candidateSeriesKey($c), $selectedSeriesKeys, true))
                ->values();

            $selected = $this->applyDiversity($directPool, 4, $interestParentMap);

            foreach ($selected as $candidate) {
                $selectedSeriesKeys[] = $this->candidateSeriesKey($candidate);
            }

            // ── Pass 2: suggestion top-up if direct pool couldn't fill 4 slots ──
            $remaining = 4 - $selected->count();

            if ($remaining > 0) {
                $suggestionPool = $this->bucketCandidates($getSuggestions(), $bucketKey)
                    ->filter(fn (array $c): bool => ! in_array($this->candidateSeriesKey($c), $selectedSeriesKeys, true))
                    ->values();

                $suggestions = $this->applyDiversity($suggestionPool, $remaining, $interestParentMap);

                foreach ($suggestions as $candidate) {
                    $selectedSeriesKeys[] = $this->candidateSeriesKey($candidate);
                }

                $selected = $selected->merge($suggestions)->sortByDesc('score')->values();
            }

            $buckets[$bucketKey] = $selected->all();
        }

        return [
            'weekend'     => $buckets['weekend'],
            'week'        => $buckets['week'],
            'coming_soon' => $buckets['coming_soon'],
        ];
    }

    /**
     * Sort and deduplicate candidates for a single bucket.
     *
     * Events are first grouped by their series key so that multiple dated
     * instances of the same recurring event collapse into one (the
     * highest-scored / soonest occurrence wins).  The resulting unique events
     * are then sorted by score descending, then start date ascending.
     *
     * @param  Collection<int, array>  $candidates
     * @return Collection<int, array>
     */
    private function bucketCandidates(Collection $candidates, string $bucketKey): Collection
    {
        return $candidates
            ->filter(fn (array $c): bool => $this->bucketKey($c['event']->starts_at) === $bucketKey)
            ->groupBy(fn (array $c): string => $this->candidateSeriesKey($c))
            ->map(function (Collection $group): array {
                return $group
                    ->sort(function (array $a, array $b): int {
                        if ($a['score'] !== $b['score']) {
                            return $b['score'] <=> $a['score'];
                        }

                        return $a['event']->starts_at <=> $b['event']->starts_at;
                    })
                    ->first();
            })
            ->sort(function (array $a, array $b): int {
                if ($a['score'] !== $b['score']) {
                    return $b['score'] <=> $a['score'];
                }

                return $a['event']->starts_at <=> $b['event']->starts_at;
            })
            ->values();
    }

    /**
     * Build a stable series key for recurring-event deduplication.
     *
     * We intentionally use the normalised title rather than the stored slug
     * because provider-assigned slugs often include the performance date
     * (e.g. "stadium-tours-2026-04-05"), which would defeat deduplication for
     * series like "Hill Dickinson Stadium Tours" that share the same title and
     * venue across many date instances.
     */
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

    /**
     * Classify a start datetime into one of the three time-buckets.
     *
     * Events before the upcoming Saturday (i.e. still upcoming this week)
     * are placed in 'week' rather than falling through to 'coming_soon'.
     * 'coming_soon' is reserved strictly for events after next Friday.
     */
    private function bucketKey(CarbonInterface $startsAt): string
    {
        [$saturday, $sunday, $nextMonday, $nextFriday] = $this->bucketBoundaries(now()->toImmutable());

        if ($startsAt->betweenIncluded($saturday, $sunday->endOfDay())) {
            return 'weekend';
        }

        // Events earlier this week (before the upcoming Saturday) and events
        // in the following Mon–Fri window both belong in the 'week' bucket.
        if ($startsAt->lt($saturday) || $startsAt->betweenIncluded($nextMonday, $nextFriday)) {
            return 'week';
        }

        return 'coming_soon';
    }

    /**
     * Compute the Saturday / Sunday / next-Monday / next-Friday boundaries
     * relative to the given instant, handling mid-weekend sends correctly.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface, 2: CarbonInterface, 3: CarbonInterface}
     */
    private function bucketBoundaries(CarbonInterface $now): array
    {
        if ($now->isSaturday()) {
            $saturday = $now->startOfDay();
            $sunday   = $saturday->addDay()->startOfDay();
        } elseif ($now->isSunday()) {
            $saturday = $now->subDay()->startOfDay();
            $sunday   = $now->startOfDay();
        } else {
            $saturday = $now->startOfWeek(Carbon::MONDAY)->addDays(5)->startOfDay();
            $sunday   = $saturday->addDay();
        }

        $nextMonday = $sunday->addDay()->startOfDay();
        $nextFriday = $nextMonday->addDays(4)->endOfDay();

        return [$saturday, $sunday, $nextMonday, $nextFriday];
    }

    /**
     * Build a timing-aware context block that drives dynamic email content.
     *
     * The context tells the mail template:
     *  - Which day-type variant to render (normal / friday / saturday / sunday)
     *  - The introductory line that appears beneath the header
     *  - The heading label for each bucket section
     *  - How many events to show per bucket (quality > quantity on weekends)
     *
     * @return array{
     *     day_type: string,
     *     intro_line: string,
     *     bucket_labels: array<string, string>,
     *     max_per_bucket: array<string, int>
     * }
     */
    private function buildNewsletterContext(): array
    {
        $now = now();

        if ($now->isSunday()) {
            // Sunday: the weekend is nearly over — shift focus to next week.
            return [
                'day_type'       => 'sunday',
                'intro_line'     => "Here's what's coming up near you — plan your week ahead",
                'bucket_labels'  => [
                    'weekend'     => 'STILL ON TODAY',
                    'week'        => 'NEXT WEEK',
                    'coming_soon' => 'COMING SOON',
                ],
                'max_per_bucket' => [
                    'weekend'     => 2,   // Only the most compelling remaining-today events
                    'week'        => 4,
                    'coming_soon' => 4,
                ],
            ];
        }

        if ($now->isSaturday()) {
            // Saturday: keep it tight — only the best remaining weekend events.
            return [
                'day_type'       => 'saturday',
                'intro_line'     => "You're just in time — here's what's still on this weekend",
                'bucket_labels'  => [
                    'weekend'     => 'STILL ON THIS WEEKEND',
                    'week'        => 'NEXT WEEK',
                    'coming_soon' => 'COMING SOON',
                ],
                'max_per_bucket' => [
                    'weekend'     => 2,   // Reduced: quality over quantity
                    'week'        => 4,
                    'coming_soon' => 4,
                ],
            ];
        }

        if ($now->isFriday()) {
            // Friday: the weekend is imminent — lean into it.
            return [
                'day_type'       => 'friday',
                'intro_line'     => "Here's what's happening near you this weekend and beyond",
                'bucket_labels'  => [
                    'weekend'     => 'HAPPENING THIS WEEKEND',
                    'week'        => 'THIS WEEK',
                    'coming_soon' => 'COMING SOON',
                ],
                'max_per_bucket' => [
                    'weekend'     => 4,
                    'week'        => 4,
                    'coming_soon' => 4,
                ],
            ];
        }

        // Mon–Thu: standard format.
        return [
            'day_type'       => 'normal',
            'intro_line'     => "Here's what's happening near you",
            'bucket_labels'  => [
                'weekend'     => 'THIS WEEKEND',
                'week'        => 'THIS WEEK',
                'coming_soon' => 'COMING SOON',
            ],
            'max_per_bucket' => [
                'weekend'     => 4,
                'week'        => 4,
                'coming_soon' => 4,
            ],
        ];
    }
}
