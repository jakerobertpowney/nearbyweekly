<?php

namespace App\Http\Controllers;

use App\Exceptions\NoMatchesException;
use App\Mail\WeeklyNewsletterMail;
use App\Services\Events\NewsletterCurator;
use Illuminate\Http\Request;

class NewsletterPreviewController extends Controller
{
    /**
     * Preview the weekly newsletter for the authenticated user.
     */
    public function __invoke(Request $request, NewsletterCurator $curator): WeeklyNewsletterMail
    {
        try {
            $result = $curator->curate($request->user());
            $buckets = $result['buckets'];
            $context = $result['newsletter_context'] ?? [];
            $seasonal = $result['seasonal_picks'] ?? [];
        } catch (NoMatchesException) {
            $buckets = [];
            $context = [];
            $seasonal = [];
        }

        return new WeeklyNewsletterMail(
            user: $request->user(),
            matches: $buckets,
            newsletterContext: $context,
            seasonalPicks: $seasonal,
        );
    }
}
