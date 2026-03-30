<?php

namespace App\Http\Controllers;

use App\Models\NewsletterRun;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the authenticated dashboard.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $formatRun = fn (NewsletterRun $run): array => [
            'id' => $run->id,
            'sent_at' => $run->sent_at?->toIso8601String(),
            'events' => $run->items->map(fn ($item) => [
                'id' => $item->event->id,
                'title' => $item->event->title,
                'category' => $item->event->category,
                'city' => $item->event->city,
                'venue_name' => $item->event->venue_name,
                'starts_at' => $item->event->starts_at?->toIso8601String(),
                'url' => $item->event->url,
                'score' => $item->ranking_score,
                'image_url' => $item->event->image_url,
            ])->values()->all(),
        ];

        $runs = NewsletterRun::query()
            ->where('status', 'sent')
            ->whereHas('items', fn ($q) => $q->where('user_id', $user->id))
            ->with([
                'items' => fn ($q) => $q
                    ->where('user_id', $user->id)
                    ->with('event')
                    ->orderByDesc('ranking_score'),
            ])
            ->orderByDesc('sent_at')
            ->get();

        $latestRun = $runs->first();
        $archiveRuns = $runs->skip(1)->values();

        return Inertia::render('Dashboard', [
            'latestRun' => $latestRun ? $formatRun($latestRun) : null,
            'archiveRuns' => $archiveRuns->map($formatRun)->values()->all(),
            'preferencesComplete' => filled($user->postcode) && $user->interests()->exists(),
        ]);
    }
}
