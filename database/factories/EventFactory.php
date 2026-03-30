<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(3);

        return [
            'source' => 'manual',
            'external_id' => fake()->uuid(),
            'title' => $title,
            'slug' => Str::slug($title),
            'description' => fake()->paragraph(),
            'category' => fake()->randomElement(['sports', 'concerts', 'comedy', 'food-and-drink', 'tech']),
            'venue_name' => fake()->company().' Hall',
            'address_line' => fake()->streetAddress(),
            'city' => fake()->randomElement(['London', 'Manchester', 'Edinburgh', 'Bristol']),
            'postcode' => fake()->randomElement(['SW1A 1AA', 'M1 1AE', 'EH1 1YZ', 'BS1 4DJ']),
            'latitude' => 51.507351,
            'longitude' => -0.127758,
            'starts_at' => now()->addDays(fake()->numberBetween(1, 20)),
            'ends_at' => now()->addDays(fake()->numberBetween(1, 20))->addHours(3),
            'url' => fake()->url(),
            'image_url' => fake()->imageUrl(1200, 630),
            'score_manual' => fake()->numberBetween(0, 20),
            'raw_payload' => ['source' => 'factory'],
        ];
    }
}
