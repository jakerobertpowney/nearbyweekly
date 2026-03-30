<?php

use App\Jobs\DiscoverFromCommonCrawlJob;
use App\Jobs\DispatchWebsiteCrawlsJob;
use App\Jobs\FetchEventsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Ingest events on Tuesday and Wednesday so the database is fresh before the Thursday send.
// Each provider runs as a separate job: independent timeout budget, retry lifecycle, and queue slot.
// Staggered start times avoid hitting external API rate limits concurrently from the same IP.
Schedule::job(new FetchEventsJob('datathistle'))->weeklyOn(2, '06:00');   // Tuesday
Schedule::job(new FetchEventsJob('ticketmaster'))->weeklyOn(2, '06:30');
Schedule::job(new FetchEventsJob('billetto'))->weeklyOn(2, '07:00');

Schedule::job(new FetchEventsJob('datathistle'))->weeklyOn(3, '06:00');   // Wednesday
Schedule::job(new FetchEventsJob('ticketmaster'))->weeklyOn(3, '06:30');
Schedule::job(new FetchEventsJob('billetto'))->weeklyOn(3, '07:00');

// Send the weekly newsletter every Thursday morning.
Schedule::command('newsletters:send-weekly')->weeklyOn(4, '08:00'); // Thursday 08:00

// Dispatch per-domain schema.org crawl jobs on Tuesdays — runs before the 06:00 ingestion jobs.
// Per-domain CrawlExternalWebsiteJob jobs fan out across the 'crawlers' queue.
Schedule::job(new DispatchWebsiteCrawlsJob)->weeklyOn(2, '05:30')
    ->onOneServer();

// Discover new UK event domains from Common Crawl — first Tuesday of each month.
// Runs at 03:00 to avoid overlap with the 05:30 and 06:00 Tuesday jobs.
Schedule::job(new DiscoverFromCommonCrawlJob)->cron('0 3 1-7 * 2')  // first Tuesday of each month at 03:00
    ->onOneServer();
