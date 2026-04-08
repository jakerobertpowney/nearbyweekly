<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PostmarkWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $secret = config('services.postmark.webhook_secret');

        if (empty($secret) || $request->header('X-Postmark-Webhook-Token') !== $secret) {
            return response('Unauthorized', 401);
        }

        $payload = $request->json()->all();

        if (($payload['RecordType'] ?? null) === 'SubscriptionChange') {
            $email = $payload['Recipient'] ?? null;
            $suppress = (bool) ($payload['SuppressSending'] ?? false);

            $user = $email ? User::where('email', $email)->first() : null;

            if ($user === null) {
                Log::warning('PostmarkWebhook: no user found for subscription change', ['email' => $email]);
            } else {
                $user->update(['newsletter_enabled' => ! $suppress]);
            }
        }

        return response(null, 204);
    }
}
