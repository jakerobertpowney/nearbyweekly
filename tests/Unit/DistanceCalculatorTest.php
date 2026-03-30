<?php

use App\Services\Distance\DistanceCalculator;

test('distance calculator returns zero for the same point', function () {
    $calculator = new DistanceCalculator;

    expect($calculator->miles(51.5074, -0.1278, 51.5074, -0.1278))->toBe(0.0);
});

test('distance calculator returns a realistic london to manchester distance', function () {
    $calculator = new DistanceCalculator;
    $distance = $calculator->miles(51.5074, -0.1278, 53.4808, -2.2426);

    expect($distance)->toBeGreaterThan(160)->toBeLessThan(170);
});
