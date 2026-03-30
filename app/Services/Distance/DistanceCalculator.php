<?php

namespace App\Services\Distance;

class DistanceCalculator
{
    /**
     * Calculate the great-circle distance between two coordinates in miles.
     */
    public function miles(float $fromLatitude, float $fromLongitude, float $toLatitude, float $toLongitude): float
    {
        $earthRadiusMiles = 3958.756;

        $deltaLatitude = deg2rad($toLatitude - $fromLatitude);
        $deltaLongitude = deg2rad($toLongitude - $fromLongitude);

        $a = sin($deltaLatitude / 2) ** 2
            + cos(deg2rad($fromLatitude)) * cos(deg2rad($toLatitude)) * sin($deltaLongitude / 2) ** 2;

        return 2 * $earthRadiusMiles * asin(min(1, sqrt($a)));
    }
}
