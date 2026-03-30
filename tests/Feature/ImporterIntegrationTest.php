<?php

use App\Models\Event;
use App\Services\Events\EventIngestionService;
use App\Services\Importers\DataThistleImporter;
use App\Services\Importers\TicketmasterImporter;
use Illuminate\Support\Facades\Http;

test('ticketmaster importer maps provider payloads into the internal event shape', function () {
    config([
        'services.ticketmaster.api_key' => 'test-key',
        'services.ticketmaster.base_url' => 'https://ticketmaster.test',
    ]);

    Http::fake([
        'ticketmaster.test/*' => Http::response([
            '_embedded' => [
                'events' => [
                    [
                        'id' => 'tm_123',
                        'name' => 'Ticketmaster Gig',
                        'url' => 'https://ticketmaster.test/events/tm_123',
                        'dates' => [
                            'start' => [
                                'dateTime' => now()->addDays(4)->toIso8601String(),
                            ],
                        ],
                        'classifications' => [
                            ['segment' => ['name' => 'Concerts']],
                        ],
                        '_embedded' => [
                            'venues' => [
                                [
                                    'name' => 'Royal Albert Hall',
                                    'city' => ['name' => 'London'],
                                    'postalCode' => 'SW7 2AP',
                                    'location' => [
                                        'latitude' => '51.5010',
                                        'longitude' => '-0.1773',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $events = app(TicketmasterImporter::class)->fetch(['limit' => 10]);

    expect($events)->toHaveCount(1);
    expect($events[0]['source'])->toBe('ticketmaster');
    expect($events[0]['category'])->toBe('concerts');
    expect($events[0]['city'])->toBe('London');
});

test('datathistle importer maps provider payloads into the internal event shape', function () {
    config([
        'services.datathistle.access_token' => 'test-token',
        'services.datathistle.base_url' => 'https://datathistle.test',
    ]);

    Http::fake([
        'datathistle.test/*' => Http::response([
            'events' => [
                [
                    'id' => 'dt_123',
                    'title' => 'DataThistle Comedy',
                    'url' => 'https://datathistle.test/events/dt_123',
                    'start_datetime' => now()->addDays(6)->toIso8601String(),
                    'category' => ['slug' => 'comedy'],
                    'venue' => [
                        'name' => 'The Glee Club',
                        'address' => [
                            'city' => 'Birmingham',
                            'postcode' => 'B5 4TB',
                        ],
                        'latitude' => 52.4750,
                        'longitude' => -1.8983,
                    ],
                ],
            ],
        ]),
    ]);

    $events = iterator_to_array(app(DataThistleImporter::class)->fetch(['limit' => 10]), false);

    expect($events)->toHaveCount(1);
    expect($events[0]['source'])->toBe('datathistle');
    expect($events[0]['category'])->toBe('comedy');
    expect($events[0]['city'])->toBe('Birmingham');
});

test('event ingestion deduplicates repeated imports by external id', function () {
    config([
        'services.ticketmaster.api_key' => 'test-key',
        'services.ticketmaster.base_url' => 'https://ticketmaster.test',
        'services.ticketmaster.feed_url' => null,
    ]);

    Http::fake([
        'ticketmaster.test/*' => Http::sequence()
            ->push([
                '_embedded' => [
                    'events' => [[
                        'id' => 'tm_123',
                        'name' => 'Ticketmaster Gig',
                        'url' => 'https://ticketmaster.test/events/tm_123',
                        'dates' => ['start' => ['dateTime' => now()->addDays(4)->toIso8601String()]],
                        '_embedded' => ['venues' => [[
                            'name' => 'Royal Albert Hall',
                            'city' => ['name' => 'London'],
                            'postalCode' => 'SW7 2AP',
                        ]]],
                    ]],
                ],
            ])
            ->push([
                '_embedded' => [
                    'events' => [[
                        'id' => 'tm_123',
                        'name' => 'Ticketmaster Gig Updated',
                        'url' => 'https://ticketmaster.test/events/tm_123',
                        'dates' => ['start' => ['dateTime' => now()->addDays(4)->toIso8601String()]],
                        '_embedded' => ['venues' => [[
                            'name' => 'Royal Albert Hall',
                            'city' => ['name' => 'London'],
                            'postalCode' => 'SW7 2AP',
                        ]]],
                    ]],
                ],
            ]),
    ]);

    $service = app(EventIngestionService::class);

    $first = $service->import(['provider' => 'ticketmaster', 'limit' => 10]);
    $second = $service->import(['provider' => 'ticketmaster', 'limit' => 10]);

    expect($first['created'])->toBe(1);
    expect($second['updated'])->toBe(1);
    expect(Event::query()->count())->toBe(1);
    expect(Event::query()->first()->title)->toBe('Ticketmaster Gig Updated');
});

test('datathistle payloads without external ids still expose fallback dedupe fields', function () {
    config([
        'services.datathistle.access_token' => 'test-token',
        'services.datathistle.base_url' => 'https://datathistle.test',
    ]);

    $start = now()->addWeek()->toIso8601String();

    Http::fake([
        'datathistle.test/*' => Http::sequence()
            ->push([
                'events' => [[
                    'title' => 'Market by the Docks',
                    'url' => 'https://datathistle.test/events/market',
                    'start_datetime' => $start,
                    'category' => ['slug' => 'markets'],
                    'venue' => [
                        'address' => [
                            'city' => 'Bristol',
                            'postcode' => 'BS1 5DB',
                        ],
                    ],
                ]],
            ])
            ->push([
                'events' => [[
                    'title' => 'Market by the Docks',
                    'url' => 'https://datathistle.test/events/market',
                    'start_datetime' => $start,
                    'category' => ['slug' => 'markets'],
                    'venue' => [
                        'address' => [
                            'city' => 'Bristol',
                            'postcode' => 'BS1 5DB',
                        ],
                    ],
                ]],
            ]),
    ]);

    $events = iterator_to_array(app(DataThistleImporter::class)->fetch(['limit' => 10]), false);

    expect($events)->toHaveCount(1);
    expect($events[0]['external_id'])->toBe('');
    expect($events[0]['title'])->toBe('Market by the Docks');
    expect($events[0]['postcode'])->toBe('BS1 5DB');
    expect($events[0]['starts_at'])->toBe($start);
});

test('datathistle importer maps the live publishing api schedule structure', function () {
    config([
        'services.datathistle.access_token' => 'test-token',
        'services.datathistle.base_url' => 'https://datathistle.test',
    ]);

    Http::fake([
        'datathistle.test/*' => Http::response([
            [
                'event_id' => 'dt_live_123',
                'name' => 'Salsa & Bachata Classes',
                'schedules' => [
                    [
                        'start_ts' => now()->addDays(3)->toIso8601String(),
                        'end_ts' => now()->addDays(30)->toIso8601String(),
                        'tags' => ['dance', 'salsa'],
                        'place' => [
                            'name' => 'Hammersmith Salsa Club',
                            'address' => '11 Rutland Grove',
                            'town' => 'London',
                            'postal_code' => 'W6 9DH',
                            'lat' => 51.49025,
                            'lon' => -0.23117,
                        ],
                        'performances' => [
                            [
                                'ts' => now()->addDays(3)->toIso8601String(),
                                'links' => [
                                    [
                                        'type' => 'booking',
                                        'url' => 'https://www.datathistle.com/details/example',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $events = iterator_to_array(app(DataThistleImporter::class)->fetch(['limit' => 10]), false);

    expect($events)->toHaveCount(1);
    expect($events[0]['external_id'])->toBe('dt_live_123');
    expect($events[0]['title'])->toBe('Salsa & Bachata Classes');
    expect($events[0]['category'])->toBe('dance');
    expect($events[0]['venue_name'])->toBe('Hammersmith Salsa Club');
    expect($events[0]['postcode'])->toBe('W6 9DH');
    expect($events[0]['url'])->toBe('https://www.datathistle.com/details/example');
});
