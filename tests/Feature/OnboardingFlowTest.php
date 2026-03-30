<?php

use App\Models\Interest;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

test('the onboarding page is displayed with interest and radius options', function () {
    Interest::factory()->count(3)->create();

    $this->get(route('onboarding.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Onboarding/Start')
            ->has('interests', 3)
            ->where('radiusOptions', [5, 10, 25, 50, 100]),
        );
});

test('subscribers can complete onboarding before receiving a magic link', function () {
    config(['services.postcodes_io.base_url' => 'https://postcodes.test']);
    Http::fake([
        'postcodes.test/*' => Http::response([
            'result' => [
                'postcode' => 'SW1A 1AA',
                'latitude' => 51.501009,
                'longitude' => -0.141588,
            ],
        ]),
    ]);

    $interest = Interest::factory()->create([
        'name' => 'Comedy',
        'slug' => 'comedy',
    ]);

    $response = $this->post(route('onboarding.store'), [
        'postcode' => 'SW1A 1AA',
        'radius_miles' => 25,
        'interests' => [$interest->id],
        'email' => 'subscriber@example.com',
    ]);

    $user = User::query()->where('email', 'subscriber@example.com')->firstOrFail();

    $response->assertRedirect(route('welcome.show'));
    expect($user->postcode)->toBe('SW1A 1AA');
    expect($user->newsletter_enabled)->toBeTrue();
    expect($user->latitude)->toBe(51.501009);
    expect($user->longitude)->toBe(-0.141588);
    expect($user->interests()->pluck('interests.id')->all())->toBe([$interest->id]);
    $this->assertAuthenticatedAs($user);
});

test('onboarding rejects postcodes that cannot be verified', function () {
    config(['services.postcodes_io.base_url' => 'https://postcodes.test']);
    Http::fake([
        'postcodes.test/*' => Http::response(['error' => 'Invalid postcode'], 404),
    ]);

    $interest = Interest::factory()->create([
        'name' => 'Comedy',
        'slug' => 'comedy',
    ]);

    $response = $this->from(route('onboarding.show'))->post(route('onboarding.store'), [
        'postcode' => 'ZZ1 1ZZ',
        'radius_miles' => 25,
        'interests' => [$interest->id],
        'email' => 'subscriber@example.com',
    ]);

    $response->assertRedirect(route('onboarding.show'));
    $response->assertSessionHasErrors([
        'postcode' => 'Enter a real UK postcode.',
    ]);
    expect(User::query()->where('email', 'subscriber@example.com')->exists())->toBeFalse();
});

test('email capture is presented on the final onboarding step', function () {
    $component = file_get_contents(resource_path('js/pages/Onboarding/Start.vue'));

    expect($component)->not->toBeFalse();
    expect(strpos($component, 'currentStep === 4'))->toBeLessThan(strpos($component, 'type="email"'));
});

test('interests are presented before location in the onboarding flow', function () {
    $component = file_get_contents(resource_path('js/pages/Onboarding/Start.vue'));

    expect($component)->not->toBeFalse();
    expect(strpos($component, "label: 'Interests'"))->toBeLessThan(strpos($component, "label: 'Location'"));
});
