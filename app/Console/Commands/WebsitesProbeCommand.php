<?php

namespace App\Console\Commands;

use App\Jobs\ProbeExternalWebsiteJob;
use App\Models\ExternalWebsite;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('websites:probe {domain? : Probe a single domain by name} {--sync : Run probes synchronously instead of queuing}')]
#[Description('Dispatch ProbeExternalWebsiteJob for all pending domains, or a single domain by name')]
class WebsitesProbeCommand extends Command
{
    public function handle(): int
    {
        $domain = $this->argument('domain');
        $sync   = (bool) $this->option('sync');

        if ($domain) {
            return $this->probeSingle($domain, $sync);
        }

        return $this->probeAll($sync);
    }

    private function probeSingle(string $domain, bool $sync): int
    {
        $website = ExternalWebsite::query()->where('domain', $domain)->first();

        if (! $website) {
            $this->error("Domain not found: {$domain}");

            return self::FAILURE;
        }

        if ($website->crawl_status !== 'pending') {
            $this->warn("Domain is not pending (current status: {$website->crawl_status}). Probing anyway.");
        }

        if ($sync) {
            (new ProbeExternalWebsiteJob($website->id))->handle();
            $website->refresh();
            $this->info("Done. Status: {$website->crawl_status}" . ($website->blocked_reason ? " ({$website->blocked_reason})" : ''));
        } else {
            ProbeExternalWebsiteJob::dispatch($website->id);
            $this->info("Probe job queued for {$domain}.");
        }

        return self::SUCCESS;
    }

    private function probeAll(bool $sync): int
    {
        $count = ExternalWebsite::query()->where('crawl_status', 'pending')->count();

        if ($count === 0) {
            $this->info('No pending domains found.');

            return self::SUCCESS;
        }

        $this->info("Found {$count} pending domain(s).");

        if ($sync) {
            $this->warn('Running synchronously — this may take a while.');
        }

        $dispatched = 0;

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        ExternalWebsite::query()
            ->where('crawl_status', 'pending')
            ->each(function (ExternalWebsite $website) use ($sync, &$dispatched, $bar): void {
                if ($sync) {
                    (new ProbeExternalWebsiteJob($website->id))->handle();
                } else {
                    ProbeExternalWebsiteJob::dispatch($website->id);
                }

                $dispatched++;
                $bar->advance();
            });

        $bar->finish();
        $this->newLine(2);

        $this->table(['Metric', 'Value'], [
            ['Pending domains found',                        $count],
            [$sync ? 'Probes run (sync)' : 'Probe jobs queued', $dispatched],
        ]);

        if (! $sync) {
            $this->newLine();
            $this->comment('Run the worker to process queued jobs:');
            $this->line('  php artisan queue:work --queue=crawlers');
        }

        return self::SUCCESS;
    }
}
