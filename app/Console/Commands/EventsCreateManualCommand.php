<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

#[Signature('events:create-manual
    {title : The event title}
    {category : The interest/category slug}
    {starts_at : Start time, for example "2026-03-28 19:30"}
    {url : Canonical event URL}
    {--city= : Event city}
    {--postcode= : Event postcode}
    {--venue= : Venue name}
    {--score=0 : Manual boost score}
    {--description= : Short event description}')]
#[Description('Create a manual curated event for the newsletter')]
class EventsCreateManualCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $title = (string) $this->argument('title');

        $event = Event::query()->create([
            'source' => 'manual',
            'external_id' => 'manual-'.Str::slug($title).'-'.Str::random(6),
            'title' => $title,
            'slug' => Str::slug($title),
            'description' => $this->option('description'),
            'category' => (string) $this->argument('category'),
            'venue_name' => $this->option('venue'),
            'city' => $this->option('city'),
            'postcode' => $this->option('postcode'),
            'starts_at' => Carbon::parse((string) $this->argument('starts_at')),
            'url' => (string) $this->argument('url'),
            'score_manual' => (int) $this->option('score'),
            'raw_payload' => ['created_via' => 'events:create-manual'],
        ]);

        $this->info("Created manual event #{$event->id}: {$event->title}");

        return self::SUCCESS;
    }
}
