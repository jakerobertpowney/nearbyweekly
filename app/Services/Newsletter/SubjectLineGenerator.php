<?php

namespace App\Services\Newsletter;

use App\Models\User;

class SubjectLineGenerator
{
    /**
     * Generate a subject line by picking a random event from the buckets.
     *
     * @param  array<string, list<array{event: \App\Models\Event, score: float}>>  $buckets
     */
    public function generate(User $user, array $buckets, string $dayType): string
    {
        $all = collect($buckets)->flatten(1)->values();

        if ($all->isEmpty()) {
            $postcode = $user->postcode;

            return match ($dayType) {
                'saturday' => "Still happening near {$postcode} this weekend",
                'sunday'   => "Plan your week — events near {$postcode}",
                'friday'   => "This weekend near {$postcode} — what's on",
                default    => "Your weekly events near {$postcode}",
            };
        }

        $pick  = $all->random();
        $title = $pick['event']->title;
        $others = $all->count() - 1;

        if ($others <= 0) {
            return "{$title} 🎉";
        }

        return "{$title} + {$others} more events near you 🎉";
    }
}
