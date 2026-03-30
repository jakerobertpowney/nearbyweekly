<?php

use App\Jobs\DiscoverFromCommonCrawlJob;
use App\Jobs\FetchAllEventsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Ingest events daily at 06:00 — FetchAllEventsJob dispatches a chain of per-provider jobs
// (DataThistle → Ticketmaster → Billetto) so providers run sequentially with independent
// timeout budgets and retry lifecycles, without hammering external APIs concurrently.
Schedule::job(new FetchAllEventsJob)->dailyAt('06:00');

// Send the weekly newsletter every Thursday morning.
Schedule::command('newsletters:send-weekly')->weeklyOn(4, '08:00'); // Thursday 08:00

// Discover new UK event domains from Common Crawl — first Tuesday of each month.
// Runs at 03:00 to avoid overlap with the 05:30 and 06:00 Tuesday jobs.
Schedule::job(new DiscoverFromCommonCrawlJob)->cron('0 3 1-7 * 2')  // first Tuesday of each month at 03:00
    ->onOneServer();
