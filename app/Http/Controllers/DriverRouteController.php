<?php

namespace App\Http\Controllers;

use App\Models\Ride;
use App\Services\RoutePlanner;
use App\Services\TravelTimePredictor;
use Illuminate\Support\Facades\Auth;

class DriverRouteController extends Controller
{
    public function show(int $ride)
    {
        $ride = Ride::where('ride_id', $ride)->where('driver_id', Auth::id())->with('bookings')->firstOrFail();
        $plan = (new RoutePlanner)->plan($ride);
        abort_unless($plan, 422, 'This ride has no usable location coordinates or its arrival constraints cannot be met.');
        $key = TravelTimePredictor::routeKey($plan['points']);
        $first = $plan['points'][0]; $last = end($plan['points']);
        $routeRequest = new \Illuminate\Http\Request([
            'origin_lat' => $first['lat'], 'origin_lng' => $first['lng'],
            'dest_lat' => $last['lat'], 'dest_lng' => $last['lng'],
            'intermediates' => array_slice($plan['points'], 1, -1),
            'departure_at' => $ride->departure_at->isFuture() ? $ride->departure_at->toIso8601String() : null,
        ]);
        $roadRoute = app(RoutesController::class)->computeRoute($routeRequest)->getData(true);
        $prediction = (new TravelTimePredictor)->predict($key, $roadRoute['distanceMeters'] / 1000, $ride->departure_at->format('Y-m-d H:i:s'));
        return view('driver-route', compact('ride', 'plan', 'key', 'prediction', 'roadRoute'));
    }
}
