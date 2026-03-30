<?php

use App\Models\User;
use Illuminate\Support\Facades\URL;

test('users can unsubscribe from the newsletter via a signed link', function () {
    $user = User::factory()->create([
        'newsletter_enabled' => true,
    ]);

    $url = URL::temporarySignedRoute('preferences.unsubscribe', now()->addDay(), [
        'user' => $user,
    ]);

    $this->get($url)
        ->assertRedirect('/');

    expect($user->fresh()->newsletter_enabled)->toBeFalse();
});
