<?php

namespace App\Services;

use Illuminate\Support\Facades\Schema;

class StudentFare
{
    public static function quote(float $km, ?float $driverCeiling = null, int $seats = 1): array
    {
        if (!is_finite($km) || $km < 0 || $seats < 1 || $seats > 3) throw new \InvalidArgumentException('Invalid fare distance or passenger count.');
        $policy = config('carpool.fare');
        $step = max(1, (int)$policy['round_to_sen']);
        $sen = (int)(round(($policy['base_sen'] + $km * $policy['per_km_sen']) / $step) * $step);
        $sen = max((int)$policy['minimum_sen'], min((int)$policy['maximum_sen'], $sen));
        if ($driverCeiling !== null) $sen = min($sen, max(0, (int)round($driverCeiling * 100)));
        return [
            'currency' => 'MYR', 'perSeat' => number_format($sen / 100, 2, '.', ''),
            'total' => number_format($sen * $seats / 100, 2, '.', ''), 'seats' => $seats,
            'distanceKm' => round($km, 3), 'policy' => 'student_distance_v1',
        ];
    }

    public static function description(): string
    {
        $p = config('carpool.fare');
        return sprintf('RM%.2f + RM%.2f/km · min RM%.2f · max RM%.2f per seat', $p['base_sen']/100, $p['per_km_sen']/100, $p['minimum_sen']/100, $p['maximum_sen']/100);
    }

    public static function storageReady(): bool
    {
        return Schema::hasColumns('ride_bookings', ['fare_per_seat', 'journey_distance_km', 'distance_source', 'fare_policy']);
    }
}
