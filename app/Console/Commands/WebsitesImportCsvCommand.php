<?php

namespace App\Console\Commands;

use App\Jobs\ProbeExternalWebsiteJob;
use App\Models\ExternalWebsite;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('websites:import-csv {file : Path to the CSV file} {--dry-run : Preview without writing} {--probe : Run ProbeExternalWebsiteJob synchronously instead of queuing (slow — use for small batches only)}')]
#[Description('Import UK event domains from a CSV file (pld,tld,file_lookup) into external_websites')]
class WebsitesImportCsvCommand extends Command
{
    public function handle(): int
    {
        $path   = $this->argument('file');
        $dryRun    = (bool) $this->option('dry-run');
        $probeSync = (bool) $this->option('probe');

        if (! file_exists($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            $this->error("Could not open file: {$path}");

            return self::FAILURE;
        }

        // Read and validate header
        $header = fgetcsv($handle);

        if ($header === false || ! in_array('pld', $header) || ! in_array('tld', $header)) {
            $this->error('CSV must have at least a pld and tld column.');
            fclose($handle);

            return self::FAILURE;
        }

        $pldIndex = array_search('pld', $header);
        $tldIndex = array_search('tld', $header);

        $this->info($dryRun ? 'Dry-run mode — no records will be written.' : "Importing from: {$path}");

        $inserted  = 0;
        $skipped   = 0;  // already in DB
        $filtered  = 0;  // non-UK rows
        $probes    = 0;

        $bar = $this->output->createProgressBar();
        $bar->setFormat(' %current% rows [%bar%] %elapsed% — %message%');
        $bar->setMessage('reading...');
        $bar->start();

        while (($row = fgetcsv($handle)) !== false) {
            $bar->advance();

            $tld = trim($row[$tldIndex] ?? '');

            if ($tld !== 'uk') {
                $filtered++;
                continue;
            }

            $domain = strtolower(trim($row[$pldIndex] ?? ''));

            if ($domain === '') {
                continue;
            }

            // Skip domains we already know about
            if (ExternalWebsite::query()->where('domain', $domain)->exists()) {
                $skipped++;
                $bar->setMessage("skipped {$domain} (exists)");
                continue;
            }

            if (! $dryRun) {
                $website = ExternalWebsite::query()->create([
                    'domain'           => $domain,
                    'events_page_url'  => "https://{$domain}",
                    'crawl_status'     => 'pending',
                    'discovery_source' => 'csv-import',
                ]);

                if ($probeSync) {
                    (new ProbeExternalWebsiteJob($website->id))->handle();
                } else {
                    ProbeExternalWebsiteJob::dispatch($website->id);
                }

                $probes++;
            }

            $inserted++;
            $bar->setMessage("added {$domain}");
        }

        $bar->setMessage('done');
        $bar->finish();
        $this->newLine(2);

        fclose($handle);

        $this->table(['Metric', 'Value'], [
            ['Non-UK rows filtered',  $filtered],
            ['Already in database',   $skipped],
            ['Inserted',              $inserted],
            [$probeSync ? 'Probes run (sync)' : 'Probe jobs queued', $probes],
            ['Dry run',               $dryRun ? 'yes' : 'no'],
        ]);

        if (! $dryRun && ! $probeSync && $probes > 0) {
            $this->newLine();
            $this->comment("Probe jobs are queued. Run the worker to process them:");
            $this->line('  php artisan queue:work --queue=crawlers');
        }

        return self::SUCCESS;
    }
}
