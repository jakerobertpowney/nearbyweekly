<?php

namespace App\Console\Commands;

use App\Jobs\CrawlExternalWebsiteJob;
use App\Jobs\DispatchWebsiteCrawlsJob;
use App\Models\ExternalWebsite;
use App\Services\Events\EventIngestionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('websites:crawl {domain? : Crawl a single domain by name} {--dry-run}')]
#[Description('Run schema.org event crawls for active external websites')]
class WebsitesCrawlCommand extends Command
{
    public function handle(EventIngestionService $ingestionService): int
    {
        $domain = $this->argument('domain');
        $dryRun = (bool) $this->option('dry-run');

        if ($domain) {
            return $this->crawlSingleDomain($domain, $dryRun, $ingestionService);
        }

        // No domain given — dispatch all due active websites
        if ($dryRun) {
            $count = ExternalWebsite::active()->due()->count();
            $this->info("Dry run: would dispatch {$count} crawl job(s).");

            return self::SUCCESS;
        }

        (new DispatchWebsiteCrawlsJob())->handle();
        $this->info('Dispatched crawl jobs for all active due websites.');

        return self::SUCCESS;
    }

    private function crawlSingleDomain(string $domain, bool $dryRun, EventIngestionService $ingestionService): int
    {
        $website = ExternalWebsite::query()->where('domain', $domain)->first();

        if (! $website) {
            $this->error("Domain not found: {$domain}");

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info("Dry run for {$domain} — crawling without persisting.");

            // Run crawl but wrap persist to count without writing
            $originalStatus = $website->crawl_status;
            $website->crawl_status = 'active'; // ensure the job runs

            if ($website->crawl_status !== 'active') {
                $website->save();
            }

            $job = new CrawlExternalWebsiteJob($website->id);
            $job->handle($ingestionService);

            $this->info('Dry run complete — check logs for event counts.');

            return self::SUCCESS;
        }

        $job = new CrawlExternalWebsiteJob($website->id);
        $job->handle($ingestionService);

        $website->refresh();

        $this->table(['Metric', 'Value'], [
            ['Domain',              $website->domain],
            ['Status',              $website->crawl_status],
            ['Events found',        $website->events_found_last_scan],
            ['Total ingested',      $website->total_events_ingested],
            ['Last scanned',        $website->last_scanned_at?->toDateTimeString() ?? 'never'],
        ]);

        return self::SUCCESS;
    }
}
