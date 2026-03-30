<?php

use App\Models\Event;
use App\Models\Interest;
use App\Models\User;
use App\Services\Events\NewsletterCurator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

function createInterestFamily(string $parentName, string $selectedName, string $relatedName): array
{
    $parent = Interest::factory()->create([
        'name' => $parentName,
        'slug' => str($parentName)->slug()->value(),
    ]);

    $selected = Interest::factory()->create([
        'parent_id' => $parent->id,
        'name' => $selectedName,
        'slug' => str($selectedName)->slug()->value(),
    ]);

    $related = Interest::factory()->create([
        'parent_id' => $parent->id,
        'name' => $relatedName,
        'slug' => str($relatedName)->slug()->value(),
    ]);

    return [$parent, $selected, $related];
}

function createMatchedEvent(string $title, CarbonImmutable $startsAt, int $interestId, int $scoreManual = 0): Event
{
    return Event::factory()->create([
        'title' => $title,
        'category' => 'concerts',
        'matched_interest_ids' => [$interestId],
        'city' => 'London',
        'postcode' => 'SW1A 1AA',
        'latitude' => 51.507351,
        'longitude' => -0.127758,
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->addHours(2),
        'score_manual' => $scoreManual,
    ]);
}

function createMatchedEventWithVenue(
    string $title,
    CarbonImmutable $startsAt,
    int $interestId,
    string $venueName,
    string $city = 'London',
    string $postcode = 'SW1A 1AA',
    int $scoreManual = 0,
): Event {
    return Event::factory()->create([
        'title' => $title,
        'slug' => str($title)->slug()->value(),
        'category' => 'concerts',
        'matched_interest_ids' => [$interestId],
        'venue_name' => $venueName,
        'city' => $city,
        'postcode' => $postcode,
        'latitude' => 51.507351,
        'longitude' => -0.127758,
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->addHours(2),
        'score_manual' => $scoreManual,
    ]);
}

test('newsletter curator fills four events in each newsletter section', function () {
    Carbon::setTestNow(CarbonImmutable::parse('2026-04-02 09:00:00'));

    [, $selectedInterest] = createInterestFamily('Music', 'Concerts', 'Festivals');

    $user = User::factory()->create([
        'postcode' => 'SW1A 1AA',
        'latitude' => 51.507351,
        'longitude' => -0.127758,
        'radius_miles' => 25,
    ]);
    $user->interests()->sync([$selectedInterest->id]);

    foreach (range(0, 3) as $index) {
        createMatchedEvent("Weekend event {$index}", CarbonImmutable::parse('2026-04-04 10:00:00')->addHours($index * 2), $selectedInterest->id, 10 - $index);
        createMatchedEvent("Week event {$index}", CarbonImmutable::parse('2026-04-06 18:00:00')->addDays($index), $selectedInterest->id, 10 - $index);
        createMatchedEvent("Coming soon event {$index}", CarbonImmutable::parse('2026-04-11 18:00:00')->addDays($index), $selectedInterest->id, 10 - $index);
    }

    $result = app(NewsletterCurator::class)->curate($user);

    expect($result['fallback_level'])->toBe(0);
    expect($result['bucket_summary'])->toBe([
        'weekend' => 4,
        'week' => 4,
        'coming_soon' => 4,
    ]);
    expect(array_keys($result['buckets']))->toBe(['weekend', 'week', 'coming_soon']);
    expect(collect($result['buckets'])->flatten(1))->toHaveCount(12);
    expect(collect($result['buckets'])->flatten(1)->pluck('match_type')->unique()->all())->toBe(['direct']);
});

test('newsletter curator fills short buckets with related-interest suggestions', function () {
    Carbon::setTestNow(CarbonImmutable::parse('2026-04-02 09:00:00'));

    [, $selectedInterest, $relatedInterest] = createInterestFamily('Music', 'Concerts', 'Festivals');

    $user = User::factory()->create([
        'postcode' => 'SW1A 1AA',
        'latitude' => 51.507351,
        'longitude' => -0.127758,
        'radius_miles' => 25,
    ]);
    $user->interests()->sync([$selectedInterest->id]);

    foreach (range(0, 2) as $index) {
        createMatchedEvent("Weekend direct {$index}", CarbonImmutable::parse('2026-04-04 10:00:00')->addHours($index * 2), $selectedInterest->id, 10 - $index);
        createMatchedEvent("Week direct {$index}", CarbonImmutable::parse('2026-04-06 18:00:00')->addDays($index), $selectedInterest->id, 10 - $index);
        createMatchedEvent("Coming soon direct {$index}", CarbonImmutable::parse('2026-04-11 18:00:00')->addDays($index), $selectedInterest->id, 10 - $index);
    }

    createMatchedEvent('Weekend related', CarbonImmutable::parse('2026-04-05 18:00:00'), $relatedInterest->id);
    createMatchedEvent('Week related', CarbonImmutable::parse('2026-04-09 18:00:00'), $relatedInterest->id);
    createMatchedEvent('Coming soon related', CarbonImmutable::parse('2026-04-14 18:00:00'), $relatedInterest->id);

    $result = app(NewsletterCurator::class)->curate($user);

    $relatedMatches = collect($result['buckets'])
        ->flatten(1)
        ->where('match_type', 'related')
        ->values();

    expect($result['fallback_level'])->toBe(1);
    expect($result['bucket_summary'])->toBe([
        'weekend' => 4,
        'week' => 4,
        'coming_soon' => 4,
    ]);
    expect($relatedMatches)->toHaveCount(3);
    expect($relatedMatches->pluck('display_interest_id')->unique()->all())->toBe([$relatedInterest->id]);
});

test('newsletter curator deduplicates recurring listings of the same event series', function () {
    Carbon::setTestNow(CarbonImmutable::parse('2026-04-02 09:00:00'));

    [, $selectedInterest] = createInterestFamily('Tours', 'Stadium Tours', 'Museums');

    $user = User::factory()->create([
        'postcode' => 'SW1A 1AA',
        'latitude' => 51.507351,
        'longitude' => -0.127758,
        'radius_miles' => 25,
    ]);
    $user->interests()->sync([$selectedInterest->id]);

    createMatchedEventWithVenue('Hill Dickinson Stadium Tour', CarbonImmutable::parse('2026-04-04 10:00:00'), $selectedInterest->id, 'Hill Dickinson Stadium', scoreManual: 12);
    createMatchedEventWithVenue('Hill Dickinson Stadium Tour', CarbonImmutable::parse('2026-04-04 14:00:00'), $selectedInterest->id, 'Hill Dickinson Stadium', scoreManual: 11);
    createMatchedEventWithVenue('City Stadium Tour', CarbonImmutable::parse('2026-04-04 12:00:00'), $selectedInterest->id, 'Etihad Stadium', scoreManual: 10);
    createMatchedEventWithVenue('Anfield Legends Tour', CarbonImmutable::parse('2026-04-05 11:00:00'), $selectedInterest->id, 'Anfield', scoreManual: 9);
    createMatchedEventWithVenue('Dockside History Walk', CarbonImmutable::parse('2026-04-05 15:00:00'), $selectedInterest->id, 'Albert Dock', scoreManual: 8);
    createMatchedEventWithVenue('Mersey River Cruise', CarbonImmutable::parse('2026-04-05 17:00:00'), $selectedInterest->id, 'Pier Head', scoreManual: 7);

    foreach (range(0, 3) as $index) {
        createMatchedEvent("Week unique {$index}", CarbonImmutable::parse('2026-04-06 18:00:00')->addDays($index), $selectedInterest->id, 10 - $index);
        createMatchedEvent("Coming soon unique {$index}", CarbonImmutable::parse('2026-04-11 18:00:00')->addDays($index), $selectedInterest->id, 10 - $index);
    }

    $result = app(NewsletterCurator::class)->curate($user);

    $allTitles = collect($result['buckets'])
        ->flatten(1)
        ->pluck('event.title');

    expect($allTitles->filter(fn (string $title): bool => $title === 'Hill Dickinson Stadium Tour'))->toHaveCount(1);
    expect($result['bucket_summary'])->toBe([
        'weekend' => 4,
        'week' => 4,
        'coming_soon' => 4,
    ]);
});
