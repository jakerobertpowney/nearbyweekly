<?php

namespace App\Services\Importers;

use App\Contracts\EventImporter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DataThistleImporter implements EventImporter
{
    private ?int $lastRateLimitRemaining = null;

    private ?int $lastRateLimitTotal = null;
    /**
     * Get the importer name.
     */
    public function name(): string
    {
        return 'datathistle';
    }

    /**
     * Determine whether the importer has the required credentials.
     */
    public function isConfigured(): bool
    {
        return filled(config('services.datathistle.access_token'))
            && filled(config('services.datathistle.base_url'));
    }

    /**
     * Return the rate limit state recorded during the last fetch() call.
     *
     * @return array{remaining: int|null, total: int|null, reset_at: null, was_throttled: bool}
     */
    public function getRateLimitState(): array
    {
        return [
            'remaining'    => $this->lastRateLimitRemaining,
            'total'        => $this->lastRateLimitTotal,
            'reset_at'     => null,
            'was_throttled' => false,
        ];
    }

    /**
     * Fetch normalized events from DataThistle, yielding one event at a time
     * so each page is processed and discarded before the next is fetched.
     *
     * @param  array{limit?: int|null, since?: string|null}  $options
     * @return iterable<array<string, mixed>>
     */
    public function fetch(array $options = []): iterable
    {
        if (! $this->isConfigured()) {
            return [];
        }

        return $this->generate($options);
    }

    /**
     * Generator that paginates through all DataThistle pages, yielding normalised events.
     *
     * @param  array{limit?: int|null, since?: string|null}  $options
     * @return \Generator<int, array<string, mixed>>
     */
    private function generate(array $options): \Generator
    {
        // Each page from DataThistle can be 30-40 MB of JSON. Raise the ceiling
        // so the decoded PHP array fits; the response object itself is freed
        // before we start yielding (see below).
        ini_set('memory_limit', '512M');

        $params = [
            'limit' => $options['limit'] ?? 50,
            'min_date' => now()->addDay()->startOfDay()->toIso8601String(),
        ];

        if (filled($options['since'] ?? null)) {
            $params['since'] = $options['since'];
        }

        $nextUrl = null;

        do {
            if ($nextUrl !== null) {
                $response = Http::acceptJson()
                    ->withToken(config('services.datathistle.access_token'))
                    ->get($nextUrl);
            } else {
                $response = Http::baseUrl(config('services.datathistle.base_url'))
                    ->acceptJson()
                    ->withToken(config('services.datathistle.access_token'))
                    ->get('/events', $params);
            }

            if (! $response->successful()) {
                break;
            }

            // ── Extract everything from the response before any yield ──────────
            //
            // yield from suspends the generator at each event, meaning any
            // variable still in scope stays alive between suspensions.  If we
            // hold $response across a yield the full 30-40 MB body string sits
            // in memory for the entire page.  Extract what we need, then free
            // the response object before we start yielding.
            //
            $rateLimitRemaining = $response->header('X-RateLimit-Remaining');
            $rateLimitTotal     = $response->header('X-RateLimit-Limit');
            $nextUrl            = $response->header('X-Next') ?: null;
            $pageData           = $response->json();

            unset($response);

            // ── Rate limit tracking ───────────────────────────────────────────
            if ($rateLimitRemaining !== null) {
                $this->lastRateLimitRemaining = (int) $rateLimitRemaining;
                Log::info('DataThistle rate limit.', [
                    'remaining' => $this->lastRateLimitRemaining,
                    'total'     => $rateLimitTotal,
                ]);
            }
            if ($rateLimitTotal !== null) {
                $this->lastRateLimitTotal = (int) $rateLimitTotal;
            }

            $threshold = (int) config('services.datathistle.rate_limit_threshold', 10);
            $stopEarly = $rateLimitRemaining !== null && (int) $rateLimitRemaining < $threshold;

            if ($stopEarly) {
                Log::warning('DataThistle rate limit nearly exhausted, stopping pagination early.', [
                    'remaining' => (int) $rateLimitRemaining,
                    'threshold' => $threshold,
                ]);
            }

            yield from $this->normalisePage($pageData);

            // Free the decoded array once all events from this page have been yielded.
            unset($pageData);

            if ($stopEarly) {
                break;
            }

        } while ($nextUrl !== null);
    }

    /**
     * Normalise a single page of raw events, yielding each valid event.
     *
     * @return iterable<array<string, mixed>>
     */
    private function normalisePage(mixed $payload): iterable
    {
        $raw = match (true) {
            is_array($payload) && array_is_list($payload) => $payload,
            default => data_get($payload, 'events', data_get($payload, 'results', data_get($payload, 'data.events', []))),
        };

        foreach ((array) $raw as $event) {
            $normalised = $this->normaliseEvent($event);

            if (filled($normalised['title']) && filled($normalised['starts_at']) && filled($normalised['url'])) {
                yield $normalised;
            }
        }
    }

    /**
     * Normalise a single raw event array.
     *
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function normaliseEvent(array $event): array
    {
        $title = (string) (data_get($event, 'title') ?? data_get($event, 'name'));
        $schedule = collect(data_get($event, 'schedules', []))->first() ?? [];
        $place = is_array(data_get($schedule, 'place')) ? data_get($schedule, 'place') : [];
        $performance = collect(data_get($schedule, 'performances', []))->first() ?? [];
        $bookingLink = collect(data_get($performance, 'links', []))
            ->firstWhere('type', 'booking');
        $category = data_get($event, 'category.slug')
            ?? data_get($event, 'category')
            ?? data_get($schedule, 'tags.0')
            ?? data_get($event, 'tags.0')
            ?? '';

        return [
            'source' => $this->name(),
            'external_id' => (string) (data_get($event, 'id') ?? data_get($event, 'event_id')),
            'title' => $title,
            'slug' => Str::slug($title),
            'description' => data_get($event, 'description') ?? data_get($event, 'summary'),
            'category' => Str::slug(strtolower((string) $category)),
            'venue_name' => data_get($event, 'venue.name') ?? data_get($place, 'name'),
            'address_line' => data_get($event, 'venue.address.line_1') ?? data_get($event, 'venue.address') ?? data_get($place, 'address'),
            'city' => data_get($event, 'venue.address.city') ?? data_get($event, 'city') ?? data_get($place, 'town'),
            'postcode' => data_get($event, 'venue.address.postcode') ?? data_get($event, 'postcode') ?? data_get($place, 'postal_code'),
            'latitude' => data_get($event, 'venue.latitude') !== null
                ? (float) data_get($event, 'venue.latitude')
                : (data_get($event, 'location.latitude') !== null
                    ? (float) data_get($event, 'location.latitude')
                    : (data_get($place, 'lat') !== null ? (float) data_get($place, 'lat') : null)),
            'longitude' => data_get($event, 'venue.longitude') !== null
                ? (float) data_get($event, 'venue.longitude')
                : (data_get($event, 'location.longitude') !== null
                    ? (float) data_get($event, 'location.longitude')
                    : ((data_get($place, 'lon') ?? data_get($place, 'lng')) !== null ? (float) (data_get($place, 'lon') ?? data_get($place, 'lng')) : null)),
            'starts_at' => data_get($event, 'start_datetime') ?? data_get($event, 'starts_at') ?? data_get($event, 'start_date') ?? data_get($performance, 'ts') ?? data_get($schedule, 'start_ts'),
            'ends_at' => data_get($event, 'end_datetime') ?? data_get($event, 'ends_at') ?? data_get($schedule, 'end_ts'),
            'url' => data_get($event, 'url') ?? data_get($event, 'tickets_url') ?? data_get($bookingLink, 'url'),
            'image_url' => data_get($event, 'image_url') ?? data_get($event, 'images.0.url') ?? data_get($place, 'images.0.url'),
            'score_manual' => 0,
            'raw_payload' => $event,
        ];
    }
}
