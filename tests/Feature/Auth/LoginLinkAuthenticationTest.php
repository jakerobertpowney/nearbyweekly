<?php

use App\Models\LoginLink;
use App\Models\User;
use App\Notifications\SendLoginLinkNotification;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

test('users can request a sign-in link', function () {
    Notification::fake();

    $user = User::factory()->create();

    $response = $this->from(route('login'))->post(route('login-links.store'), [
        'email' => $user->email,
    ]);

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('status', 'If we found an account for that email, we sent you a secure sign-in link.');

    expect(LoginLink::query()->whereBelongsTo($user)->count())->toBe(1);

    Notification::assertSentTo($user, SendLoginLinkNotification::class);
});

test('requesting a sign-in link for an unknown email returns the same response', function () {
    Notification::fake();

    $response = $this->from(route('login'))->post(route('login-links.store'), [
        'email' => 'missing@example.com',
    ]);

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('status', 'If we found an account for that email, we sent you a secure sign-in link.');

    expect(LoginLink::query()->count())->toBe(0);

    Notification::assertNothingSent();
});

test('users can authenticate with a valid sign-in link', function () {
    Notification::fake();

    $user = User::factory()->create();
    $url = null;

    $this->post(route('login-links.store'), [
        'email' => $user->email,
    ]);

    Notification::assertSentTo($user, SendLoginLinkNotification::class, function (SendLoginLinkNotification $notification) use (&$url) {
        $url = $notification->url;

        return true;
    });

    $response = $this->get($url);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', absolute: false));
    expect(LoginLink::query()->sole()->fresh()->consumed_at)->not->toBeNull();
});

test('sign-in links can only be used once', function () {
    Notification::fake();

    $user = User::factory()->create();
    $url = null;

    $this->post(route('login-links.store'), [
        'email' => $user->email,
    ]);

    Notification::assertSentTo($user, SendLoginLinkNotification::class, function (SendLoginLinkNotification $notification) use (&$url) {
        $url = $notification->url;

        return true;
    });

    $this->get($url)->assertRedirect(route('dashboard', absolute: false));
    $this->get($url)->assertForbidden();
});

test('users with two factor enabled are redirected to the challenge after using a sign-in link', function () {
    $this->skipUnlessFortifyFeature(Features::twoFactorAuthentication());

    Notification::fake();

    $user = User::factory()->withTwoFactor()->create();
    $url = null;

    $this->post(route('login-links.store'), [
        'email' => $user->email,
    ]);

    Notification::assertSentTo($user, SendLoginLinkNotification::class, function (SendLoginLinkNotification $notification) use (&$url) {
        $url = $notification->url;

        return true;
    });

    $response = $this->get($url);

    $response->assertRedirect(route('two-factor.login'));
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

test('sign-in link requests are rate limited', function () {
    Notification::fake();

    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->post(route('login-links.store'), [
            'email' => $user->email,
        ])->assertRedirect(route('login'));
    }

    $response = $this->post(route('login-links.store'), [
        'email' => $user->email,
    ]);

    $response->assertTooManyRequests();
});
