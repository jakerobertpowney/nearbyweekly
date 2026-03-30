<?php

namespace App\Services\Events;

use App\Jobs\ClassifyEventJob;
use App\Models\Event;
use App\Models\IngestionLog;
use App\Services\Importers\BillettoImporter;
use App\Services\Importers\DataThistleImporter;
use App\Services\Importers\FakeImporter;
use App\Services\Importers\TicketmasterFeedImporter;
use App\Services\Importers\TicketmasterImporter;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class EventIngestionService
{
    public function __construct(
        private DataThistleImporter $dataThistleImporter,
        private TicketmasterImporter $ticketmasterImporter,
        private TicketmasterFeedImporter $ticketmasterFeedImporter,
        private FakeImporter $fakeImporter,
        private BillettoImporter $billettoImporter,
    ) {}

    /**
     * Import events from one or more providers.
     *
     * @param  array{provider?: string|null, dry_run?: bool, limit?: int|null}  $options
     * @return array{fetched: int, created: int, updated: int, skipped: int, failed: int, providers: array<int, string>}
     */
    public function import(array $options = []): array
    {
        $stats = [
            'fetched' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'providers' => [],
        ];

        foreach ($this->selectedImporters($options['provider'] ?? null) as $importer) {
            $stats['providers'][] = $importer->name();

            if (! $importer->isConfigured()) {
                $stats['skipped']++;

                continue;
            }

            $providerOptions = $options;

            if ($importer->name() === 'datathistle') {
                $providerOptions['since'] = now()->subWeek()->toIso8601String();
            }

            $providerStats = [
                'fetched' => 0,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'failed' => 0,
            ];

            $logStatus = 'success';

            try {
                foreach ($importer->fetch($providerOptions) as $payload) {
                    $providerStats['fetched']++;
                    $stats['fetched']++;

                    if (($options['dry_run'] ?? false) === true) {
                        $providerStats['skipped']++;
                        $stats['skipped']++;

                        continue;
                    }

                    $result = $this->persist($payload);
                    $providerStats[$result]++;
                    $stats[$result]++;
                }
            } catch (Throwable $exception) {
                report($exception);
                $providerStats['failed']++;
                $stats['failed']++;
                $logStatus = 'failed';
            }

            $rateLimitState = method_exists($importer, 'getRateLimitState')
                ? $importer->getRateLimitState()
                : [];

            if (($rateLimitState['was_throttled'] ?? false) && $logStatus === 'success') {
                $logStatus = 'partial';
            }

            $log = IngestionLog::query()->create([
                'provider'              => $importer->name(),
                'status'                => $logStatus,
                'fetched'               => $providerStats['fetched'],
                'created'               => $providerStats['created'],
                'updated'               => $providerStats['updated'],
                'skipped'               => $providerStats['skipped'],
                'failed'                => $providerStats['failed'],
                'rate_limit_remaining'  => $rateLimitState['remaining'] ?? null,
                'rate_limit_total'      => $rateLimitState['total'] ?? null,
                'rate_limit_reset_at'   => $rateLimitState['reset_at'] ?? null,
                'ran_at'                => now(),
            ]);

            $this->checkAlerts($importer->name(), $log);
        }

        return $stats;
    }

    /**
     * Persist a single normalised event payload (for use by webhook handlers).
     */
    public function persistSingle(array $payload): void
    {
        $result = $this->persist($payload);

        // Dispatch classification for new webhook events
        if ($result === 'created') {
            $event = Event::query()
                ->where('source', $payload['source'])
                ->where('external_id', $payload['external_id'])
                ->first();

            if ($event && empty($event->matched_interest_ids)) {
                ClassifyEventJob::dispatch($event->id);
            }
        }
    }

    /**
     * Check the last two logs for a provider and emit a critical alert if both failed or fetched nothing.
     */
    private function checkAlerts(string $provider, IngestionLog $latest): void
    {
        $lastTwo = IngestionLog::query()
            ->where('provider', $provider)
            ->orderByDesc('ran_at')
            ->limit(2)
            ->get();

        if ($lastTwo->count() < 2) {
            return;
        }

        $bothFailed = $lastTwo->every(fn (IngestionLog $log): bool => $log->status === 'failed');
        $bothEmpty = $lastTwo->every(fn (IngestionLog $log): bool => $log->fetched === 0);

        if ($bothFailed || $bothEmpty) {
            $reason = $bothFailed ? 'two consecutive failures' : 'two consecutive runs with zero events fetched';

            Log::critical("Ingestion alert: provider [{$provider}] has had {$reason}.", [
                'provider' => $provider,
                'log_ids' => $lastTwo->pluck('id')->all(),
            ]);
        }
    }

    /**
     * @return array<int, object>
     */
    private function selectedImporters(?string $provider = null): array
    {
        $ticketmaster = $this->ticketmasterFeedImporter->isConfigured()
            ? $this->ticketmasterFeedImporter
            : $this->ticketmasterImporter;

        $importers = [
            $this->dataThistleImporter,
            $ticketmaster,
            $this->billettoImporter,
        ];

        if (app()->environment(['local', 'testing'])) {
            $importers[] = $this->fakeImporter;
        }

        if ($provider === null) {
            return $importers;
        }

        return array_values(array_filter(
            $importers,
            fn ($importer): bool => $importer->name() === $provider,
        ));
    }

    /**
     * Persist a normalized event payload.
     *
     * Returns 'created', 'updated', or 'skipped'.
     * Public so that non-importer callers (e.g. CrawlExternalWebsiteJob) can
     * persist individual events and inspect the result.
     */
    public function persist(array $payload): string
    {
        $event = null;

        if (filled($payload['external_id'] ?? null)) {
            $event = Event::query()
                ->where('source', $payload['source'])
                ->where('external_id', $payload['external_id'])
                ->first();
        }

        if ($event === null) {
            $event = Event::query()
                ->where('title', $payload['title'])
                ->where('starts_at', Carbon::parse($payload['starts_at']))
                ->where(function ($query) use ($payload): void {
                    $query->where('postcode', $payload['postcode'] ?? null)
                        ->orWhere('city', $payload['city'] ?? null);
                })
                ->first();
        }

        $attributes = [
            ...Arr::only($payload, [
                'source',
                'external_id',
                'title',
                'description',
                'category',
                'venue_name',
                'address_line',
                'city',
                'postcode',
                'latitude',
                'longitude',
                'url',
                'image_url',
                'score_manual',
                'raw_payload',
            ]),
            'slug' => filled($payload['slug'] ?? null) ? $payload['slug'] : Str::slug((string) $payload['title']),
            'starts_at' => Carbon::parse($payload['starts_at']),
            'ends_at' => filled($payload['ends_at'] ?? null) ? Carbon::parse($payload['ends_at']) : null,
        ];

        if ($event === null) {
            $created = Event::query()->create($attributes);
            ClassifyEventJob::dispatch($created->id);

            return 'created';
        }

        $event->fill($attributes);

        if (! $event->isDirty()) {
            return 'skipped';
        }

        $event->save();

        return 'updated';
    }
}
