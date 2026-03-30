<?php

namespace App\Jobs;

use App\Contracts\PostcodeGeocoder;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GeocodeUserJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $backoff = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $userId) {}

    /**
     * Execute the job.
     */
    public function handle(PostcodeGeocoder $postcodeGeocoder): void
    {
        $user = User::query()->find($this->userId);

        if ($user === null || empty($user->postcode)) {
            return;
        }

        $geo = $postcodeGeocoder->geocode($user->postcode);

        if ($geo === null) {
            $this->fail(new \RuntimeException("Geocoding failed for postcode [{$user->postcode}]"));

            return;
        }

        $user->update([
            'postcode' => $geo['postcode'],
            'latitude' => $geo['latitude'],
            'longitude' => $geo['longitude'],
        ]);
    }

    /**
     * Handle a job failure after all retries are exhausted.
     */
    public function failed(\Throwable $exception): void
    {
        $user = User::query()->find($this->userId);

        Log::warning('GeocodeUserJob: failed to geocode postcode after all retries.', [
            'user_id' => $this->userId,
            'postcode' => $user?->postcode,
        ]);
    }
}
