<?php

namespace App\Services\Importers;

use App\Contracts\EventImporter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TicketmasterImporter implements EventImporter
{
    private ?int $lastRateLimitAvailable = null;

    private ?int $lastRateLimitTotal = null;

    private ?Carbon $lastRateLimitResetAt = null;

    private bool $wasThrottled = false;
    /**
     * Get the importer name.
     */
    public function name(): string
    {
        return 'ticketmaster';
    }

    /**
     * Determine whether the importer has the required credentials.
     */
    public function isConfigured(): bool
    {
        return filled(config('services.ticketmaster.api_key'))
            && filled(config('services.ticketmaster.base_url'));
    }

    /**
     * Return the rate limit state recorded during the last fetch() call.
     *
     * @return array{remaining: int|null, total: int|null, reset_at: \Illuminate\Support\Carbon|null, was_throttled: bool}
     */
    public function getRateLimitState(): array
    {
        return [
            'remaining'    => $this->lastRateLimitAvailable,
            'total'        => $this->lastRateLimitTotal,
            'reset_at'     => $this->lastRateLimitResetAt,
            'was_throttled' => $this->wasThrottled,
        ];
    }

    /**
     * Fetch normalized events from Ticketmaster Discovery.
     *
     * @param  array{limit?: int|null}  $options
     * @return list<array<string, mixed>>
     */
    public function fetch(array $options = []): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $response = Http::baseUrl(config('services.ticketmaster.base_url'))
            ->acceptJson()
            ->get('/events.json', [
                'apikey' => config('services.ticketmaster.api_key'),
                'countryCode' => 'GB',
                'sort' => 'date,asc',
                'size' => min($options['limit'] ?? 50, 200),
                'startDateTime' => Carbon::now()->toIso8601String(),
            ]);

        $rateLimitAvailable = $response->header('Rate-Limit-Available');
        $rateLimitTotal     = $response->header('Rate-Limit');
        $rateLimitOver      = $response->header('Rate-Limit-Over');
        $rateLimitReset     = $response->header('Rate-Limit-Reset');

        if (filled($rateLimitAvailable)) {
            $this->lastRateLimitAvailable = (int) $rateLimitAvailable;
        }
        if (filled($rateLimitTotal)) {
            $this->lastRateLimitTotal = (int) $rateLimitTotal;
        }
        if (filled($rateLimitReset)) {
            $this->lastRateLimitResetAt = Carbon::createFromTimestamp((int) $rateLimitReset);
        }

        Log::info('TicketmasterImporter: rate limit.', [
            'available' => $this->lastRateLimitAvailable,
            'total'     => $this->lastRateLimitTotal,
        ]);

        if ($rateLimitOver === '1') {
            Log::warning('TicketmasterImporter: daily API quota exceeded.', [
                'reset_at' => $this->lastRateLimitResetAt?->toIso8601String(),
            ]);
            $this->wasThrottled = true;

            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        $events = $response->json('_embedded.events', []);

        if (! is_array($events)) {
            return [];
        }

        return collect($events)->map(function (array $event): array {
            return [
                'source' => $this->name(),
                'external_id' => data_get($event, 'id'),
                'title' => data_get($event, 'name'),
                'slug' => Str::slug((string) data_get($event, 'name')),
                'description' => data_get($event, 'info'),
                'category' => strtolower((string) data_get($event, 'classifications.0.segment.name', '')),
                'venue_name' => data_get($event, '_embedded.venues.0.name'),
                'address_line' => data_get($event, '_embedded.venues.0.address.line1'),
                'city' => data_get($event, '_embedded.venues.0.city.name'),
                'postcode' => data_get($event, '_embedded.venues.0.postalCode'),
                'latitude' => data_get($event, '_embedded.venues.0.location.latitude') !== null ? (float) data_get($event, '_embedded.venues.0.location.latitude') : null,
                'longitude' => data_get($event, '_embedded.venues.0.location.longitude') !== null ? (float) data_get($event, '_embedded.venues.0.location.longitude') : null,
                'starts_at' => data_get($event, 'dates.start.dateTime') ?? data_get($event, 'dates.start.localDate'),
                'ends_at' => data_get($event, 'dates.end.dateTime'),
                'url' => data_get($event, 'url'),
                'image_url' => data_get($event, 'images.0.url'),
                'score_manual' => 0,
                'raw_payload' => $event,
            ];
        })->filter(fn (array $event): bool => filled($event['title']) && filled($event['starts_at']) && filled($event['url']))->values()->all();
    }
}
