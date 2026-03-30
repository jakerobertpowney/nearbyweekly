<?php

namespace App\Jobs;

use App\Models\ExternalWebsite;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DispatchWebsiteCrawlsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 60;

    public function handle(): void
    {
        $websites = ExternalWebsite::active()->due()->get();

        foreach ($websites as $website) {
            CrawlExternalWebsiteJob::dispatch($website->id);
        }

        Log::info('DispatchWebsiteCrawlsJob: dispatched crawl jobs.', ['count' => $websites->count()]);
    }
}
