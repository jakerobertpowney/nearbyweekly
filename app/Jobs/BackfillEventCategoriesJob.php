<?php

namespace App\Jobs;

use App\Models\CategoryMapping;
use App\Models\Event;
use App\Models\Interest;
use App\Services\Events\AiCategoryClassifier;
use App\Services\Events\EventIngestionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BackfillEventCategoriesJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<int>  $eventIds
     */
    public function __construct(
        public array $eventIds,
        public bool $skipAi = false,
    ) {}

    public function handle(EventIngestionService $service, AiCategoryClassifier $classifier): void
    {
        $interests = Interest::query()
            ->get(['id', 'slug', 'name'])
            ->map(fn (Interest $i): array => ['id' => $i->id, 'slug' => $i->slug, 'name' => strtolower($i->name)])
            ->all();

        Event::query()
            ->whereIn('id', $this->eventIds)
            ->each(function (Event $event) use ($service, $classifier, $interests): void {
                $interestIds = $service->resolveByKeywords($event->title ?? '', $event->description ?? '');

                if (! empty($interestIds)) {
                    $resolvedSlug = collect($interests)->firstWhere('id', $interestIds[0])['slug'] ?? null;
                } else {
                    if ($this->skipAi || ! filled($event->title)) {
                        return;
                    }

                    $interestId = $classifier->classify(
                        $event->title ?? '',
                        $event->description ?? '',
                        '',
                        $interests,
                    );

                    if ($interestId === null) {
                        return;
                    }

                    $resolvedSlug = collect($interests)->firstWhere('id', $interestId)['slug'] ?? null;
                    $interestIds = [$interestId];

                    if ($resolvedSlug !== null) {
                        CategoryMapping::query()->firstOrCreate(
                            ['source' => $event->source, 'external_category' => $resolvedSlug],
                            ['interest_id' => $interestId, 'ai_generated' => true],
                        );
                    }
                }

                $event->update([
                    'category' => $resolvedSlug,
                    'matched_interest_ids' => $interestIds,
                ]);
            });
    }
}
