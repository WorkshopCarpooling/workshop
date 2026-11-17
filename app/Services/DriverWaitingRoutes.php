<?php

namespace App\Services;

use App\Models\{DriverWaitingRoute, User, Vehicle};
use Illuminate\Support\Facades\{Cache, DB, Schema};
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

final class DriverWaitingRoutes
{
    public static function ready(): bool { return Schema::hasTable('driver_waiting_routes') && SharedRidePool::ready(); }

    public static function current(int $driver): ?DriverWaitingRoute
    {
        return self::ready() ? DriverWaitingRoute::where('driver_id', $driver)->first() : null;
    }

    public static function usable(DriverWaitingRoute $route, int $seats = 1, bool $requireOnline = true): bool
    {
        if (($requireOnline && $route->available_until->lte(now())) || !$route->driver?->isActive() || !$route->driver?->isDriver()
            || $route->vehicle?->verification_status !== 'approved' || (int)$route->vehicle?->user_id !== (int)$route->driver_id) return false;
        if ($route->ride_id) {
            if (!$route->ride || !SharedRidePool::isPool($route->ride) || $route->ride->ride_status !== 'scheduled'
                || $route->ride->available_seats < $seats || (int)$route->ride->vehicle_id !== (int)$route->vehicle_id) return false;
        } elseif ($seats > (int)config('carpool.instant_passenger_capacity', 3)) return false;
        return !OnDemandRides::driverBusy((int)$route->driver_id, $route->ride_id);
    }

    public static function open(int $driver, array $journey, int $vehicleId): DriverWaitingRoute
    {
        abort_unless(self::ready(), 503, 'Driver waiting routes are temporarily unavailable.');
        if (!CampusDriverArea::contains((float)$journey['pickup_latitude'], (float)$journey['pickup_longitude']))
            throw ValidationException::withMessages(['pickup_label'=>'Choose a starting point within the UTeM service area.']);
        return DB::transaction(function () use ($driver, $journey, $vehicleId) {
            $user = User::whereKey($driver)->lockForUpdate()->firstOrFail();
            abort_unless($user->isActive() && $user->isDriver(), 403);
            if (OnDemandRides::driverBusy($driver)) throw ValidationException::withMessages(['route'=>'Finish your current ride before opening another route.']);
            $vehicle = Vehicle::whereKey($vehicleId)->where('user_id', $driver)->where('verification_status', 'approved')->lockForUpdate()->first();
            if (!$vehicle) throw ValidationException::withMessages(['vehicle_id'=>'Choose one of your approved vehicles.']);
            return DriverWaitingRoute::updateOrCreate(['driver_id'=>$driver], $journey + [
                'vehicle_id'=>$vehicleId, 'ride_id'=>null,
                'version'=>(string)Str::uuid(),
                'available_until'=>now()->addSeconds((int)config('carpool.nearby.heartbeat_seconds', 90)),
            ]);
        }, 3);
    }

    public static function heartbeat(int $driver, int $id, string $version, bool $resume = false): ?DriverWaitingRoute
    {
        if (!self::ready()) return null;
        return DB::transaction(function () use ($driver, $id, $version, $resume) {
            User::whereKey($driver)->lockForUpdate()->firstOrFail();
            $route = DriverWaitingRoute::where('driver_id', $driver)->whereKey($id)->where('version',$version)->lockForUpdate()->first();
            // An expired/offline route must be reopened explicitly, never resurrected by a late timer.
            if (!$route || !self::usable($route, 1, !$resume)) return null;
            $route->update(['available_until'=>now()->addSeconds((int)config('carpool.nearby.heartbeat_seconds', 90))]);
            return $route;
        }, 3);
    }

    public static function close(int $driver, int $id, string $version): void
    {
        if (!self::ready()) return;
        DB::transaction(function () use ($driver, $id, $version) {
            User::whereKey($driver)->lockForUpdate()->firstOrFail();
            DriverWaitingRoute::where('driver_id', $driver)->whereKey($id)->where('version',$version)->update(['available_until'=>now()]);
        }, 3);
    }

    public static function summary(?DriverWaitingRoute $route): ?array
    {
        if (!$route) return null;
        return ['id'=>$route->id, 'version'=>$route->version, 'online'=>self::usable($route), 'canResume'=>self::usable($route,1,false), 'pickup'=>LocationLabel::short($route->pickup_label),
            'destination'=>LocationLabel::short($route->dropoff_label), 'vehicleId'=>(int)$route->vehicle_id,
            'expiresAt'=>$route->available_until->toIso8601String(), 'hasPassengers'=>$route->ride_id && in_array($route->ride?->ride_status,['scheduled','in_progress'],true)];
    }

    public static function match(DriverWaitingRoute $route, array $journey): ?array
    {
        if (!self::usable($route, (int)($journey['seats_requested'] ?? 1))) return null;
        if (Journey::km($route->dropoff_latitude, $route->dropoff_longitude, $journey['dropoff_latitude'], $journey['dropoff_longitude'])
            > (float)config('carpool.nearby.destination_km', .05)) return null;
        $origin = ['lat'=>$route->pickup_latitude, 'lng'=>$route->pickup_longitude];
        $pickup = ['lat'=>(float)$journey['pickup_latitude'], 'lng'=>(float)$journey['pickup_longitude']];
        $end = ['lat'=>$route->dropoff_latitude, 'lng'=>$route->dropoff_longitude];
        $points = $route->ride ? (SharedRidePool::plan($route->ride->loadMissing('bookings'))['points'] ?? null) : [$origin, $end];
        if (!$points) return null;
        $key='nearby-route-match:'.sha1(json_encode([$points,$pickup,config('carpool.nearby')]));
        return Cache::remember($key,60,function() use($points,$pickup) {
        $base = NavigationRoad::route($points); $best = null;
        for ($i=1; $i<count($points); $i++) {
            $proposed = $points; array_splice($proposed, $i, 0, [$pickup]);
            $road = NavigationRoad::route($proposed);
            // Never subtract a road distance from a geometric fallback (or vice versa).
            if (($base['road'] ?? false) !== ($road['road'] ?? false)) continue;
            $extraKm = max(0, ($road['distanceMeters']-$base['distanceMeters'])/1000);
            $extraMinutes = max(0, ($road['durationSeconds']-$base['durationSeconds'])/60) + 2;
            if ($extraKm > (float)config('carpool.nearby.max_extra_km', 2)
                || $extraMinutes > (float)config('carpool.nearby.max_extra_minutes', 5)) continue;
            if (!$best || $extraMinutes < $best['extraMinutes']) $best = [
                'extraKm'=>round($extraKm,2), 'extraMinutes'=>round($extraMinutes,1),
                'basis'=>($road['road'] ?? false) ? 'road_estimate' : 'approximate',
            ];
        }
        return $best;
        });
    }

    public static function nearby(int $passenger, array $journey): array
    {
        if (!self::ready()) return [];
        $routes = DriverWaitingRoute::where('available_until','>',now())->where('driver_id','<>',$passenger)
            ->whereBetween('dropoff_latitude',[$journey['dropoff_latitude']-.001,$journey['dropoff_latitude']+.001])
            ->whereBetween('dropoff_longitude',[$journey['dropoff_longitude']-.001,$journey['dropoff_longitude']+.001])
            ->with(['driver','vehicle','ride.bookings'])->orderByDesc('updated_at')->limit(20)->get()
            ->filter(fn($route)=>self::usable($route,(int)($journey['seats_requested']??1)))
            ->sortBy(fn($route)=>Journey::km($route->pickup_latitude,$route->pickup_longitude,$journey['pickup_latitude'],$journey['pickup_longitude']))->take(4);
        $matches = [];
        foreach ($routes as $route) if ($match = self::match($route, $journey)) $matches[] = $match + [
            'id'=>$route->id, 'driver'=>$route->driver->full_name,
            'pickup'=>LocationLabel::short($route->pickup_label), 'destination'=>LocationLabel::short($route->dropoff_label),
            'seats'=>$route->ride ? (int)$route->ride->available_seats : (int)config('carpool.instant_passenger_capacity',3),
        ];
        usort($matches, fn($a,$b)=>$a['extraMinutes']<=>$b['extraMinutes'] ?: $a['id']<=>$b['id']);
        // No driver's coordinates, vehicle plate, or other passengers' pins are exposed.
        return array_slice($matches, 0, 5);
    }
}
