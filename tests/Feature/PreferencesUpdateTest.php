<?php

use App\Models\Interest;
use App\Models\User;

test('authenticated users can update their preferences without a password', function () {
    $currentInterest = Interest::factory()->create([
        'name' => 'Comedy',
        'slug' => 'comedy',
    ]);
    $nextInterest = Interest::factory()->create([
        'name' => 'Theatre',
        'slug' => 'theatre',
    ]);

    $user = User::factory()->create();
    $user->interests()->sync([$currentInterest->id]);

    $response = $this->actingAs($user)->put(route('preferences.update'), [
        'postcode' => 'M1 1AE',
        'radius_miles' => 10,
        'newsletter_enabled' => false,
        'interests' => [$nextInterest->id],
    ]);

    $response->assertRedirect();

    expect($user->refresh()->postcode)->toBe('M1 1AE');
    expect($user->radius_miles)->toBe(10);
    expect($user->newsletter_enabled)->toBeFalse();
    expect($user->interests()->pluck('interests.id')->all())->toBe([$nextInterest->id]);
});
