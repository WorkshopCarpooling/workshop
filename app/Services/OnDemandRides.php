<?php

namespace App\Services;

use App\Models\{Ride, RideBooking, RideRequest, User, Vehicle};
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;

class OnDemandRides
{
    public static function ready(): bool
    {
        return Schema::hasTable('ride_requests') && Schema::hasColumn('rides', 'ride_mode') && StudentFare::storageReady();
    }

    public static function active(int $passenger): ?RideRequest
    {
        return RideRequest::where('passenger_id', $passenger)->where(function ($query) {
            $query->where(fn ($q) => $q->where('request_status', 'pending')->where('expires_at', '>', now()))
                ->orWhere(fn ($q) => $q->where('request_status', 'accepted')
                    ->whereHas('ride', fn ($r) => $r->whereIn('ride_status', ['scheduled', 'in_progress'])));
        })->with(['driver', 'ride'])->latest('request_id')->first();
    }

    public static function latest(int $passenger): ?RideRequest
    {
        $latest = self::active($passenger) ?? RideRequest::where('passenger_id', $passenger)
            ->when(Schema::hasColumn('ride_requests', 'scheduled_for'), fn ($q) => $q->whereNull('scheduled_for'))
            ->with(['driver', 'ride'])->latest('request_id')->first();
        return $latest && $latest->displayStatus() !== 'cancelled' ? $latest : null;
    }

    public static function create(int $passenger, array $journey, array $fare): RideRequest
    {
        return DB::transaction(function () use ($passenger, $journey, $fare) {
            // Serialize repeated submissions by this passenger, including two browser tabs.
            $user=User::whereKey($passenger)->lockForUpdate()->firstOrFail();
            abort_unless($user->isPassenger()&&$user->isActive(),403);
            if ($active = self::active($passenger)) return $active;
            return RideRequest::create($journey + $fare + [
                'passenger_id'=>$passenger, 'request_status'=>'pending', 'expires_at'=>now()->addMinutes(10),
            ]);
        });
    }

    public static function cancel(int $passenger, int $id): void
    {
        DB::transaction(function () use ($passenger, $id) {
            $request = RideRequest::whereKey($id)->where('passenger_id', $passenger)->lockForUpdate()->firstOrFail();
            if (!in_array($request->request_status, ['scheduled', 'pending', 'paused'], true)) {
                throw ValidationException::withMessages(['request'=>'A driver has already responded. Refresh to see your ride.']);
            }
            $request->update(['request_status'=>'cancelled']);
        });
    }

    public static function driverBusy(int $driver, ?int $exceptRide = null): bool
    {
        return Ride::where('driver_id', $driver)->when($exceptRide,fn($q)=>$q->where('ride_id','<>',$exceptRide))->where(function ($q) {
            $q->where('ride_status', 'in_progress')->orWhere(fn ($r) => $r->whereIn('ride_mode', ['now', 'appointment'])->where('ride_status', 'scheduled'));
        })->exists();
    }

    public static function accept(int $driver, int $id, int $vehicleId, array $poolOptions = []): Ride
    {
        return DB::transaction(function () use ($driver, $id, $vehicleId, $poolOptions) {
            $user = User::whereKey($driver)->lockForUpdate()->firstOrFail();
            if (!$user->isDriver() || !$user->isActive()) abort(403);
            $pool=SharedRidePool::current($driver);
            if(self::driverBusy($driver,$pool?->ride_id))throw ValidationException::withMessages(['request'=>'Finish your current ride before accepting another request.']);
            $vehicle = Vehicle::whereKey($vehicleId)->where('user_id', $driver)->where('verification_status', 'approved')->lockForUpdate()->first();
            if (!$vehicle) throw ValidationException::withMessages(['vehicle_id'=>'Choose one of your approved vehicles.']);
            $request = RideRequest::whereKey($id)->lockForUpdate()->firstOrFail();
            // Request-before-ride ordering also serializes acceptance against member removal.
            if($pool)$pool=Ride::whereKey($pool->ride_id)->lockForUpdate()->firstOrFail();
            if($request->request_status==='accepted'&&(int)$request->driver_id===$driver&&(int)$request->ride?->vehicle_id===$vehicleId)return $request->ride;
            if ($request->request_status !== 'pending' || $request->expires_at->lte(now())) {
                throw ValidationException::withMessages(['request'=>'This request was accepted, cancelled or expired. Refresh the request list.']);
            }
            $passenger = $request->passenger;
            if (!$passenger || !$passenger->isActive() || !$passenger->isPassenger() || $passenger->user_id === $driver) abort(403);
            if($pool) {
                if((int)$pool->vehicle_id!==$vehicleId||!SharedRidePool::compatible($pool,$request))throw ValidationException::withMessages(['request'=>'This request does not fit your open car. Check the destination, vehicle and seats, or finish your current trip.']);
                $pool->decrement('available_seats',$request->seats_requested);$ride=$pool;
            } else {
            $setup=(!$request->scheduled_for&&SharedRidePool::ready())?SharedRidePool::setup($poolOptions,(int)$request->seats_requested):[];
            $ride = Ride::create([
                'driver_id'=>$driver, 'vehicle_id'=>$vehicleId, 'ride_mode'=>$request->scheduled_for ? 'appointment' : 'now',
                'departure_at'=>$request->scheduled_for ?? now(),
                'pickup_point'=>$request->pickup_label, 'destination'=>$request->dropoff_label,
                'pickup_latitude'=>$request->pickup_latitude, 'pickup_longitude'=>$request->pickup_longitude,
                'dropoff_latitude'=>$request->dropoff_latitude, 'dropoff_longitude'=>$request->dropoff_longitude,
                'available_seats'=>isset($setup['pool_seat_capacity'])?(int)$setup['pool_seat_capacity']-$request->seats_requested:0, 'contribution_per_seat'=>$request->fare_per_seat,
                'vehicle_description'=>$vehicle->vehicle_type, 'vehicle_type'=>$vehicle->vehicle_type,
                'vehicle_color'=>$vehicle->vehicle_color, 'vehicle_plate'=>$vehicle->vehicle_plate, 'ride_status'=>'scheduled',
            ]+$setup);
            }
            RideBooking::create($request->only([
                'passenger_id', 'pickup_label', 'pickup_latitude', 'pickup_longitude', 'dropoff_label',
                'dropoff_latitude', 'dropoff_longitude', 'seats_requested', 'fare_per_seat',
                'journey_distance_km', 'distance_source', 'fare_policy',
            ]) + ['ride_id'=>$ride->ride_id, 'booking_status'=>'accepted']);
            $request->update(['driver_id'=>$driver, 'ride_id'=>$ride->ride_id, 'request_status'=>'accepted', 'accepted_at'=>now()]);
            return $ride;
        },3);
    }

    public static function feed(int $driver, ?array $origin = null)
    {
        RideAppointments::dispatchDue();
        if (!Vehicle::where('user_id', $driver)->where('verification_status', 'approved')->exists())return collect();
        $pool=SharedRidePool::current($driver);
        if(self::driverBusy($driver,$pool?->ride_id))return collect();
        $requests=RideRequest::where('request_status', 'pending')->where('expires_at', '>', now())
            ->where('passenger_id', '<>', $driver)
            ->whereHas('passenger', fn ($q) => $q->where('account_status', 'active')->where('account_type', 'passenger')->where('role', 'student'))
            ->when($pool,fn($q)=>$q->whereNull('scheduled_for')->where('seats_requested','<=',$pool->available_seats))
            ->with('passenger')->oldest('request_id')->get()
            ->filter(fn($request)=>!$pool||SharedRidePool::compatible($pool,$request));
        $origin=$pool?['lat'=>$pool->pool_origin_latitude,'lng'=>$pool->pool_origin_longitude]:$origin;
        if($origin)$requests=$requests->sortBy(fn($request)=>Journey::km($origin['lat'],$origin['lng'],$request->pickup_latitude,$request->pickup_longitude));
        return $requests->take(50)->values();
    }

    public static function payload(?RideRequest $request): ?array
    {
        if (!$request) return null;
        return [
            'id'=>$request->request_id, 'status'=>$request->displayStatus(), 'pickup'=>$request->pickup_label,
            'dropoff'=>$request->dropoff_label, 'seats'=>$request->seats_requested,
            'perSeat'=>$request->fare_per_seat, 'total'=>number_format((float)$request->fare_per_seat * $request->seats_requested, 2, '.', ''),
            'distanceKm'=>$request->journey_distance_km, 'distanceSource'=>$request->distance_source,
            'expiresAt'=>$request->expires_at->toIso8601String(), 'rideId'=>$request->ride_id,
            'scheduledFor'=>$request->scheduled_for?->toIso8601String(),
            'navigationUrl'=>$request->ride?->ride_status==='in_progress'&&TripNavigation::ready()?url('trips/'.$request->ride_id.'/map'):null,
            'driver'=>$request->driver?->full_name,
            'shared'=>SharedRidePool::passengerSummary($request),
            'vehicle'=>$request->ride ? trim($request->ride->vehicle_color.' '.$request->ride->vehicle_type).' · '.$request->ride->vehicle_plate : null,
        ];
    }
}
