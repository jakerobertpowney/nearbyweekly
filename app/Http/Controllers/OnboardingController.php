<?php

namespace App\Http\Controllers;

use App\Contracts\PostcodeGeocoder;
use App\Http\Requests\StoreOnboardingRequest;
use App\Http\Requests\ValidatePostcodeRequest;
use App\Jobs\SendWeeklyNewsletterJob;
use App\Models\Interest;
use App\Models\NewsletterRun;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    /**
     * Show the onboarding flow.
     */
    public function show(Request $request): Response
    {
        return Inertia::render('Onboarding/Start', [
            'interests' => Interest::grouped(),
            'radiusOptions' => [5, 10, 25, 50, 100],
            'successEmail' => $request->session()->get('successEmail'),
        ]);
    }

    /**
     * Validate that a postcode is real and supported.
     */
    public function validatePostcode(
        ValidatePostcodeRequest $request,
        PostcodeGeocoder $postcodeGeocoder,
    ): JsonResponse {
        $geo = $postcodeGeocoder->geocode((string) $request->validated('postcode'));

        if ($geo === null) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'postcode' => ['Enter a real UK postcode.'],
                ],
            ], 422);
        }

        return response()->json([
            'postcode' => $geo['postcode'],
        ]);
    }

    /**
     * Store onboarding preferences and log the user in.
     */
    public function store(
        StoreOnboardingRequest $request,
        PostcodeGeocoder $postcodeGeocoder,
    ): RedirectResponse {
        $data = $request->validated();
        $geo = $postcodeGeocoder->geocode($data['postcode']);

        if ($geo === null) {
            return back()
                ->withErrors(['postcode' => 'Enter a real UK postcode.'])
                ->withInput();
        }

        $user = User::query()->firstOrNew(['email' => Str::lower($data['email'])]);

        if (! $user->exists) {
            $user->name = Str::headline(Str::before($user->email, '@'));
            $user->password = Str::random(40);
        }

        $user->postcode = $geo['postcode'] ?? Str::upper($data['postcode']);
        $user->latitude = $geo['latitude'] ?? null;
        $user->longitude = $geo['longitude'] ?? null;
        $user->radius_miles = (int) $data['radius_miles'];
        $user->newsletter_enabled = true;

        $user->save();

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        $user->interests()->sync($data['interests']);

        auth()->login($user);

        $request->session()->regenerate();

        $run = NewsletterRun::query()->create([
            'scheduled_for' => now(),
            'sent_at' => null,
            'status' => 'pending',
        ]);

        SendWeeklyNewsletterJob::dispatch($run->id, $user->id);

        return to_route('welcome.show')
            ->with('newsletterRunId', $run->id)
            ->with('successEmail', $user->email);
    }
}
