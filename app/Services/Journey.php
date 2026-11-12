<?php

namespace App\Services;

use Illuminate\Http\Request;

class Journey
{
    public static function rules(): array
    {
        $rules = [];
        foreach (['pickup', 'dropoff'] as $point) {
            $rules[$point . '_label'] = 'required|string|max:180';
            $rules[$point . '_latitude'] = 'required|numeric|between:-90,90';
            $rules[$point . '_longitude'] = 'required|numeric|between:-180,180';
        }
        return $rules;
    }

    public static function fields(Request $request): array
    {
        $data = $request->validate(self::rules());
        foreach (['pickup', 'dropoff'] as $point) {
            $data[$point . '_label'] = trim($data[$point . '_label']);
            abort_if($data[$point . '_label'] === '', 422, 'Enter a location name.');
        }
        abort_if(self::km($data['pickup_latitude'], $data['pickup_longitude'], $data['dropoff_latitude'], $data['dropoff_longitude']) < 0.05, 422, 'Pickup and drop-off must be different locations.');
        return $data;
    }

    public static function km(float $a, float $b, float $c, float $d): float
    {
        $v = sin(deg2rad($c - $a) / 2) ** 2 + cos(deg2rad($a)) * cos(deg2rad($c)) * sin(deg2rad($d - $b) / 2) ** 2;
        return 6371 * 2 * asin(sqrt(min(1, max(0, $v))));
    }

    public static function minutes(array $a, array $b): float
    {
        // Explicit baseline heuristic, not a road routing or trained AI model.
        return max(1, self::km($a['lat'], $a['lng'], $b['lat'], $b['lng']) * 1.28 / 42 * 60);
    }
}
