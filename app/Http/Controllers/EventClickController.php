<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventClick;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EventClickController extends Controller
{
    /**
     * Record a click and redirect to the event's external URL.
     */
    public function __invoke(Request $request, Event $event): RedirectResponse
    {
        EventClick::query()->create([
            'event_id' => $event->id,
            'user_id' => $request->user()?->id,
            'clicked_at' => now(),
            'referrer' => $request->header('Referer'),
        ]);

        return redirect()->away($event->url);
    }
}
