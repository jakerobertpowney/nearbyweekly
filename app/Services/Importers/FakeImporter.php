<?php

namespace App\Services\Importers;

use App\Contracts\EventImporter;

class FakeImporter implements EventImporter
{
    /**
     * Get the importer name.
     */
    public function name(): string
    {
        return 'fake';
    }

    /**
     * Determine whether the importer is configured.
     */
    public function isConfigured(): bool
    {
        return app()->environment(['local', 'testing']);
    }

    /**
     * Fetch fake normalized events for testing ingestion flows.
     *
     * @param  array{limit?: int|null}  $options
     * @return list<array<string, mixed>>
     */
    public function fetch(array $options = []): array
    {
        return collect([
            [
                'source' => $this->name(),
                'external_id' => 'fake-london-tech',
                'title' => 'Builders Breakfast for Product Teams',
                'description' => 'An early morning meetup for founders, operators, and product leads.',
                'category' => 'tech',
                'venue_name' => 'Second Home',
                'address_line' => '68 Hanbury Street',
                'city' => 'London',
                'postcode' => 'E1 5JL',
                'latitude' => 51.520067,
                'longitude' => -0.071743,
                'starts_at' => now()->addDays(7)->setTime(8, 30)->toIso8601String(),
                'ends_at' => now()->addDays(7)->setTime(10, 0)->toIso8601String(),
                'url' => 'https://example.com/fake/builders-breakfast',
                'image_url' => null,
                'score_manual' => 4,
                'raw_payload' => ['provider' => 'fake'],
            ],
        ])->take($options['limit'] ?? 50)->values()->all();
    }
}
