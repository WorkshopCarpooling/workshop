<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/** Latest GPS fix only; no journey history. Cache expires after ten minutes. */
class DriverLocation
{
    public static function key(int $rideId): string
    {
        return 'driver-location:v1:'.$rideId;
    }

    public static function get(int $rideId): ?array
    {
        return Cache::get(self::key($rideId));
    }

    public static function save(int $rideId, array $location): void
    {
        Cache::put(self::key($rideId), $location, now()->addMinutes(10));
    }

    public static function forget(int $rideId): void
    {
        Cache::forget(self::key($rideId));
    }
}
