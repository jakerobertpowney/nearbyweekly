<?php

namespace App\Console\Commands;

use App\Jobs\BackfillPopularityScoreJob;
use App\Models\Event;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('events:backfill-popularity
    {--limit= : Only process this many events}
    {--source= : Only process events from this source (e.g. datathistle, ticketmaster)}
    {--chunk=25 : Events per job (each event makes one AI API call — keep this modest)}
    {--dry-run : Print the count of events that would be scored without dispatching any jobs}
')]
#[Description('Backfill AI popularity scores for events that were ingested before scoring existed')]
class EventsBackfillPopularityCommand extends Command
{
    public function handle(): int
    {
        $limit    = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $source   = $this->option('source');
        $chunk    = max(1, (int) $this->option('chunk'));
        $dryRun   = (bool) $this->option('dry-run');

        $query = Event::query()
            ->whereNull('popularity_score')
            ->where(fn ($q) => $q
                ->where('url_status', '!=', 'dead')
                ->orWhereNull('url_status')
            )
            ->orderBy('id')
            ->select('id');

        if ($source !== null) {
            $query->where('source', $source);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        $total = $query->count();

        if ($total === 0) {
            $this->line('No events need popularity scoring.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->line("Dry run: {$total} events would be scored in ".ceil($total / $chunk)." jobs (chunk size: {$chunk}).");

            return self::SUCCESS;
        }

        $dispatched = 0;

        $query->pluck('id')
            ->chunk($chunk)
            ->each(function ($ids) use (&$dispatched): void {
                BackfillPopularityScoreJob::dispatch($ids->values()->all());
                $dispatched++;
            });

        $this->line("Dispatched {$dispatched} jobs covering {$total} events (chunk size: {$chunk}).");
        $this->line('Each event makes one AI API call. Monitor your Anthropic usage dashboard if running against a large dataset.');

        return self::SUCCESS;
    }
}
