<?php

use Illuminate\Support\Facades\Http;

test('postcode validation endpoint returns a normalized postcode for real postcodes', function () {
    config(['services.postcodes_io.base_url' => 'https://postcodes.test']);
    Http::fake([
        'postcodes.test/*' => Http::response([
            'result' => [
                'postcode' => 'SW1A 1AA',
                'latitude' => 51.501009,
                'longitude' => -0.141588,
            ],
        ]),
    ]);

    $this->getJson(route('onboarding.postcode.validate', ['postcode' => 'sw1a1aa']))
        ->assertOk()
        ->assertJson([
            'postcode' => 'SW1A 1AA',
        ]);
});

test('postcode validation endpoint rejects postcodes that cannot be verified', function () {
    config(['services.postcodes_io.base_url' => 'https://postcodes.test']);
    Http::fake([
        'postcodes.test/*' => Http::response(['error' => 'Invalid postcode'], 404),
    ]);

    $this->getJson(route('onboarding.postcode.validate', ['postcode' => 'ZZ1 1ZZ']))
        ->assertUnprocessable()
        ->assertJsonPath('errors.postcode.0', 'Enter a real UK postcode.');
});
