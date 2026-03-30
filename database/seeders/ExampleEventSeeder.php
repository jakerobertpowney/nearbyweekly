<?php

namespace Database\Seeders;

use App\Models\Event;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ExampleEventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        collect([
            [
                'source' => 'manual',
                'external_id' => 'manual-london-jazz-night',
                'title' => 'Late Night Jazz at Southbank',
                'description' => 'A relaxed Friday set with emerging London jazz talent and riverside cocktails.',
                'category' => 'concerts',
                'venue_name' => 'Southbank Centre',
                'address_line' => 'Belvedere Road',
                'city' => 'London',
                'postcode' => 'SE1 8XX',
                'latitude' => 51.506873,
                'longitude' => -0.116395,
                'starts_at' => now()->addDays(3)->setTime(19, 30),
                'ends_at' => now()->addDays(3)->setTime(22, 0),
                'url' => 'https://example.com/events/late-night-jazz',
                'image_url' => 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819',
                'score_manual' => 15,
            ],
            [
                'source' => 'manual',
                'external_id' => 'manual-mcr-comedy-cellar',
                'title' => 'Northern Quarter Comedy Cellar',
                'description' => 'Stand-up from rising comics in an intimate Manchester basement venue.',
                'category' => 'comedy',
                'venue_name' => 'The Frog Cellar',
                'address_line' => 'Oldham Street',
                'city' => 'Manchester',
                'postcode' => 'M1 1JN',
                'latitude' => 53.483959,
                'longitude' => -2.236104,
                'starts_at' => now()->addDays(5)->setTime(20, 0),
                'ends_at' => now()->addDays(5)->setTime(22, 30),
                'url' => 'https://example.com/events/comedy-cellar',
                'image_url' => 'https://images.unsplash.com/photo-1527224857830-43a7acc85260',
                'score_manual' => 12,
            ],
            [
                'source' => 'manual',
                'external_id' => 'manual-bristol-food-market',
                'title' => 'Harbourside Street Food Market',
                'description' => 'Weekend market with independent traders, live music, and Bristol favourites.',
                'category' => 'food-and-drink',
                'venue_name' => 'Bristol Harbourside',
                'address_line' => 'Anchor Road',
                'city' => 'Bristol',
                'postcode' => 'BS1 5DB',
                'latitude' => 51.450001,
                'longitude' => -2.601531,
                'starts_at' => now()->addDays(6)->setTime(12, 0),
                'ends_at' => now()->addDays(6)->setTime(18, 0),
                'url' => 'https://example.com/events/harbourside-market',
                'image_url' => 'https://images.unsplash.com/photo-1488459716781-31db52582fe9',
                'score_manual' => 10,
            ],
            [
                'source' => 'manual',
                'external_id' => 'manual-edinburgh-family-fair',
                'title' => 'Princes Street Family Discovery Fair',
                'description' => 'Hands-on activities, story tents, and food stalls for a full family day out.',
                'category' => 'family-days-out',
                'venue_name' => 'Princes Street Gardens',
                'address_line' => 'Princes Street',
                'city' => 'Edinburgh',
                'postcode' => 'EH2 2HG',
                'latitude' => 55.952056,
                'longitude' => -3.196484,
                'starts_at' => now()->addDays(9)->setTime(10, 0),
                'ends_at' => now()->addDays(9)->setTime(16, 0),
                'url' => 'https://example.com/events/family-discovery-fair',
                'image_url' => 'https://images.unsplash.com/photo-1517457373958-b7bdd4587205',
                'score_manual' => 11,
            ],
        ])->each(function (array $event): void {
            Event::query()->updateOrCreate(
                ['source' => $event['source'], 'external_id' => $event['external_id']],
                [
                    ...$event,
                    'slug' => Str::slug($event['title']),
                    'raw_payload' => ['seeded' => true],
                ],
            );
        });
    }
}
