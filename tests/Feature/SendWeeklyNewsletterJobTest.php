<?php

use App\Jobs\SendWeeklyNewsletterJob;
use App\Mail\WeeklyNewsletterMail;
use App\Models\Event;
use App\Models\Interest;
use App\Models\NewsletterItem;
use App\Models\NewsletterRun;
use App\Models\User;
use App\Services\Events\NewsletterCurator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

afterEach(function () {
    Carbon::setTestNow();
});

function createNewsletterInterestFamily(): array
{
    $parent = Interest::factory()->create([
        'name' => 'Music',
        'slug' => 'music',
    ]);

    $selected = Interest::factory()->create([
        'parent_id' => $parent->id,
        'name' => 'Concerts',
        'slug' => 'concerts',
    ]);

    $related = Interest::factory()->create([
        'parent_id' => $parent->id,
        'name' => 'Festivals',
        'slug' => 'festivals',
    ]);

    return [$selected, $related];
}

function createNewsletterEvent(string $title, CarbonImmutable $startsAt, int $interestId, int $scoreManual = 0): Event
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

test('send weekly newsletter job stores twelve items and renders related-interest suggestions', function () {
    Mail::fake();
    Carbon::setTestNow(CarbonImmutable::parse('2026-04-02 09:00:00'));

    [$selectedInterest, $relatedInterest] = createNewsletterInterestFamily();

    $user = User::factory()->create([
        'email' => 'subscriber@example.com',
        'postcode' => 'SW1A 1AA',
        'latitude' => 51.507351,
        'longitude' => -0.127758,
        'radius_miles' => 25,
        'newsletter_enabled' => true,
    ]);
    $user->interests()->sync([$selectedInterest->id]);

    foreach (range(0, 2) as $index) {
        createNewsletterEvent("Weekend direct {$index}", CarbonImmutable::parse('2026-04-04 10:00:00')->addHours($index * 2), $selectedInterest->id, 10 - $index);
        createNewsletterEvent("Week direct {$index}", CarbonImmutable::parse('2026-04-06 18:00:00')->addDays($index), $selectedInterest->id, 10 - $index);
        createNewsletterEvent("Coming soon direct {$index}", CarbonImmutable::parse('2026-04-11 18:00:00')->addDays($index), $selectedInterest->id, 10 - $index);
    }

    createNewsletterEvent('Weekend related', CarbonImmutable::parse('2026-04-05 18:00:00'), $relatedInterest->id);
    createNewsletterEvent('Week related', CarbonImmutable::parse('2026-04-09 18:00:00'), $relatedInterest->id);
    createNewsletterEvent('Coming soon related', CarbonImmutable::parse('2026-04-14 18:00:00'), $relatedInterest->id);

    $run = NewsletterRun::factory()->create();

    (new SendWeeklyNewsletterJob($run->id, $user->id))->handle(app(NewsletterCurator::class));

    expect(NewsletterItem::query()->where('newsletter_run_id', $run->id)->where('user_id', $user->id)->count())->toBe(12);
    expect(
        NewsletterItem::query()
            ->where('newsletter_run_id', $run->id)
            ->where('user_id', $user->id)
            ->distinct('event_id')
            ->count('event_id')
    )->toBe(12);

    expect($run->fresh()->status)->toBe('sent');
    expect($run->fresh()->fallback_level)->toBe(1);
    expect($run->fresh()->bucket_summary)->toBe([
        'weekend' => 4,
        'week' => 4,
        'coming_soon' => 4,
    ]);

    Mail::assertQueued(WeeklyNewsletterMail::class, function (WeeklyNewsletterMail $mail) use ($user): bool {
        expect($mail->hasTo($user->email))->toBeTrue();
        expect($mail->render())->toContain('We think you might be interested in...');
        expect($mail->render())->toContain('COMING SOON');

        return true;
    });
});
