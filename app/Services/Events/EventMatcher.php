<?php

namespace App\Services\Events;

use App\Models\Event;
use App\Models\Interest;
use App\Models\User;
use App\Services\Distance\DistanceCalculator;
use Illuminate\Support\Collection;

class EventMatcher
{
    /**
     * Create a new matcher instance.
     */
    public function __construct(private DistanceCalculator $distanceCalculator) {}

    /**
     * Get ranked event matches for the user.
     *
     * @return Collection<int, array{event: Event, score: float, distance_miles: float}>
     */
    public function forUser(User $user, int $limit = 8, ?float $radiusOverride = null): Collection
    {
        return $this->forInterestIds(
            $user,
            $user->interests()->pluck('interests.id'),
            $limit,
            $radiusOverride,
        );
    }

    /**
     * Get ranked event matches for an explicit set of interest IDs.
     *
     * @param  Collection<int, int>|array<int, int>  $selectedIds
     * @return Collection<int, array{event: Event, score: float, distance_miles: float}>
     */
    public function forInterestIds(
        User $user,
        Collection|array $selectedIds,
        int $limit = 8,
        ?float $radiusOverride = null,
    ): Collection {
        $selectedIds = collect($selectedIds)->map(fn (mixed $id): int => (int) $id)->unique()->values();

        if ($selectedIds->isEmpty() || $user->latitude === null || $user->longitude === null) {
            return collect();
        }

        $expandedIds = $this->expandInterestIds($selectedIds);
        $radius = $radiusOverride ?? $user->radius_miles;

        $latDelta = $radius / 69.0;
        $lngDelta = $radius / (69.0 * cos(deg2rad($user->latitude)));

        return Event::query()
            ->where('starts_at', '>=', now())
            ->where('starts_at', '<=', now()->addWeeks(3))
            ->whereNotNull('matched_interest_ids')
            ->where(fn ($q) => $q
                ->where('url_status', '!=', 'dead')
                ->orWhereNull('url_status')
            )
            ->whereBetween('latitude', [$user->latitude - $latDelta, $user->latitude + $latDelta])
            ->whereBetween('longitude', [$user->longitude - $lngDelta, $user->longitude + $lngDelta])
            ->withCount([
                'clicks as recent_clicks' => fn ($q) => $q->where('clicked_at', '>=', now()->subDays(30)),
            ])
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Event $event): ?array => $this->scoreEvent($user, $event, $expandedIds, $radius))
            ->filter()
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }

    /**
     * Expand parent interest IDs to include all their children's IDs.
     *
     * @param  Collection<int, int>  $selectedIds
     * @return Collection<int, int>
     */
    private function expandInterestIds(Collection $selectedIds): Collection
    {
        $childIds = Interest::whereIn('parent_id', $selectedIds)->pluck('id');

        return $selectedIds->merge($childIds)->unique()->values();
    }

    /**
     * Score a single event for the user.
     *
     * @param  Collection<int, int>  $interestIds
     * @return array{event: Event, score: float, distance_miles: float}|null
     */
    private function scoreEvent(User $user, Event $event, Collection $interestIds, float $radius): ?array
    {
        if ($event->latitude === null || $event->longitude === null) {
            return null;
        }

        $eventInterestIds = $event->matched_interest_ids ?? [];

        if (empty($eventInterestIds) || $interestIds->intersect($eventInterestIds)->isEmpty()) {
            return null;
        }

        $distanceMiles = $this->distanceCalculator->miles(
            $user->latitude,
            $user->longitude,
            $event->latitude,
            $event->longitude,
        );

        if ($distanceMiles > $radius) {
            return null;
        }

        $hoursUntilStart = max(now()->diffInHours($event->starts_at, false), 1);
        $distanceScore = max(0, 20 - ($distanceMiles * 0.69));        // max 20 pts, zero at ~29 miles
        $recencyScore = max(0, 20 - ($hoursUntilStart / 12));         // max 20 pts, zero at 240 hours (10 days)
        $categoryScore = 30;
        $manualBoost = (float) $event->score_manual;

        // ── Popularity score (max 25 pts) ─────────────────────────────────────
        //
        // Two-tier formula:
        //
        //   Tier 1 — AI baseline (0–20 pts)
        //     Set once at ingestion by AiEventClassifier, stored as popularity_score (1–10).
        //     Reflects expected broad appeal: stadium shows score 9–10, weekly pub
        //     quizzes score 1–2.  Unclassified events default to a neutral 5 midpoint
        //     so they aren't inadvertently penalised before their job has run.
        //
        //   Tier 2 — click engagement boost (0–5 pts)
        //     Each unique click in the last 30 days adds 1.25 pts, capped at 5.
        //     Early in the product's life this will be close to zero; as real
        //     engagement data accumulates it nudges genuinely popular events ahead
        //     of events the AI over-estimated.
        //
        $aiBase       = ($event->popularity_score ?? 5.0);            // 1.0–10.0
        $aiScore      = min(20.0, $aiBase * 2.0);                     // scaled to 0–20 pts
        $recentClicks = (int) ($event->recent_clicks ?? 0);
        $clickBoost   = min(5.0, $recentClicks * 1.25);               // 0–5 pts
        $popularityScore = min(25.0, $aiScore + $clickBoost);

        return [
            'event'          => $event,
            'score'          => round($categoryScore + $distanceScore + $recencyScore + $popularityScore + $manualBoost, 2),
            'distance_miles' => round($distanceMiles, 1),
        ];
    }
}
