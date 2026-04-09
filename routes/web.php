<?php

use App\Http\Controllers\Auth\LoginLinkController;
use App\Http\Controllers\BillettoWebhookController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventClickController;
use App\Http\Controllers\NewsletterDemoController;
use App\Http\Controllers\NewsletterPreviewController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PostmarkWebhookController;
use App\Http\Controllers\PreferenceController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::get('register', fn () => redirect()->route('onboarding.show'))->name('register');

Route::get('newsletter/demo', NewsletterDemoController::class)->name('newsletter.demo');

Route::get('start', [OnboardingController::class, 'show'])->name('onboarding.show');
Route::get('start/postcode', [OnboardingController::class, 'validatePostcode'])->name('onboarding.postcode.validate');
Route::post('start', [OnboardingController::class, 'store'])->name('onboarding.store');

Route::get('welcome', [WelcomeController::class, 'show'])->name('welcome.show');
Route::get('welcome/status', [WelcomeController::class, 'status'])->name('welcome.status');

Route::middleware('guest')->group(function () {
    Route::post('login/link', [LoginLinkController::class, 'store'])
        ->middleware('throttle:login-links')
        ->name('login-links.store');
});

Route::get('login/link/{loginLink}/{token}', [LoginLinkController::class, 'consume'])
    ->middleware('signed')
    ->name('login-links.consume');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('preferences', [PreferenceController::class, 'edit'])->name('preferences.edit');
    Route::put('preferences', [PreferenceController::class, 'update'])->name('preferences.update');
    Route::get('newsletter/preview', NewsletterPreviewController::class)->name('newsletter.preview');
});

Route::get('events/{event}/go', EventClickController::class)->name('events.go');

Route::post('webhooks/billetto', BillettoWebhookController::class)->name('webhooks.billetto');
Route::post('webhooks/postmark', PostmarkWebhookController::class)->name('webhooks.postmark');

require __DIR__.'/settings.php';
