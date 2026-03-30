<?php

namespace App\Services\Events;

use App\Models\Event;
use App\Models\User;
use App\Services\Distance\DistanceCalculator;
use Illuminate\Support\Collection;

class SeasonalSlotFiller
{
    public function __construct(
        private SeasonalCalendar $calendar,
        private DistanceCalculator $distance,
    ) {}

    /**
     * Pick 0–2 seasonal events near the user, bypassing the interest filter.
     *
     * Returns an empty Collection when no seasons are currently active or when
     * the user has no coordinates. Match arrays include `match_type = 'seasonal'`
     * and a `season_label` key derived from the first overlapping active season.
     *
     * @param  Collection<int, int>  $excludeEventIds
     * @return Collection<int, array>
     */
    public function pick(User $user, Collection $excludeEventIds, float $radius): Collection
    {
        $seasons = $this->calendar->activeSeasons(now());

        if (empty($seasons)) {
            return collect();
        }

        if ($user->latitude === null || $user->longitude === null) {
            return collect();
        }

        $seasonalTags = collect($seasons)
            ->flatMap(fn ($s) => $s['tags'])
            ->unique()
            ->values()
            ->all();

        // Bounding box pre-filter (same formula as EventMatcher)
        $lat      = (float) $user->latitude;
        $lng      = (float) $user->longitude;
        $latDelta = $radius / 69.0;
        $lngDelta = $radius / (69.0 * cos(deg2rad($lat)));

        $candidates = Event::query()
            ->where('starts_at', '>=', now())
            ->where('starts_at', '<=', now()->addWeeks(3))
            ->where(fn ($q) => $q->where('url_status', '!=', 'dead')->orWhereNull('url_status'))
            ->whereBetween('latitude', [$lat - $latDelta, $lat + $latDelta])
            ->whereBetween('longitude', [$lng - $lngDelta, $lng + $lngDelta])
            ->whereNotIn('id', $excludeEventIds->isEmpty() ? [0] : $excludeEventIds->all())
            ->where(function ($q) use ($seasonalTags): void {
                foreach ($seasonalTags as $tag) {
                    $q->orWhereJsonContains('tags', $tag);
                }
            })
            ->orderBy('starts_at')
            ->limit(20)
            ->get();

        // Haversine trim + scoring
        $scored = $candidates
            ->map(function (Event $event) use ($lat, $lng, $radius, $seasons): ?array {
                if ($event->latitude === null || $event->longitude === null) {
                    return null;
                }

                $distanceMiles = $this->distance->miles($lat, $lng, (float) $event->latitude, (float) $event->longitude);

                if ($distanceMiles > $radius) {
                    return null;
                }

                $score = $this->score($event, $distanceMiles, $radius);

                return [
                    'event'          => $event,
                    'score'          => $score,
                    'distance_miles' => round($distanceMiles, 1),
                    'match_type'     => 'seasonal',
                    'season_label'   => $this->resolveSeasonLabel($event, $seasons),
                ];
            })
            ->filter()
            ->sortByDesc('score')
            ->take(2)
            ->values();

        return $scored;
    }

    /**
     * Simplified scoring: distance + recency + popularity (no category score).
     */
    private function score(Event $event, float $distanceMiles, float $radius): float
    {
        // Distance score — up to 35 pts, linear decay
        $distanceScore = max(0.0, 35.0 * (1.0 - ($distanceMiles / $radius)));

        // Recency score — up to 30 pts; events sooner score higher
        $daysUntil    = (float) now()->diffInDays($event->starts_at, false);
        $recencyScore = $daysUntil >= 0
            ? max(0.0, 30.0 * (1.0 - ($daysUntil / 21.0)))
            : 0.0;

        // Popularity bonus — up to 10 pts
        $popularityBonus = $event->popularity_score !== null
            ? (float) $event->popularity_score
            : 0.0;

        return $distanceScore + $recencyScore + $popularityBonus;
    }

    /**
     * Return the label of the first active season whose tags overlap with the event's tags.
     * Falls back to the first active season's label if no tags match.
     */
    private function resolveSeasonLabel(Event $event, array $seasons): string
    {
        $eventTags = $event->tags ?? [];

        foreach ($seasons as $season) {
            if (! empty(array_intersect($eventTags, $season['tags']))) {
                return $season['emoji'].' '.$season['label'];
            }
        }

        return $seasons[0]['emoji'].' '.$seasons[0]['label'];
    }
}
