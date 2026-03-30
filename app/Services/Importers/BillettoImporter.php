<?php

namespace App\Services\Importers;

use App\Contracts\EventImporter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BillettoImporter implements EventImporter
{
    private const BASE_URL = 'https://billetto.co.uk/api/v3/public/events';

    private const MAX_PAGES = 50;

    private ?int $lastRateLimitRemaining = null;

    private ?int $lastRateLimitTotal = null;

    private bool $wasThrottled = false;

    public function name(): string
    {
        return 'billetto';
    }

    public function isConfigured(): bool
    {
        return filled(config('services.billetto.api_key'))
            && filled(config('services.billetto.api_secret'));
    }

    public function fetch(array $options = []): array
    {
        $events = [];
        $pages = 0;
        $nextUrl = self::BASE_URL;
        $params = [
            'limit' => 100,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(90)->toDateString(),
        ];
        $consecutiveThrottles = 0;

        while ($nextUrl && $pages < self::MAX_PAGES) {
            $request = Http::withHeaders([
                'Api-Keypair' => config('services.billetto.api_key')
                    .':'.config('services.billetto.api_secret'),
            ]);

            $response = $pages === 0
                ? $request->get($nextUrl, $params)
                : $request->get($nextUrl);

            // ── 429 handling ─────────────────────────────────────────────────
            if ($response->status() === 429) {
                $retryAfterMs = (int) $response->header('X-Ratelimit-Retry-After', 0);

                if ($retryAfterMs > 0 && $retryAfterMs <= 30_000 && $consecutiveThrottles === 0) {
                    $consecutiveThrottles++;
                    usleep($retryAfterMs * 1_000);

                    continue;
                }

                Log::warning('BillettoImporter: rate limit exceeded, stopping early.', [
                    'retry_after_ms' => $retryAfterMs,
                    'pages_fetched' => $pages,
                ]);
                $this->wasThrottled = true;
                break;
            }

            $consecutiveThrottles = 0;

            if (! $response->successful()) {
                Log::warning('BillettoImporter: request failed', [
                    'status' => $response->status(),
                    'url'    => $nextUrl,
                ]);
                break;
            }

            // ── Rate limit header tracking ────────────────────────────────────
            $remaining = $response->header('X-Ratelimit-Remaining');
            $cost      = $response->header('X-Ratelimit-Cost');
            $total     = $response->header('X-Ratelimit-Limit');

            if ($remaining !== null) {
                $this->lastRateLimitRemaining = (int) $remaining;
            }
            if ($total !== null) {
                $this->lastRateLimitTotal = (int) $total;
            }

            $body = $response->json();

            foreach ($body['data'] ?? [] as $raw) {
                $normalised = $this->normalise($raw);
                if ($normalised !== null) {
                    $events[] = $normalised;
                }
            }

            // ── Proactive stop: fewer than 2 pages of budget left ─────────────
            if ($remaining !== null && $cost !== null) {
                $remainingInt = (int) $remaining;
                $costInt      = max(1, (int) $cost);

                if ($remainingInt < $costInt * 2) {
                    Log::warning('BillettoImporter: rate limit budget nearly exhausted, stopping early.', [
                        'remaining'    => $remainingInt,
                        'cost'         => $costInt,
                        'pages_fetched' => $pages + 1,
                    ]);
                    $this->wasThrottled = true;
                    break;
                }
            }

            // Cursor-based pagination: follow next_url until has_more is false
            $nextUrl = ($body['has_more'] ?? false) ? ($body['next_url'] ?? null) : null;
            $pages++;
        }

        return $events;
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
            'was_throttled' => $this->wasThrottled,
        ];
    }

    public function normalise(array $raw): ?array
    {
        // Skip online-only events
        if (! empty($raw['online_event'])) {
            return null;
        }

        // Location is a flat key on the event, not nested under venue
        $location = $raw['location'] ?? [];
        $postcode = $location['postal_code'] ?? null;

        if (! $postcode) {
            $addressText = implode(' ', array_filter([
                $location['address_line'] ?? null,
                $location['address_line_2'] ?? null,
                $location['city'] ?? null,
            ]));
            $postcode = $this->extractPostcode($addressText);
        }

        if (! $postcode) {
            return null;
        }

        $minPence = $raw['minimum_price']['amount_in_cents'] ?? null;
        $priceMin = $minPence !== null ? round($minPence / 100, 2) : null;

        return [
            'source' => 'billetto',
            'external_id' => (string) $raw['id'],
            'title' => $raw['title'],
            'description' => strip_tags($raw['description'] ?? ''),
            'category' => $raw['categorization']['category'] ?? null,
            'starts_at' => $raw['startdate'],
            'ends_at' => $raw['enddate'] ?? null,
            'venue_name' => $location['location_name'] ?? null,
            'address' => trim(implode(', ', array_filter([
                $location['address_line'] ?? null,
                $location['city'] ?? null,
            ]))),
            'postcode' => strtoupper(trim($postcode)),
            'latitude' => $location['coordinates']['latitude'] ?? null,
            'longitude' => $location['coordinates']['longitude'] ?? null,
            'url' => $raw['url'] ?? null,
            'image_url' => $raw['image_link'] ?? null,
            'price_min' => $priceMin,
            'price_max' => $priceMin,
        ];
    }

    private function extractPostcode(string $text): ?string
    {
        preg_match('/[A-Z]{1,2}[0-9][0-9A-Z]?\s?[0-9][A-Z]{2}/i', $text, $matches);

        return $matches[0] ?? null;
    }
}
