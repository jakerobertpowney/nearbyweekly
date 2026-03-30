<?php

namespace App\Jobs;

use App\Models\Event;
use App\Services\Events\AiEventClassifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class GenerateEventTagsJob implements ShouldQueue
{
    use Queueable;

    public int $backoff = 60;

    /** @param  list<int>  $eventIds */
    public function __construct(public readonly array $eventIds)
    {
        $this->onQueue('classification');
    }

    /**
     * Keep retrying for up to 2 hours rather than using a fixed attempt count.
     * Rate-limit releases count as attempts, so a fixed $tries budget gets
     * exhausted quickly on large batches. A time window is the correct model here.
     */
    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(2);
    }

    public function handle(AiEventClassifier $classifier): void
    {
        $events = Event::query()
            ->whereIn('id', $this->eventIds)
            ->whereNull('tags')
            ->where(fn ($q) => $q
                ->where('url_status', '!=', 'dead')
                ->orWhereNull('url_status')
            )
            ->get();

        foreach ($events as $event) {
            $executed = RateLimiter::attempt(
                key: 'anthropic-tagging',
                maxAttempts: 40,
                callback: function () use ($classifier, $event): void {
                    $tags = $classifier->tagEvent($event);

                    $event->tags = $tags; // write even if empty — marks as processed
                    $event->save();

                    Log::debug('GenerateEventTagsJob: tagged', [
                        'event_id' => $event->id,
                        'tags'     => $tags,
                    ]);
                },
                decaySeconds: 60,
            );

            if (! $executed) {
                $this->release(5);

                return;
            }
        }
    }
}
