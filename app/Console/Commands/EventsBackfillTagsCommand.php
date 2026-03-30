<?php

namespace App\Console\Commands;

use App\Jobs\GenerateEventTagsJob;
use App\Models\Event;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('events:backfill-tags {--batch=50 : Events per job} {--limit= : Max events to process}')]
#[Description('Dispatch GenerateEventTagsJob for all events missing tags')]
class EventsBackfillTagsCommand extends Command
{
    public function handle(): int
    {
        $batchSize = (int) $this->option('batch');
        $limit     = $this->option('limit') ? (int) $this->option('limit') : null;

        $query = Event::query()
            ->whereNull('tags')
            ->where(fn ($q) => $q
                ->where('url_status', '!=', 'dead')
                ->orWhereNull('url_status')
            )
            ->orderByDesc('starts_at')
            ->select('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        $ids = $query->pluck('id')->all();

        if (empty($ids)) {
            $this->info('No events need tagging.');

            return self::SUCCESS;
        }

        $batches = array_chunk($ids, $batchSize);

        foreach ($batches as $batch) {
            GenerateEventTagsJob::dispatch($batch);
        }

        $this->info(sprintf(
            'Queued %d events across %d %s.',
            count($ids),
            count($batches),
            count($batches) === 1 ? 'batch' : 'batches',
        ));

        return self::SUCCESS;
    }
}
