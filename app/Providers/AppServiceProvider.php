<?php

namespace App\Providers;

use App\Contracts\PostcodeGeocoder;
use App\Services\Geocoding\PostcodesIoGeocoder;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PostcodeGeocoder::class, PostcodesIoGeocoder::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerHttpMacros();
    }

    /**
     * Register a resilient HTTP macro that wraps every request with standard
     * exponential backoff for transient errors (408, 429, 5xx).
     *
     * Usage in importers:  Http::resilient()->get(...)
     */
    protected function registerHttpMacros(): void
    {
        Http::macro('resilient', fn (): PendingRequest => Http::retry(
            times: 3,
            sleepMilliseconds: fn (int $attempt): int => $attempt * 1_000,
            when: fn (\Throwable $e, \Illuminate\Http\Client\Response $response): bool => in_array(
                $response->status(), [408, 429, 500, 502, 503, 504], true
            ),
            throw: false,
        )->withOptions(['timeout' => 30]));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
