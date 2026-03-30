<?php

use App\Models\Event;
use App\Models\Interest;
use App\Models\User;
use App\Services\Distance\DistanceCalculator;
use App\Services\Events\EventMatcher;

test('event matcher ranks nearby matching events ahead of irrelevant or distant ones', function () {
    $comedy = Interest::factory()->create([
        'name' => 'Comedy',
        'slug' => 'comedy',
    ]);

    $user = User::factory()->manchester()->create([
        'radius_miles' => 15,
    ]);
    $user->interests()->sync([$comedy->id]);

    $nearby = Event::factory()->create([
        'title' => 'Nearby Comedy Night',
        'category' => 'comedy',
        'matched_interest_ids' => [$comedy->id],
        'city' => 'Manchester',
        'postcode' => 'M1 1AE',
        'latitude' => 53.479251,
        'longitude' => -2.247926,
        'starts_at' => now()->addDays(2),
        'score_manual' => 5,
    ]);

    Event::factory()->create([
        'title' => 'Far Away Comedy',
        'category' => 'comedy',
        'matched_interest_ids' => [$comedy->id],
        'city' => 'London',
        'postcode' => 'SW1A 1AA',
        'latitude' => 51.501009,
        'longitude' => -0.141588,
        'starts_at' => now()->addDays(2),
    ]);

    Event::factory()->create([
        'title' => 'Nearby Tech Meetup',
        'category' => 'tech',
        'matched_interest_ids' => null,
        'city' => 'Manchester',
        'postcode' => 'M1 1AE',
        'latitude' => 53.479251,
        'longitude' => -2.247926,
        'starts_at' => now()->addDays(1),
    ]);

    $matcher = new EventMatcher(new DistanceCalculator);
    $matches = $matcher->forUser($user);

    expect($matches)->toHaveCount(1);
    expect($matches->first()['event']->is($nearby))->toBeTrue();
    expect($matches->first()['score'])->toBeGreaterThan(30);
});
