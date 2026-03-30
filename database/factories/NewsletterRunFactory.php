<?php

namespace Database\Factories;

use App\Models\NewsletterRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsletterRun>
 */
class NewsletterRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scheduled_for' => now()->next('Friday')->setTime(7, 0),
            'sent_at' => null,
            'status' => 'scheduled',
        ];
    }
}
