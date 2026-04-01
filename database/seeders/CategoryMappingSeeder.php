<?php

namespace Database\Seeders;

use App\Models\CategoryMapping;
use App\Models\Interest;
use Illuminate\Database\Seeder;

class CategoryMappingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $interests = Interest::query()->pluck('id', 'slug');

        $mappings = [
            // Ticketmaster — classifications.0.segment.name (lowercased)
            ['interest' => 'concerts', 'source' => 'ticketmaster', 'external_category' => 'music'],
            ['interest' => 'sports', 'source' => 'ticketmaster', 'external_category' => 'sports'],
            ['interest' => 'theatre', 'source' => 'ticketmaster', 'external_category' => 'arts & theatre'],
            ['interest' => 'comedy', 'source' => 'ticketmaster', 'external_category' => 'comedy'],
            ['interest' => 'family-days-out', 'source' => 'ticketmaster', 'external_category' => 'family'],
            ['interest' => 'festivals', 'source' => 'ticketmaster', 'external_category' => 'miscellaneous'],

            // Ticketmaster Feed — same mapping as Discovery API
            ['interest' => 'concerts', 'source' => 'ticketmaster-feed', 'external_category' => 'music'],
            ['interest' => 'sports', 'source' => 'ticketmaster-feed', 'external_category' => 'sports'],
            ['interest' => 'theatre', 'source' => 'ticketmaster-feed', 'external_category' => 'arts & theatre'],
            ['interest' => 'comedy', 'source' => 'ticketmaster-feed', 'external_category' => 'comedy'],
            ['interest' => 'family-days-out', 'source' => 'ticketmaster-feed', 'external_category' => 'family'],
            ['interest' => 'festivals', 'source' => 'ticketmaster-feed', 'external_category' => 'miscellaneous'],

            // DataThistle — category slugs (slugified)
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'live-music'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'music'],
            ['interest' => 'food-and-drink', 'source' => 'datathistle', 'external_category' => 'food-drink'],
            ['interest' => 'food-and-drink', 'source' => 'datathistle', 'external_category' => 'food-and-drink'],
            ['interest' => 'comedy', 'source' => 'datathistle', 'external_category' => 'comedy'],
            ['interest' => 'theatre', 'source' => 'datathistle', 'external_category' => 'theatre'],
            ['interest' => 'sports', 'source' => 'datathistle', 'external_category' => 'sport'],
            ['interest' => 'sports', 'source' => 'datathistle', 'external_category' => 'sports'],
            ['interest' => 'wellness', 'source' => 'datathistle', 'external_category' => 'wellness'],
            ['interest' => 'festivals', 'source' => 'datathistle', 'external_category' => 'festival'],
            ['interest' => 'festivals', 'source' => 'datathistle', 'external_category' => 'festivals'],
            ['interest' => 'markets', 'source' => 'datathistle', 'external_category' => 'markets'],
            ['interest' => 'markets', 'source' => 'datathistle', 'external_category' => 'market'],
            ['interest' => 'hiking', 'source' => 'datathistle', 'external_category' => 'hiking'],
            ['interest' => 'hiking', 'source' => 'datathistle', 'external_category' => 'outdoors'],
            ['interest' => 'family-days-out', 'source' => 'datathistle', 'external_category' => 'family'],
            ['interest' => 'family-days-out', 'source' => 'datathistle', 'external_category' => 'family-friendly'],
            ['interest' => 'tech', 'source' => 'datathistle', 'external_category' => 'tech'],
            ['interest' => 'tech', 'source' => 'datathistle', 'external_category' => 'technology'],
            ['interest' => 'farming-and-rural', 'source' => 'datathistle', 'external_category' => 'farming'],
            ['interest' => 'farming-and-rural', 'source' => 'datathistle', 'external_category' => 'rural'],

            // Shows & Attractions — Motor Shows & Cars
            ['interest' => 'motor-shows', 'source' => 'datathistle', 'external_category' => 'motor-show'],
            ['interest' => 'motor-shows', 'source' => 'datathistle', 'external_category' => 'motor-shows'],
            ['interest' => 'motor-shows', 'source' => 'datathistle', 'external_category' => 'motorshow'],
            ['interest' => 'motor-shows', 'source' => 'datathistle', 'external_category' => 'car-show'],
            ['interest' => 'motor-shows', 'source' => 'datathistle', 'external_category' => 'classic-cars'],
            ['interest' => 'motor-shows', 'source' => 'datathistle', 'external_category' => 'classic-car-show'],
            ['interest' => 'motor-shows', 'source' => 'datathistle', 'external_category' => 'cars'],
            ['interest' => 'motor-shows', 'source' => 'datathistle', 'external_category' => 'automotive'],
            ['interest' => 'motor-shows', 'source' => 'ticketmaster', 'external_category' => 'motor sports'],
            ['interest' => 'motor-shows', 'source' => 'ticketmaster-feed', 'external_category' => 'motor sports'],
            ['interest' => 'motor-shows', 'source' => 'billetto', 'external_category' => 'motor_show'],
            ['interest' => 'motor-shows', 'source' => 'billetto', 'external_category' => 'automotive'],

            // Shows & Attractions — Air Shows
            ['interest' => 'air-shows', 'source' => 'datathistle', 'external_category' => 'air-show'],
            ['interest' => 'air-shows', 'source' => 'datathistle', 'external_category' => 'air-shows'],
            ['interest' => 'air-shows', 'source' => 'datathistle', 'external_category' => 'airshow'],
            ['interest' => 'air-shows', 'source' => 'datathistle', 'external_category' => 'air-display'],
            ['interest' => 'air-shows', 'source' => 'datathistle', 'external_category' => 'aviation'],
            ['interest' => 'air-shows', 'source' => 'billetto',    'external_category' => 'air_show'],
            ['interest' => 'air-shows', 'source' => 'billetto',    'external_category' => 'aviation'],

            // Shows & Attractions — Agricultural & County Shows
            ['interest' => 'agricultural-shows', 'source' => 'datathistle', 'external_category' => 'agricultural-show'],
            ['interest' => 'agricultural-shows', 'source' => 'datathistle', 'external_category' => 'county-show'],
            ['interest' => 'agricultural-shows', 'source' => 'datathistle', 'external_category' => 'country-show'],
            ['interest' => 'agricultural-shows', 'source' => 'datathistle', 'external_category' => 'country-fair'],
            ['interest' => 'agricultural-shows', 'source' => 'datathistle', 'external_category' => 'agricultural'],
            ['interest' => 'agricultural-shows', 'source' => 'datathistle', 'external_category' => 'country-sports'],
            ['interest' => 'agricultural-shows', 'source' => 'billetto',    'external_category' => 'agricultural_show'],
            ['interest' => 'agricultural-shows', 'source' => 'billetto',    'external_category' => 'country_show'],

            // Shows & Attractions — Flower & Garden Shows
            ['interest' => 'flower-garden-shows', 'source' => 'datathistle', 'external_category' => 'flower-show'],
            ['interest' => 'flower-garden-shows', 'source' => 'datathistle', 'external_category' => 'garden-show'],
            ['interest' => 'flower-garden-shows', 'source' => 'datathistle', 'external_category' => 'gardening'],
            ['interest' => 'flower-garden-shows', 'source' => 'datathistle', 'external_category' => 'horticulture'],
            ['interest' => 'flower-garden-shows', 'source' => 'datathistle', 'external_category' => 'garden'],
            ['interest' => 'flower-garden-shows', 'source' => 'datathistle', 'external_category' => 'flowers'],
            ['interest' => 'flower-garden-shows', 'source' => 'billetto',    'external_category' => 'garden_show'],
            ['interest' => 'flower-garden-shows', 'source' => 'billetto',    'external_category' => 'gardening'],

            // Shows & Attractions — Boat Shows
            ['interest' => 'boat-shows', 'source' => 'datathistle', 'external_category' => 'boat-show'],
            ['interest' => 'boat-shows', 'source' => 'datathistle', 'external_category' => 'boat-shows'],
            ['interest' => 'boat-shows', 'source' => 'datathistle', 'external_category' => 'marine'],
            ['interest' => 'boat-shows', 'source' => 'datathistle', 'external_category' => 'nautical'],
            ['interest' => 'boat-shows', 'source' => 'billetto',    'external_category' => 'boat_show'],
            ['interest' => 'boat-shows', 'source' => 'billetto',    'external_category' => 'marine'],

            // Outdoors & Nature — Sailing & Water Sports
            ['interest' => 'sailing-watersports', 'source' => 'datathistle', 'external_category' => 'sailing'],
            ['interest' => 'sailing-watersports', 'source' => 'datathistle', 'external_category' => 'watersports'],
            ['interest' => 'sailing-watersports', 'source' => 'datathistle', 'external_category' => 'water-sports'],
            ['interest' => 'sailing-watersports', 'source' => 'datathistle', 'external_category' => 'kayaking'],
            ['interest' => 'sailing-watersports', 'source' => 'datathistle', 'external_category' => 'paddleboarding'],
            ['interest' => 'sailing-watersports', 'source' => 'datathistle', 'external_category' => 'surfing'],
            ['interest' => 'sailing-watersports', 'source' => 'datathistle', 'external_category' => 'windsurfing'],
            ['interest' => 'sailing-watersports', 'source' => 'datathistle', 'external_category' => 'rowing'],
            ['interest' => 'sailing-watersports', 'source' => 'billetto',    'external_category' => 'sailing'],
            ['interest' => 'sailing-watersports', 'source' => 'billetto',    'external_category' => 'water_sports'],

            // Arts & Culture — Ticketmaster (arts & theatre maps to BOTH theatre and arts-and-culture)
            ['interest' => 'arts-and-culture', 'source' => 'ticketmaster', 'external_category' => 'arts & theatre'],
            ['interest' => 'arts-and-culture', 'source' => 'ticketmaster-feed', 'external_category' => 'arts & theatre'],

            // Arts & Culture — DataThistle
            ['interest' => 'arts-and-culture', 'source' => 'datathistle', 'external_category' => 'arts'],
            ['interest' => 'arts-and-culture', 'source' => 'datathistle', 'external_category' => 'visual-arts'],
            ['interest' => 'arts-and-culture', 'source' => 'datathistle', 'external_category' => 'exhibitions'],
            ['interest' => 'arts-and-culture', 'source' => 'datathistle', 'external_category' => 'gallery'],
            ['interest' => 'arts-and-culture', 'source' => 'datathistle', 'external_category' => 'culture'],
            ['interest' => 'arts-and-culture', 'source' => 'datathistle', 'external_category' => 'heritage'],

            // Family Days Out — kids/children gap-fill
            ['interest' => 'family-days-out', 'source' => 'datathistle', 'external_category' => 'kids'],
            ['interest' => 'family-days-out', 'source' => 'datathistle', 'external_category' => 'children'],
            ['interest' => 'family-days-out', 'source' => 'datathistle', 'external_category' => 'kids-activities'],
            ['interest' => 'family-days-out', 'source' => 'datathistle', 'external_category' => 'family-fun'],
            ['interest' => 'family-days-out', 'source' => 'ticketmaster', 'external_category' => 'family'],
            ['interest' => 'family-days-out', 'source' => 'ticketmaster-feed', 'external_category' => 'family'],

            // DataThistle — expanded category coverage (backfill scope)
            ['interest' => 'family-days-out', 'source' => 'datathistle', 'external_category' => 'days-out'],
            ['interest' => 'family-days-out', 'source' => 'datathistle', 'external_category' => 'activities'],
            ['interest' => 'family-days-out', 'source' => 'datathistle', 'external_category' => 'easter'],
            ['interest' => 'family-days-out', 'source' => 'datathistle', 'external_category' => 'attractions'],
            ['interest' => 'family-days-out', 'source' => 'datathistle', 'external_category' => 'crazy-golf'],
            ['interest' => 'family-days-out', 'source' => 'datathistle', 'external_category' => 'circus'],
            ['interest' => 'family-days-out', 'source' => 'datathistle', 'external_category' => 'disney'],
            ['interest' => 'family-days-out', 'source' => 'datathistle', 'external_category' => 'animals'],
            ['interest' => 'family-days-out', 'source' => 'datathistle', 'external_category' => 'seasonal'],

            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'clubs'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'alternative'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'party'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'indie'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'folk'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'classical'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'jazz'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'electronica'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'dj'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'blues'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'country'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'metal'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'afrobeats'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'drum-bass'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'k-pop'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'lgbt'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'hip-hop'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'acoustic'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'experimental'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'choir'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'choral'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'easy-listening'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'edm'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'funk'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'disco'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'soul'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'dubstep'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'breaks'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'hardcore'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'grunge'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'hard-rock'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'heavy-metal'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'grindcore'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'ambient'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'bluegrass'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'celtic'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'african'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'acid-jazz'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'alt'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'alternative-pop'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'brit-pop'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'madchester'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'cheesy-dance'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'chart-party'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => 'singer-songwriter'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => '70s'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => '80s'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => '60s'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => '2000s'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => '1970s'],
            ['interest' => 'concerts', 'source' => 'datathistle', 'external_category' => '1990s'],

            ['interest' => 'theatre', 'source' => 'datathistle', 'external_category' => 'drama'],
            ['interest' => 'theatre', 'source' => 'datathistle', 'external_category' => 'musical'],
            ['interest' => 'theatre', 'source' => 'datathistle', 'external_category' => 'cabaret'],
            ['interest' => 'theatre', 'source' => 'datathistle', 'external_category' => 'physical-theatre'],
            ['interest' => 'theatre', 'source' => 'datathistle', 'external_category' => 'pantomime'],
            ['interest' => 'theatre', 'source' => 'datathistle', 'external_category' => 'play'],
            ['interest' => 'theatre', 'source' => 'datathistle', 'external_category' => 'burlesque'],
            ['interest' => 'theatre', 'source' => 'datathistle', 'external_category' => 'magic'],

            ['interest' => 'arts-and-culture', 'source' => 'datathistle', 'external_category' => 'film'],
            ['interest' => 'arts-and-culture', 'source' => 'datathistle', 'external_category' => 'books'],
            ['interest' => 'arts-and-culture', 'source' => 'datathistle', 'external_category' => 'workshops'],
            ['interest' => 'arts-and-culture', 'source' => 'datathistle', 'external_category' => 'dance'],
            ['interest' => 'arts-and-culture', 'source' => 'datathistle', 'external_category' => 'animation'],
            ['interest' => 'arts-and-culture', 'source' => 'datathistle', 'external_category' => 'visual-art'],
            ['interest' => 'arts-and-culture', 'source' => 'datathistle', 'external_category' => 'exhibition'],
            ['interest' => 'arts-and-culture', 'source' => 'datathistle', 'external_category' => 'talks-lectures'],
            ['interest' => 'arts-and-culture', 'source' => 'datathistle', 'external_category' => 'documentary'],
            ['interest' => 'arts-and-culture', 'source' => 'datathistle', 'external_category' => 'biography'],
            ['interest' => 'arts-and-culture', 'source' => 'datathistle', 'external_category' => 'art-craft'],
            ['interest' => 'arts-and-culture', 'source' => 'datathistle', 'external_category' => 'crafts'],
            ['interest' => 'arts-and-culture', 'source' => 'datathistle', 'external_category' => 'charity'],
            ['interest' => 'arts-and-culture', 'source' => 'datathistle', 'external_category' => 'painter-drawing'],
            ['interest' => 'arts-and-culture', 'source' => 'datathistle', 'external_category' => 'installation'],
            ['interest' => 'arts-and-culture', 'source' => 'datathistle', 'external_category' => 'ballet'],
            ['interest' => 'arts-and-culture', 'source' => 'datathistle', 'external_category' => 'event-cinema'],
            ['interest' => 'arts-and-culture', 'source' => 'datathistle', 'external_category' => 'arthouse'],
            ['interest' => 'arts-and-culture', 'source' => 'datathistle', 'external_category' => 'art-and-design'],
            ['interest' => 'arts-and-culture', 'source' => 'datathistle', 'external_category' => 'art'],
            ['interest' => 'arts-and-culture', 'source' => 'datathistle', 'external_category' => 'contemporary'],
            ['interest' => 'arts-and-culture', 'source' => 'datathistle', 'external_category' => 'current-affairs'],
            ['interest' => 'arts-and-culture', 'source' => 'datathistle', 'external_category' => 'history'],
            ['interest' => 'arts-and-culture', 'source' => 'datathistle', 'external_category' => 'pottery-ceramics'],
            ['interest' => 'arts-and-culture', 'source' => 'datathistle', 'external_category' => 'embroidery'],
            ['interest' => 'arts-and-culture', 'source' => 'datathistle', 'external_category' => 'jewellery'],
            ['interest' => 'arts-and-culture', 'source' => 'datathistle', 'external_category' => 'prints'],
            ['interest' => 'arts-and-culture', 'source' => 'datathistle', 'external_category' => 'photography'],

            ['interest' => 'food-and-drink', 'source' => 'datathistle', 'external_category' => 'pub-night'],
            ['interest' => 'food-and-drink', 'source' => 'datathistle', 'external_category' => 'pub-crawl'],
            ['interest' => 'food-and-drink', 'source' => 'datathistle', 'external_category' => 'social'],
            ['interest' => 'food-and-drink', 'source' => 'datathistle', 'external_category' => 'quiz-night'],
            ['interest' => 'food-and-drink', 'source' => 'datathistle', 'external_category' => 'quiz'],
            ['interest' => 'food-and-drink', 'source' => 'datathistle', 'external_category' => 'pub-quiz'],
            ['interest' => 'food-and-drink', 'source' => 'datathistle', 'external_category' => 'karaoke'],

            ['interest' => 'sports', 'source' => 'datathistle', 'external_category' => 'action'],
            ['interest' => 'sports', 'source' => 'datathistle', 'external_category' => 'motorsport'],
            ['interest' => 'sports', 'source' => 'datathistle', 'external_category' => 'cycling'],
            ['interest' => 'sports', 'source' => 'datathistle', 'external_category' => 'football'],

            ['interest' => 'hiking', 'source' => 'datathistle', 'external_category' => 'adventure'],
            ['interest' => 'hiking', 'source' => 'datathistle', 'external_category' => 'gardens'],

            ['interest' => 'wellness', 'source' => 'datathistle', 'external_category' => 'health'],
            ['interest' => 'wellness', 'source' => 'datathistle', 'external_category' => 'class'],
            ['interest' => 'wellness', 'source' => 'datathistle', 'external_category' => 'courses'],
            ['interest' => 'wellness', 'source' => 'datathistle', 'external_category' => 'spiritual'],

            // Billetto — flat snake_case category strings from the API
            ['interest' => 'concerts',        'source' => 'billetto', 'external_category' => 'music'],
            ['interest' => 'festivals',        'source' => 'billetto', 'external_category' => 'festival'],
            ['interest' => 'arts-and-culture', 'source' => 'billetto', 'external_category' => 'art'],
            ['interest' => 'arts-and-culture', 'source' => 'billetto', 'external_category' => 'visual_art'],
            ['interest' => 'arts-and-culture', 'source' => 'billetto', 'external_category' => 'exhibition'],
            ['interest' => 'theatre',          'source' => 'billetto', 'external_category' => 'theatre'],
            ['interest' => 'comedy',           'source' => 'billetto', 'external_category' => 'comedy'],
            ['interest' => 'food-and-drink',   'source' => 'billetto', 'external_category' => 'food_drink'],
            ['interest' => 'food-and-drink',   'source' => 'billetto', 'external_category' => 'food'],
            ['interest' => 'wellness',         'source' => 'billetto', 'external_category' => 'health_wellness'],
            ['interest' => 'wellness',         'source' => 'billetto', 'external_category' => 'yoga'],
            ['interest' => 'fitness',          'source' => 'billetto', 'external_category' => 'sport'],
            ['interest' => 'fitness',          'source' => 'billetto', 'external_category' => 'fitness'],
            ['interest' => 'family-days-out',  'source' => 'billetto', 'external_category' => 'family_kids'],
            ['interest' => 'family-days-out',  'source' => 'billetto', 'external_category' => 'kids'],
            ['interest' => 'networking',       'source' => 'billetto', 'external_category' => 'business'],
            ['interest' => 'networking',       'source' => 'billetto', 'external_category' => 'conference'],
        ];

        foreach ($mappings as $mapping) {
            $interestId = $interests[$mapping['interest']] ?? null;

            if ($interestId === null) {
                continue;
            }

            CategoryMapping::query()->updateOrCreate(
                ['source' => $mapping['source'], 'external_category' => $mapping['external_category']],
                ['interest_id' => $interestId],
            );
        }
    }
}
