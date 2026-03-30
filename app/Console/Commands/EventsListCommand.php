<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('events:list {--limit=20}')]
#[Description('List recent events for admin-lite review')]
class EventsListCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $events = Event::query()
            ->latest('starts_at')
            ->limit((int) $this->option('limit'))
            ->get();

        $this->table(['ID', 'Title', 'Source', 'Category', 'City', 'Starts', 'Boost'], $events->map(fn (Event $event): array => [
            $event->id,
            $event->title,
            $event->source,
            $event->category,
            $event->city,
            optional($event->starts_at)?->format('Y-m-d H:i'),
            $event->score_manual,
        ])->all());

        return self::SUCCESS;
    }
}
