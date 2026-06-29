<?php

namespace App\Services;

use App\Models\SchoolLocation;

class GeoFenceService
{
    public function isInside(float $lat, float $lng, SchoolLocation $location): bool
    {
        return $this->distanceInMeters($lat, $lng, (float) $location->latitude, (float) $location->longitude) <= $location->radius_meter;
    }

    public function distanceInMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }
}
