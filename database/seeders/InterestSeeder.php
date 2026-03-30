<?php

namespace Database\Seeders;

use App\Models\Interest;
use Illuminate\Database\Seeder;

class InterestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $parents = [
            ['name' => 'Music',                'slug' => 'music',                  'sort_order' => 1],
            ['name' => 'Food & Drink',         'slug' => 'food-and-drink',         'sort_order' => 2],
            ['name' => 'Arts & Entertainment', 'slug' => 'arts-and-entertainment', 'sort_order' => 3],
            ['name' => 'Health & Fitness',     'slug' => 'health-and-fitness',     'sort_order' => 4],
            ['name' => 'Family',               'slug' => 'family',                 'sort_order' => 5],
            ['name' => 'Outdoors & Nature',    'slug' => 'outdoors-and-nature',    'sort_order' => 6],
            ['name' => 'Tech & Professional',  'slug' => 'tech-and-professional',  'sort_order' => 7],
        ];

        $children = [
            'music' => [
                ['name' => 'Live Gigs',          'slug' => 'live-gigs'],
                ['name' => 'Concerts',           'slug' => 'concerts'],
                ['name' => 'Festivals',          'slug' => 'festivals'],
                ['name' => 'Electronic & Dance', 'slug' => 'electronic-dance'],
                ['name' => 'Indie & Rock',       'slug' => 'indie-rock'],
                ['name' => 'Hip-hop & R&B',      'slug' => 'hip-hop-rnb'],
                ['name' => 'Folk & Acoustic',    'slug' => 'folk-acoustic'],
                ['name' => 'Soul & Funk',        'slug' => 'soul-funk'],
                ['name' => 'Classical & Opera',  'slug' => 'classical-opera'],
                ['name' => 'Jazz & Blues',       'slug' => 'jazz-blues'],
            ],
            'arts-and-entertainment' => [
                ['name' => 'Theatre',        'slug' => 'theatre'],
                ['name' => 'Arts & Culture', 'slug' => 'arts-and-culture'],
                ['name' => 'Comedy',         'slug' => 'comedy'],
                ['name' => 'Film & Cinema',  'slug' => 'film-cinema'],
                ['name' => 'Spoken Word',    'slug' => 'spoken-word'],
            ],
            'food-and-drink' => [
                ['name' => 'Food Events',    'slug' => 'food-events'],
                ['name' => 'Markets',        'slug' => 'markets'],
                ['name' => 'Wine & Cocktails', 'slug' => 'wine-cocktails'],
                ['name' => 'Beer & Brewing', 'slug' => 'beer-brewing'],
                ['name' => 'Cooking Classes', 'slug' => 'cooking-classes'],
            ],
            'health-and-fitness' => [
                ['name' => 'Sports',          'slug' => 'sports'],
                ['name' => 'Wellness',        'slug' => 'wellness'],
                ['name' => 'Yoga & Pilates',  'slug' => 'yoga-pilates'],
                ['name' => 'Running & Cycling', 'slug' => 'running-cycling'],
                ['name' => 'Martial Arts',    'slug' => 'martial-arts'],
            ],
            'outdoors-and-nature' => [
                ['name' => 'Hiking',              'slug' => 'hiking'],
                ['name' => 'Farming & Rural',     'slug' => 'farming-and-rural'],
                ['name' => 'Outdoor Adventures',  'slug' => 'outdoor-adventures'],
                ['name' => 'Wildlife & Nature',   'slug' => 'wildlife-nature'],
            ],
            'family' => [
                ['name' => 'Family Days Out',  'slug' => 'family-days-out'],
                ['name' => 'Kids Activities',  'slug' => 'kids-activities'],
                ['name' => 'Educational Days', 'slug' => 'educational-days'],
            ],
            'tech-and-professional' => [
                ['name' => 'Tech',                       'slug' => 'tech'],
                ['name' => 'Business & Networking',      'slug' => 'business-networking'],
                ['name' => 'Conferences',                'slug' => 'conferences'],
                ['name' => 'Workshops',                  'slug' => 'workshops'],
                ['name' => 'Startup & Entrepreneurship', 'slug' => 'startup'],
            ],
        ];

        // Insert/update parents first to get their IDs
        foreach ($parents as $parentData) {
            Interest::query()->updateOrCreate(
                ['slug' => $parentData['slug']],
                ['name' => $parentData['name'], 'parent_id' => null, 'sort_order' => $parentData['sort_order']],
            );
        }

        // Insert/update children referencing their parent
        foreach ($children as $parentSlug => $subs) {
            $parent = Interest::query()->where('slug', $parentSlug)->first();

            if (! $parent) {
                continue;
            }

            foreach ($subs as $childData) {
                Interest::query()->updateOrCreate(
                    ['slug' => $childData['slug']],
                    ['name' => $childData['name'], 'parent_id' => $parent->id],
                );
            }
        }
    }
}
