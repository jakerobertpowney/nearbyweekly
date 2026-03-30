<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\SendMagicLoginLink;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RequestLoginLinkRequest;
use App\Models\LoginLink;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Fortify\Features;

class LoginLinkController extends Controller
{
    /**
     * Email a one-time sign-in link to the user.
     */
    public function store(RequestLoginLinkRequest $request, SendMagicLoginLink $sendMagicLoginLink): RedirectResponse
    {
        $email = Str::lower($request->string('email')->trim()->value());

        $user = User::query()->where('email', $email)->first();

        if ($user) {
            $sendMagicLoginLink->handle($user);
        }

        return to_route('login')->with(
            'status',
            'If we found an account for that email, we sent you a secure sign-in link.',
        );
    }

    /**
     * Authenticate the user from a one-time sign-in link.
     */
    public function consume(Request $request, LoginLink $loginLink, string $token): RedirectResponse
    {
        if ($loginLink->isConsumed() || $loginLink->hasExpired() || ! hash_equals($loginLink->token, hash('sha256', $token))) {
            abort(403);
        }

        $user = $loginLink->user;

        if ($user === null) {
            abort(403);
        }

        $loginLink->forceFill([
            'consumed_at' => now(),
        ])->save();

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        if (Features::canManageTwoFactorAuthentication() && method_exists($user, 'hasEnabledTwoFactorAuthentication') && $user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => false,
            ]);

            return redirect()->route('two-factor.login');
        }

        Auth::guard(config('fortify.guard'))->login($user);
        $request->session()->regenerate();

        return redirect()->intended(config('fortify.home'));
    }
}
