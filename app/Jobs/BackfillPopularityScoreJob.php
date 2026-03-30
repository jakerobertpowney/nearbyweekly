<?php

namespace App\Jobs;

use App\Models\Event;
use App\Services\Events\AiEventClassifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class BackfillPopularityScoreJob implements ShouldQueue
{
    use Queueable;

    /**
     * Number of times the job may be attempted.
     * Allows recovery from transient API errors without manual intervention.
     */
    public int $tries = 3;

    /**
     * Seconds to wait before retrying after a failure.
     */
    public int $backoff = 60;

    /**
     * @param  list<int>  $eventIds  IDs of events to score in this batch.
     */
    public function __construct(public readonly array $eventIds) {}

    public function handle(AiEventClassifier $classifier): void
    {
        $events = Event::query()
            ->whereIn('id', $this->eventIds)
            ->whereNull('popularity_score')
            ->where(fn ($q) => $q
                ->where('url_status', '!=', 'dead')
                ->orWhereNull('url_status')
            )
            ->get();

        foreach ($events as $event) {
            $score = $classifier->scorePopularity($event);

            if ($score === null) {
                Log::warning('BackfillPopularityScoreJob: scoring failed', [
                    'event_id' => $event->id,
                    'title'    => $event->title,
                ]);

                continue;
            }

            $event->popularity_score = $score;
            $event->save();

            Log::debug('BackfillPopularityScoreJob: scored', [
                'event_id' => $event->id,
                'score'    => $score,
            ]);
        }
    }
}
