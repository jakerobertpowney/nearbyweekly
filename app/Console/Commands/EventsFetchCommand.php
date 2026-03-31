<?php

namespace App\Console\Commands;

use App\Jobs\FetchEventsJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('events:fetch {--provider=} {--limit=50}')]
#[Description('Dispatch a queued job to fetch and normalize events from external providers')]
class EventsFetchCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $provider = $this->option('provider') ?: null;
        $limit = (int) $this->option('limit');

        FetchEventsJob::dispatch($provider, $limit);

        $message = $provider
            ? "Event fetch job dispatched for provider: {$provider}"
            : 'Event fetch job dispatched for all providers';

        $this->info($message);

        return self::SUCCESS;
    }
}
