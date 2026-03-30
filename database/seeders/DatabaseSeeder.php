<?php

namespace Database\Seeders;

use App\Models\Interest;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            InterestSeeder::class,
            CategoryMappingSeeder::class,
            ExampleEventSeeder::class,
        ]);

        $user = User::factory()->create([
            'name' => 'NearbyWeekly Tester',
            'email' => 'test@example.com',
            'postcode' => 'SW1A 1AA',
            'latitude' => 51.501009,
            'longitude' => -0.141588,
        ]);

        $user->interests()->sync(
            Interest::query()
                ->whereIn('slug', ['concerts', 'comedy', 'food-and-drink'])
                ->pluck('id'),
        );
    }
}
