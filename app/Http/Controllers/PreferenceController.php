<?php

namespace App\Http\Controllers;

use App\Contracts\PostcodeGeocoder;
use App\Http\Requests\UpdatePreferenceRequest;
use App\Jobs\GeocodeUserJob;
use App\Models\Interest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PreferenceController extends Controller
{
    /**
     * Show the preference management screen.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Preferences/Edit', [
            'interests' => Interest::grouped(),
            'radiusOptions' => [5, 10, 25, 50, 100],
            'preferences' => [
                'postcode' => $request->user()?->postcode,
                'radius_miles' => $request->user()?->radius_miles,
                'newsletter_enabled' => $request->user()?->newsletter_enabled,
                'interest_ids' => $request->user()?->interests()->pluck('interests.id'),
            ],
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the authenticated user's preferences.
     */
    public function update(UpdatePreferenceRequest $request, PostcodeGeocoder $postcodeGeocoder): RedirectResponse
    {
        $data = $request->validated();
        $geo = $postcodeGeocoder->geocode($data['postcode']);

        $request->user()->update([
            'postcode' => $geo['postcode'] ?? strtoupper($data['postcode']),
            'latitude' => $geo['latitude'] ?? null,
            'longitude' => $geo['longitude'] ?? null,
            'radius_miles' => (int) $data['radius_miles'],
            'newsletter_enabled' => (bool) $data['newsletter_enabled'],
        ]);

        if ($geo === null) {
            GeocodeUserJob::dispatch($request->user()->id);
        }

        $request->user()->interests()->sync($data['interests']);

        return back()->with('status', 'Preferences updated.');
    }

    /**
     * Disable the newsletter for the user via a signed unsubscribe link.
     */
    public function unsubscribe(Request $request, User $user): RedirectResponse
    {
        $user->update([
            'newsletter_enabled' => false,
        ]);

        return redirect('/')->with('status', 'You have been unsubscribed from the weekly newsletter.');
    }
}
