<?php

namespace App\Http\Controllers;

use App\Models\Ride;
use App\Services\DriverLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DriverLocationController extends Controller
{
    private function ownedRide(Request $request, int $rideId): Ride
    {
        $user = $request->user();
        abort_unless($user && $user->isActive() && $user->role === 'student' && $user->isDriver(), 403);
        return Ride::where('ride_id', $rideId)->where('driver_id', $user->user_id)->lockForUpdate()->firstOrFail();
    }

    private function json(array $data)
    {
        return response()->json($data)->header('Cache-Control', 'no-store, private');
    }

    public function start(Request $request, int $ride)
    {
        return DB::transaction(function () use ($request, $ride) {
            $owned = $this->ownedRide($request, $ride);
            abort_unless($owned->ride_status === 'in_progress', 409, 'Start the trip before sharing location.');
            $token = Str::random(40);
            $previous = DriverLocation::get($ride) ?? [];
            DriverLocation::save($ride, array_replace($previous, ['token' => $token, 'sharing' => true, 'gpsState' => 'waiting']));
            return $this->json(['token' => $token]);
        });
    }

    public function update(Request $request, int $ride)
    {
        return DB::transaction(function () use ($request, $ride) {
            $owned = $this->ownedRide($request, $ride);
            abort_unless($owned->ride_status === 'in_progress', 409, 'This trip is no longer active.');
            $data = $request->validate([
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'accuracy' => 'required|numeric|between:0,10000',
                'heading' => 'nullable|numeric|between:0,360',
                'capturedAt' => 'required|numeric',
            ]);
            $location = DriverLocation::get($ride);
            abort_unless($location && ($location['sharing'] ?? true) && hash_equals($location['token'], (string)$request->header('X-Tracking-Token')), 409, 'Location sharing stopped or restarted in another tab.');
            $timestamp = (float)$data['capturedAt'];
            abort_if($timestamp < now()->timestamp - 60 || $timestamp > now()->timestamp + 15, 422, 'A fresh GPS fix is required. Check the device clock.');
            abort_if(isset($location['capturedAt']) && $timestamp <= $location['capturedAt'], 422, 'This GPS fix is older than the last update.');
            DriverLocation::save($ride, $data + ['token' => $location['token'], 'receivedAt' => now()->timestamp, 'sharing' => true, 'gpsState' => 'live']);
            return $this->json(['sharing' => true]);
        });
    }

    public function stop(Request $request, int $ride)
    {
        return DB::transaction(function () use ($request, $ride) {
            $this->ownedRide($request, $ride);
            $location = DriverLocation::get($ride);
            abort_if($location && !hash_equals($location['token'], (string)$request->header('X-Tracking-Token')), 409, 'Sharing has restarted in another tab.');
            DriverLocation::forget($ride);
            return $this->json(['sharing' => false]);
        });
    }

    public function status(Request $request, int $ride)
    {
        return DB::transaction(function () use ($request, $ride) {
            $owned = $this->ownedRide($request, $ride);
            $location = DriverLocation::get($ride);
            return $this->json(['active' => $owned->ride_status === 'in_progress', 'sharing' => $owned->ride_status === 'in_progress' && $location && ($location['sharing'] ?? true) && hash_equals($location['token'], (string)$request->header('X-Tracking-Token'))]);
        });
    }

    public function report(Request $request, int $ride)
    {
        return DB::transaction(function () use ($request, $ride) {
            $owned = $this->ownedRide($request, $ride);
            abort_unless($owned->ride_status === 'in_progress', 409, 'This trip is no longer active.');
            $data = $request->validate(['state' => 'required|in:waiting,insecure_context,permission_denied,unavailable,unsupported,page_closed']);
            $location = DriverLocation::get($ride);
            abort_unless($location && hash_equals($location['token'], (string)$request->header('X-Tracking-Token')), 409);
            $location['gpsState'] = $data['state'];
            if ($data['state'] === 'page_closed') $location['sharing'] = false;
            DriverLocation::save($ride, $location);
            return $this->json(['reported' => true]);
        });
    }

    public function feed(Request $request)
    {
        abort_unless($request->user()?->isAdmin() && $request->user()->isActive(), 403);
        $rides = Ride::where('ride_status', 'in_progress')
            ->whereHas('driver', fn ($q) => $q->where('role', 'student')->where('account_type', 'driver')->where('account_status', 'active'))
            ->with('driver')->orderBy('ride_id')->get();
        $items = $rides->map(function ($ride) {
            $fix = DriverLocation::get($ride->ride_id);
            $hasFix = isset($fix['latitude'], $fix['longitude'], $fix['capturedAt']) && now()->timestamp - (float)$fix['capturedAt'] <= 600;
            $age = $hasFix ? max(0, now()->timestamp - (float)$fix['capturedAt']) : null;
            $known = RoutesController::getCampusLocations();
            $start = $ride->pickup_latitude !== null && $ride->pickup_longitude !== null
                ? ['lat' => (float)$ride->pickup_latitude, 'lng' => (float)$ride->pickup_longitude]
                : ($ride->origin_latitude !== null && $ride->origin_longitude !== null
                    ? ['lat' => (float)$ride->origin_latitude, 'lng' => (float)$ride->origin_longitude]
                    : ($known[$ride->pickup_point] ?? null));
            $gpsState = $fix['gpsState'] ?? ($hasFix ? 'live' : 'waiting');
            $messages = [
                'waiting' => 'GPS is starting. Waiting for a device location.',
                'insecure_context' => 'GPS blocked by HTTP. On this computer use http://localhost/workshop/driver.php; phones need HTTPS.',
                'permission_denied' => 'Location permission is blocked in the driver browser.',
                'unavailable' => 'The driver device could not obtain a GPS fix. Keep its location services on.',
                'unsupported' => 'The driver browser does not support device location.',
                'page_closed' => 'Driver page closed or left. Showing the last received location.',
                'live' => 'Receiving device GPS.',
            ];
            return [
                'id' => $ride->ride_id, 'driver' => $ride->driver->full_name,
                'vehicle' => trim(($ride->vehicle_color ?? '').' '.($ride->vehicle_type ?: $ride->vehicle_description)),
                'plate' => $ride->vehicle_plate, 'origin' => $ride->pickup_point, 'destination' => $ride->destination,
                'state' => $hasFix ? ($age <= 30 && $gpsState === 'live' && ($fix['sharing'] ?? true) ? 'live' : 'stale') : 'waiting',
                'gpsState' => $gpsState, 'gpsMessage' => $messages[$gpsState] ?? $messages['waiting'],
                'plannedStart' => $start ? ['lat' => (float)$start['lat'], 'lng' => (float)$start['lng']] : null,
                'location' => $hasFix ? [
                    'lat' => (float)$fix['latitude'], 'lng' => (float)$fix['longitude'],
                    'accuracy' => (float)$fix['accuracy'], 'heading' => isset($fix['heading']) ? (float)$fix['heading'] : null,
                    'capturedAt' => (float)$fix['capturedAt'], 'ageSeconds' => $age,
                ] : null,
            ];
        });
        return $this->json(['rides' => $items, 'serverNow' => now()->timestamp]);
    }
}
