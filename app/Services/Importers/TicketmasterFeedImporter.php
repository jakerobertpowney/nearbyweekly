<?php

namespace App\Services\Importers;

use App\Contracts\EventImporter;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TicketmasterFeedImporter implements EventImporter
{
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
        return filled(config('services.ticketmaster.feed_url'));
    }

    /**
     * Fetch normalized events from the Ticketmaster bulk feed (gzipped JSONL).
     *
     * @param  array{limit?: int|null}  $options
     * @return list<array<string, mixed>>
     */
    public function fetch(array $options = []): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $tempDir = storage_path('app/temp');

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $tempPath = $tempDir.'/ticketmaster_feed_'.now()->format('YmdHis').'.json.gz';

        try {
            $feedUrl = config('services.ticketmaster.feed_url').'?'.http_build_query([
                'apikey' => config('services.ticketmaster.api_key'),
                'countryCode' => 'GB',
            ]);

            $response = Http::sink($tempPath)->get($feedUrl);

            if ($response->status() !== 200) {
                throw new \RuntimeException(
                    "TicketmasterFeedImporter: HTTP {$response->status()} downloading feed."
                );
            }

            $fileSize = @filesize($tempPath);

            if ($fileSize === false || $fileSize === 0) {
                throw new \RuntimeException('TicketmasterFeedImporter: downloaded feed file is empty.');
            }

            Log::info('TicketmasterFeedImporter: feed downloaded.', [
                'size_mb' => round($fileSize / 1024 / 1024, 2),
            ]);

            return $this->parseGzippedJsonl($tempPath);
        } finally {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }
    }

    /**
     * Stream-parse a gzipped pretty-printed JSON feed file.
     *
     * The feed format is:
     *   {"events":[ {
     *     "eventId" : "...",
     *     ...
     *   }, {
     *     "eventId" : "...",
     *     ...
     *   }]}
     *
     * We accumulate lines per event and decode each one individually to avoid
     * loading the entire (400MB+) decompressed file into memory at once.
     *
     * @return list<array<string, mixed>>
     */
    private function parseGzippedJsonl(string $path): array
    {
        $handle = gzopen($path, 'r');

        if ($handle === false) {
            Log::error('TicketmasterFeedImporter: could not open gzipped feed file.');

            return [];
        }

        $events = [];
        $now = now();
        $buffer = '';

        try {
            while (! gzeof($handle)) {
                $line = gzgets($handle, 65536);

                if ($line === false) {
                    break;
                }

                $trimmed = trim($line);

                // Opening line — begin accumulating the first event.
                if (str_ends_with($trimmed, '[ {')) {
                    $buffer = '{';

                    continue;
                }

                // Event boundary — decode accumulated buffer, then start next event.
                if ($trimmed === '}, {') {
                    $this->processEventBuffer($buffer.'}', $events, $now);
                    $buffer = '{';

                    continue;
                }

                // Closing line — decode the final event.
                if ($trimmed === '}]}' || $trimmed === '}]') {
                    $this->processEventBuffer($buffer.'}', $events, $now);
                    break;
                }

                if ($buffer !== '') {
                    $buffer .= "\n".$line;
                }
            }
        } finally {
            gzclose($handle);
        }

        return $events;
    }

    /**
     * Decode a single accumulated event JSON string and append to $events if valid.
     *
     * @param  list<array<string, mixed>>  $events
     */
    private function processEventBuffer(string $json, array &$events, CarbonInterface $now): void
    {
        $data = json_decode($json, true);

        if (! is_array($data)) {
            return;
        }

        // Skip events not in GB
        if (($data['venue']['venueCountryCode'] ?? null) !== 'GB') {
            return;
        }

        $normalised = $this->normalise($data);

        if (! filled($normalised['title']) || ! filled($normalised['starts_at']) || ! filled($normalised['url'])) {
            return;
        }

        // Skip past events
        if (Carbon::parse($normalised['starts_at'])->lt($now)) {
            return;
        }

        $events[] = $normalised;
    }

    /**
     * Normalise a raw Ticketmaster feed record.
     *
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function normalise(array $event): array
    {
        $title = (string) ($event['eventName'] ?? '');
        $venue = $event['venue'] ?? [];
        $image = data_get($event, 'images.0.image.url')
            ?? data_get($event, 'attractions.0.attraction.images.0.image.url')
            ?? $event['eventImageUrl']
            ?? null;

        return [
            'source' => $this->name(),
            'external_id' => $event['eventId'] ?? null,
            'title' => $title,
            'slug' => Str::slug($title),
            'description' => $event['eventInfo'] ?? $event['eventNotes'] ?? null,
            'category' => strtolower((string) ($event['classificationSegment'] ?? '')),
            'venue_name' => $venue['venueName'] ?? null,
            'address_line' => $venue['venueStreet'] ?? null,
            'city' => $venue['venueCity'] ?? null,
            'postcode' => $venue['venueZipCode'] ?? null,
            'latitude' => isset($venue['venueLatitude']) ? (float) $venue['venueLatitude'] : null,
            'longitude' => isset($venue['venueLongitude']) ? (float) $venue['venueLongitude'] : null,
            'starts_at' => $event['eventStartDateTime'] ?? $event['eventStartLocalDate'] ?? null,
            'ends_at' => $event['eventEndDateTime'] ?? null,
            'url' => $event['primaryEventUrl'] ?? null,
            'image_url' => $image,
            'score_manual' => 0,
            'raw_payload' => $event,
        ];
    }
}
