<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('events:boost {event : Event ID} {score : New manual boost score}')]
#[Description('Update the manual boost score for an event')]
class EventsBoostCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $event = Event::query()->findOrFail((int) $this->argument('event'));

        $event->update([
            'score_manual' => (int) $this->argument('score'),
        ]);

        $this->info("Updated event #{$event->id} to boost score {$event->score_manual}.");

        return self::SUCCESS;
    }
}
