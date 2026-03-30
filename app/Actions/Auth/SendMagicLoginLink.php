<?php

namespace App\Actions\Auth;

use App\Models\LoginLink;
use App\Models\User;
use App\Notifications\SendLoginLinkNotification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class SendMagicLoginLink
{
    private const EXPIRATION_MINUTES = 15;

    /**
     * Issue and email a fresh one-time login link for the user.
     */
    public function handle(User $user): LoginLink
    {
        $user->loginLinks()->delete();

        $plainTextToken = Str::random(64);

        $loginLink = $user->loginLinks()->create([
            'token' => hash('sha256', $plainTextToken),
            'expires_at' => now()->addMinutes(self::EXPIRATION_MINUTES),
        ]);

        $user->notify(new SendLoginLinkNotification(
            URL::temporarySignedRoute('login-links.consume', $loginLink->expires_at, [
                'loginLink' => $loginLink,
                'token' => $plainTextToken,
            ]),
            $loginLink->expires_at,
        ));

        return $loginLink;
    }
}
