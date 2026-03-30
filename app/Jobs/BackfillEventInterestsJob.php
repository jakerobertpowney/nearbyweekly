<?php

namespace App\Jobs;

use App\Models\Event;
use App\Services\Events\EventIngestionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BackfillEventInterestsJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<int>  $eventIds
     */
    public function __construct(public array $eventIds) {}

    public function handle(EventIngestionService $service): void
    {
        Event::query()
            ->whereIn('id', $this->eventIds)
            ->each(function (Event $event) use ($service): void {
                $payload = [
                    'source' => $event->source,
                    'category' => $event->category,
                    'title' => $event->title,
                    'description' => $event->description,
                ];

                $resolved = $service->resolveInterestIds($payload) ?: null;

                if ($resolved !== $event->matched_interest_ids) {
                    $event->update(['matched_interest_ids' => $resolved]);
                }
            });
    }
}
