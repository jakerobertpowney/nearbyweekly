<?php

namespace App\Console\Commands;

use App\Jobs\DiscoverFromCommonCrawlJob;
use App\Models\ExternalWebsite;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('websites:discover {--dry-run}')]
#[Description('Discover UK event domains from Common Crawl and add them to external_websites')]
class WebsitesDiscoverCommand extends Command
{
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $before = ExternalWebsite::query()->count();

        $this->info($dryRun ? 'Running in dry-run mode — no records will be written.' : 'Discovering domains...');

        (new DiscoverFromCommonCrawlJob($dryRun))->handle();

        $after = ExternalWebsite::query()->count();

        $this->table(['Metric', 'Value'], [
            ['Domains before', $before],
            ['Domains after',  $after],
            ['New domains',    $after - $before],
            ['Dry run',        $dryRun ? 'yes' : 'no'],
        ]);

        return self::SUCCESS;
    }
}
