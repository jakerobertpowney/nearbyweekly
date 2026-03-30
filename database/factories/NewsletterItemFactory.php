<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\NewsletterItem;
use App\Models\NewsletterRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsletterItem>
 */
class NewsletterItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'newsletter_run_id' => NewsletterRun::factory(),
            'user_id' => User::factory(),
            'event_id' => Event::factory(),
            'ranking_score' => fake()->randomFloat(2, 10, 95),
        ];
    }
}
