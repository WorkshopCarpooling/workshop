<?php

namespace App\Services;

use App\Models\Ride;
use Illuminate\Support\Facades\DB;

class AutomaticCarbon
{
    public static function inputs(Ride $ride,bool $forCompletion=false): ?array
    {
        if ($ride->ride_status !== 'completed') return null;
        $ride->loadMissing(['vehicle', 'bookings']);
        $vehicle = $ride->vehicle;
        if (!$vehicle || !config('carbon.fuels.'.$vehicle->fuel_type.'.factor') || !is_finite((float)$vehicle->fuel_l_per_100km) || $vehicle->fuel_l_per_100km <= 0 || $vehicle->fuel_l_per_100km > 50) return null;

        // Historical deadlines must not prevent estimating an already completed journey.
        // The planned route is an approximation, never a reconstruction of recorded GPS.
        $planned = clone $ride;
        $planned->setRelation('bookings', $ride->bookings->map(function ($booking) {
            $copy = clone $booking;
            $copy->arrive_by = null;
            return $copy;
        }));
        $planner = new RoutePlanner;
        $plan = $planner->plan($planned);
        $endpoints = $planner->endpoints($ride);
        if (!$plan || !$endpoints || $plan['distanceKm'] <= 0 || $plan['distanceKm'] > 500) return null;
        $originalKm = $planner->distance($endpoints);
        if ($originalKm <= 0 || $originalKm > 500) return null;

        $road=CarbonRoadDistance::estimate($ride,$plan['points'],$endpoints,$forCompletion);
        $preference=$forCompletion?CarbonPreferences::forUser((int)$ride->driver_id):null;
        return ['shared_km'=>$road['shared_km']??$plan['distanceKm'], 'driver_baseline_km'=>$road['driver_baseline_km']??round($originalKm, 3),
            'distance_basis'=>$road?'road_estimate':'planned_estimate', 'fuel_type'=>$vehicle->fuel_type,
            'fuel_l_per_100km'=>$vehicle->fuel_l_per_100km, 'driver_would_travel'=>$preference->driver_would_travel??'unknown',
            'distance_method'=>$road['method']??'Planned stop order; straight-line distance × 1.28. Not measured road distance or GPS history.'];
    }

    public static function preview(Ride $ride,bool $forCompletion=false): ?object
    {
        $inputs = self::inputs($ride,$forCompletion);
        if (!$inputs) return null;
        $record = CarbonEstimate::calculate($ride, $inputs);
        $details = json_decode($record['details'], true);
        $details['automatic'] = true;
        $details['suggested_driver_km'] = $inputs['driver_baseline_km'];
        $details['distance_method'] = $inputs['distance_method'];
        if($forCompletion&&$inputs['driver_would_travel']!=='unknown')$details['driver_preference_used']=true;
        $record['details'] = json_encode($details, JSON_THROW_ON_ERROR);
        return (object)($record + ['ride_id'=>$ride->ride_id, 'recorded_by'=>$ride->driver_id,
            'created_at'=>now(), 'updated_at'=>now(), 'preview'=>true]);
    }

    public static function recordCompleted(int $rideId): void
    {
        if (!CarbonEstimate::ready()) return;
        DB::transaction(function () use ($rideId) {
            $ride = Ride::where('ride_id', $rideId)->lockForUpdate()->with(['vehicle', 'bookings'])->first();
            if (!$ride || DB::table('ride_carbon_reports')->where('ride_id', $rideId)->exists()) return;
            $record = self::preview($ride,true);
            if (!$record) return;
            $values = (array)$record;
            unset($values['preview']);
            DB::table('ride_carbon_reports')->insert($values);
        });
    }

    public static function refreshComparison(Ride $ride): void
    {
        $report = DB::table('ride_carbon_reports')->where('ride_id', $ride->ride_id)->first();
        if (!$report) return;
        $details = json_decode($report->details, true);
        // Preserve the original fuel factors and method when baselines change.
        // If the configured factors changed, require an explicit new estimate instead.
        if (($details['fuel_factor'] ?? null) != config('carbon.fuels.'.$details['fuel'].'.factor') || $report->method !== config('carbon.method')) return;
        foreach ($details['bookings'] ?? [] as $line) {
            if ($line['fuel'] && ($line['fuel_factor'] ?? null) != config('carbon.fuels.'.$line['fuel'].'.factor')) return;
        }
        $record = CarbonEstimate::calculate($ride->load('bookings'), [
            'shared_km'=>$report->shared_km, 'driver_baseline_km'=>$report->driver_baseline_km,
            'distance_basis'=>$report->distance_basis, 'fuel_type'=>$details['fuel'],
            'fuel_l_per_100km'=>$details['litres_per_100km'], 'driver_would_travel'=>$details['driver_would_travel'],
        ]);
        $updated = json_decode($record['details'], true);
        foreach (['automatic','suggested_driver_km','distance_method','driver_preference_used'] as $key) if (isset($details[$key])) $updated[$key] = $details[$key];
        $record['details'] = json_encode($updated, JSON_THROW_ON_ERROR);
        DB::table('ride_carbon_reports')->where('ride_id', $ride->ride_id)->update($record + ['updated_at'=>now()]);
    }
}
