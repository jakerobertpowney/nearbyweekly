<?php

namespace App\Console\Commands;

use App\Jobs\BackfillEventCategoriesJob;
use App\Models\Event;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('events:backfill-categories {--limit= : Only process this many events} {--source= : Only process events from this source} {--skip-ai : Skip AI classification} {--chunk=50 : Events per job}')]
#[Description('Fix events with missing or uncategorisable category values by dispatching background jobs')]
class EventsBackfillCategoriesCommand extends Command
{
    public function handle(): int
    {
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $source = $this->option('source');
        $skipAi = (bool) $this->option('skip-ai');
        $chunkSize = max(1, (int) $this->option('chunk'));

        $query = Event::query()
            ->where(function (Builder $q): void {
                $q->whereNull('category')
                    ->orWhere('category', '')
                    ->orWhereRaw('LOWER(TRIM(category)) = ?', ['undefined'])
                    ->orWhereRaw('LOWER(TRIM(category)) = ?', ['miscellaneous']);
            })
            ->orderBy('id')
            ->select('id');

        if ($source !== null) {
            $query->where('source', $source);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        $total = min($query->count(), $limit ?? PHP_INT_MAX);

        if ($total === 0) {
            $this->line('No events need reprocessing.');

            return self::SUCCESS;
        }

        $dispatched = 0;

        $query->pluck('id')
            ->chunk($chunkSize)
            ->each(function ($ids) use ($skipAi, &$dispatched): void {
                BackfillEventCategoriesJob::dispatch($ids->values()->all(), $skipAi);
                $dispatched++;
            });

        $this->line("Dispatched {$dispatched} jobs covering {$total} events (chunk size: {$chunkSize}).");

        return self::SUCCESS;
    }
}
