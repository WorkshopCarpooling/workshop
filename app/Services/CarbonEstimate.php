<?php

namespace App\Services;

use App\Models\Ride;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class CarbonEstimate
{
    public static function ready(): bool
    {
        return Schema::hasTable('ride_carbon_reports') && Schema::hasColumn('vehicles','fuel_type') && Schema::hasColumn('ride_bookings','baseline_mode');
    }

    public static function emissions(float $km, float $litresPer100, string $fuel): float
    {
        $factor = config('carbon.fuels.'.$fuel.'.factor');
        if (!$factor || !is_finite($km) || !is_finite($litresPer100) || $km < 0 || $km > 500 || $litresPer100 <= 0 || $litresPer100 > 50) {
            throw ValidationException::withMessages(['carbon'=>'Enter a supported fuel, a distance up to 500 km, and fuel consumption above 0 and at most 50 L/100 km.']);
        }
        return $km * $litresPer100 / 100 * $factor;
    }

    public static function profileRules(): array
    {
        return ['fuel_type'=>'required|in:'.implode(',',array_keys(config('carbon.fuels'))), 'fuel_l_per_100km'=>'required|numeric|between:0.1,50'];
    }

    public static function baseline(Request $request, int $seats): array
    {
        $data = validator($request->all(), [
            'baseline_mode'=>'nullable|in:'.implode(',',array_keys(config('carbon.modes'))),
            'baseline_cars'=>'nullable|integer|between:1,'.max(1,$seats),
            'baseline_fuel_type'=>'nullable|in:'.implode(',',array_keys(config('carbon.fuels'))),
            'baseline_fuel_l_per_100km'=>'nullable|numeric|between:0.1,50',
            'baseline_distance_km'=>'nullable|numeric|between:0.001,500',
        ])->validate();
        $mode = $data['baseline_mode'] ?? 'unknown';
        if ($mode === 'drive_alone') {
            foreach (['baseline_cars','baseline_fuel_type','baseline_fuel_l_per_100km'] as $key) {
                if (empty($data[$key])) throw ValidationException::withMessages([$key=>'For a separate-car baseline, provide the number of cars, fuel and consumption.']);
            }
        }
        return ['baseline_mode'=>$mode,
            'baseline_cars'=>$mode==='drive_alone' ? (int)$data['baseline_cars'] : null,
            'baseline_fuel_type'=>$mode==='drive_alone' ? $data['baseline_fuel_type'] : null,
            'baseline_fuel_l_per_100km'=>$mode==='drive_alone' ? (float)$data['baseline_fuel_l_per_100km'] : null,
            'baseline_distance_km'=>$mode==='drive_alone' && isset($data['baseline_distance_km']) ? (float)$data['baseline_distance_km'] : null];
    }

    public static function calculate(Ride $ride, array $data): array
    {
        $shared = self::emissions((float)$data['shared_km'], (float)$data['fuel_l_per_100km'], $data['fuel_type']);
        $driverKm = $data['driver_would_travel']==='yes' ? (float)$data['driver_baseline_km'] : 0;
        $baseline = self::emissions($driverKm, (float)$data['fuel_l_per_100km'], $data['fuel_type']);
        $complete = in_array($data['driver_would_travel'], ['yes','no'], true); $lines = [];
        foreach ($ride->bookings->where('booking_status','accepted') as $booking) {
            $mode = $booking->baseline_mode ?: 'unknown';
            $km=$booking->baseline_distance_km ?? $booking->journey_distance_km;
            $line = ['booking_id'=>$booking->booking_id, 'mode'=>$mode, 'cars'=>$booking->baseline_cars, 'km'=>$km,
                'distance_source'=>$booking->baseline_distance_km !== null ? 'passenger_reported' : $booking->distance_source, 'fuel'=>$booking->baseline_fuel_type, 'fuel_factor'=>config('carbon.fuels.'.$booking->baseline_fuel_type.'.factor'), 'litres_per_100km'=>$booking->baseline_fuel_l_per_100km, 'kg'=>null];
            if (in_array($mode,['walk_cycle','no_trip'],true)) $line['kg'] = 0;
            elseif ($mode==='drive_alone' && $km > 0 && $booking->baseline_cars >= 1 && $booking->baseline_cars <= $booking->seats_requested && $booking->baseline_fuel_l_per_100km > 0 && config('carbon.fuels.'.$booking->baseline_fuel_type.'.factor')) {
                $line['kg'] = self::emissions($km,$booking->baseline_fuel_l_per_100km,$booking->baseline_fuel_type) * $booking->baseline_cars;
                $baseline += $line['kg'];
            } else $complete = false;
            $lines[] = $line;
        }
        return ['shared_km'=>(float)$data['shared_km'], 'driver_baseline_km'=>$driverKm, 'distance_basis'=>$data['distance_basis'],
            'shared_kg'=>round($shared,6), 'baseline_kg'=>$complete ? round($baseline,6) : null,
            'avoided_kg'=>$complete ? round($baseline-$shared,6) : null, 'needs_review'=>false, 'method'=>config('carbon.method'),
            'details'=>json_encode(['fuel'=>$data['fuel_type'], 'litres_per_100km'=>(float)$data['fuel_l_per_100km'],
                'fuel_factor'=>config('carbon.fuels.'.$data['fuel_type'].'.factor'), 'driver_would_travel'=>$data['driver_would_travel'],
                'baseline_complete'=>$complete, 'bookings'=>$lines, 'source'=>config('carbon.source_title'), 'source_url'=>config('carbon.source_url')], JSON_THROW_ON_ERROR)];
    }

    public static function summary($rideIds = null): array
    {
        if (!self::ready()) return ['count'=>0,'avoided'=>null,'shared'=>null];
        $query=DB::table('ride_carbon_reports')->join('rides','rides.ride_id','=','ride_carbon_reports.ride_id')
            ->where('rides.ride_status','completed')->where('needs_review',false)->whereNotNull('avoided_kg');
        if ($rideIds !== null) $query->whereIn('rides.ride_id',$rideIds);
        return ['count'=>(clone $query)->count(),'avoided'=>(clone $query)->sum('avoided_kg'),'shared'=>(clone $query)->sum('shared_kg')];
    }
}
