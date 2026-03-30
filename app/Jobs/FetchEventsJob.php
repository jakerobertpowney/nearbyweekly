<?php

namespace App\Jobs;

use App\Services\Events\EventIngestionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FetchEventsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ?string $provider = null,
        public ?int $limit = 50,
    ) {
        $this->timeout = match ($provider) {
            'datathistle'  => 1800, // 30 min — multi-page pagination with 30-40 MB responses
            'ticketmaster' => 900,  // 15 min — large gzipped feed download or paginated API
            default        => 300,  // 5 min — standard API calls (billetto, fake)
        };
    }

    /**
     * Execute the job.
     */
    public function handle(EventIngestionService $eventIngestionService): void
    {
        $eventIngestionService->import([
            'provider' => $this->provider,
            'limit' => $this->limit,
        ]);
    }
}
