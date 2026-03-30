<?php

namespace App\Console\Commands;

use App\Jobs\ClassifyEventJob;
use App\Models\Event;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('events:backfill-interests {--limit= : Only process this many events}')]
#[Description('Dispatch ClassifyEventJob for all events where matched_interest_ids is null')]
class EventsBackfillInterestsCommand extends Command
{
    public function handle(): int
    {
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        $query = Event::query()
            ->whereNull('matched_interest_ids')
            ->where('url_status', '!=', 'dead')
            ->orderBy('id')
            ->select('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        $ids = $query->pluck('id');

        if ($ids->isEmpty()) {
            $this->line('No unclassified events found.');

            return self::SUCCESS;
        }

        $ids->each(fn (int $id) => ClassifyEventJob::dispatch($id));

        $this->line("Dispatched {$ids->count()} ClassifyEventJob(s).");

        return self::SUCCESS;
    }
}
