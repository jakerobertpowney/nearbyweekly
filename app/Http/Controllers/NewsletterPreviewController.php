<?php

namespace App\Http\Controllers;

use App\Exceptions\NoMatchesException;
use App\Mail\WeeklyNewsletterMail;
use App\Services\Events\NewsletterCurator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class NewsletterPreviewController extends Controller
{
    /**
     * Preview the weekly newsletter for the authenticated user.
     */
    public function __invoke(Request $request, NewsletterCurator $curator): WeeklyNewsletterMail
    {
        try {
            $matches = $curator->curate($request->user())['buckets'];
        } catch (NoMatchesException) {
            $matches = [];
        }

        return new WeeklyNewsletterMail(
            $request->user(),
            $matches,
            URL::temporarySignedRoute('preferences.unsubscribe', now()->addDays(7), [
                'user' => $request->user(),
            ]),
        );
    }
}
