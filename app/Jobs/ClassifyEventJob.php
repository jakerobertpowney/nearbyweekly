<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\Interest;
use App\Services\Events\AiEventClassifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class ClassifyEventJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public readonly int $eventId)
    {
        $this->onQueue('classification');
    }

    public function handle(AiEventClassifier $classifier): void
    {
        if (! config('app.classification_enabled', true)) {
            return;
        }

        $executed = RateLimiter::attempt(
            key: 'anthropic-classification',
            maxAttempts: 40,
            callback: function () use ($classifier): void {
                $this->runClassification($classifier);
            },
            decaySeconds: 60,
        );

        if (! $executed) {
            $this->release(5);
        }
    }

    private function runClassification(AiEventClassifier $classifier): void
    {
        $event = Event::find($this->eventId);

        if (! $event || ! empty($event->matched_interest_ids)) {
            return;
        }

        $subInterests = Interest::whereNotNull('parent_id')
            ->select('id', 'name', 'slug', 'parent_id')
            ->with('parent:id,name')
            ->get();

        $result = $classifier->classify($event, $subInterests);
        $urlStatus = $result['url_status'];
        $interestIds = $result['interest_ids'];
        $popularityScore = $result['popularity_score'];
        $tags = $result['tags'];

        $event->url_checked_at = now();
        $event->url_status = $urlStatus;

        if ($urlStatus === 'dead') {
            $event->save();
            Log::info('ClassifyEventJob: dead URL, event suppressed', [
                'event_id' => $event->id,
                'title'    => $event->title,
                'url'      => $event->url,
            ]);

            return;
        }

        if (! empty($interestIds)) {
            $event->matched_interest_ids = $interestIds;
        }

        if ($popularityScore !== null) {
            $event->popularity_score = $popularityScore;
        }

        if (! empty($tags)) {
            $event->tags = $tags;
        }

        $event->save();

        if (empty($interestIds)) {
            Log::info('ClassifyEventJob: no sub-interest match', [
                'event_id' => $event->id,
                'title'    => $event->title,
            ]);
        }
    }
}
