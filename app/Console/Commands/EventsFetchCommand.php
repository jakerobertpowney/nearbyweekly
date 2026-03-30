<?php

namespace App\Console\Commands;

use App\Services\Events\EventIngestionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('events:fetch {--provider=} {--dry-run} {--limit=50}')]
#[Description('Fetch and normalize events from external providers')]
class EventsFetchCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(EventIngestionService $eventIngestionService): int
    {

        $stats = $eventIngestionService->import([
            'provider' => $this->option('provider') ?: null,
            'dry_run' => (bool) $this->option('dry-run'),
            'limit' => (int) $this->option('limit'),
        ]);

        $this->table(['Metric', 'Value'], [
            ['Providers', implode(', ', $stats['providers'])],
            ['Fetched', $stats['fetched']],
            ['Created', $stats['created']],
            ['Updated', $stats['updated']],
            ['Skipped', $stats['skipped']],
            ['Failed', $stats['failed']],
        ]);

        return self::SUCCESS;
    }
}
