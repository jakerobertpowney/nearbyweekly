<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;

class FetchAllEventsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Dispatch the full daily event ingestion pipeline as a sequential chain.
     *
     * Order:
     *   1. DataThistle   — incremental API fetch with pagination
     *   2. Ticketmaster  — bulk feed or paginated Discovery API
     *   3. Billetto      — standard API fetch
     *   4. DispatchWebsiteCrawlsJob — fans out per-domain schema.org crawls
     *      to the 'crawlers' queue; returns quickly so the chain completes
     *      without waiting for individual crawls to finish.
     *
     * Providers run sequentially so that:
     *  - a slow or failing provider does not consume the queue slot of another
     *  - concurrent outbound HTTP doesn't race against shared rate-limit windows
     *
     * Each FetchEventsJob carries its own timeout budget (defined in FetchEventsJob).
     * ClassifyEventJob is dispatched per-event inside EventIngestionService::persist(),
     * covering both the API providers and the schema.org crawl pipeline.
     */
    public function handle(): void
    {
        Bus::chain([
            new FetchEventsJob('datathistle'),
            new FetchEventsJob('ticketmaster'),
            new FetchEventsJob('billetto'),
            new DispatchWebsiteCrawlsJob,
        ])->dispatch();
    }
}
