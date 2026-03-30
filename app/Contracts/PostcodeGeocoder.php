<?php

namespace App\Contracts;

interface PostcodeGeocoder
{
    /**
     * Resolve a UK postcode to latitude and longitude.
     *
     * @return array{postcode: string, latitude: float, longitude: float}|null
     */
    public function geocode(string $postcode): ?array;
}
