<?php

namespace App\Console\Commands;

use App\Jobs\FetchAllEventsJob;
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
    public function handle(): void
    {
        FetchAllEventsJob::dispatch();
    }
}
