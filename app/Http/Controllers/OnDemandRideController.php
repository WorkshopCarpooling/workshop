<?php

namespace App\Http\Controllers;

use App\Services\{FareQuote, Journey, OnDemandRides};
use App\Services\RideAppointments;
use App\Services\DriverWaitingRoutes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OnDemandRideController extends Controller
{
    private function journey(Request $request): array
    {
        return Journey::fields($request) + $request->validate([
            'seats_requested'=>'required|integer|between:1,3',
            'departure_at'=>'prohibited', 'arrive_by'=>'prohibited',
        ]);
    }

    public function index(Request $request)
    {
        $ready = OnDemandRides::ready();
        if ($request->expectsJson()) {
            $journey = $this->journey($request);
            $routeRequest = Request::create('/api/routes/compute', 'POST', [
                'origin_lat'=>$journey['pickup_latitude'], 'origin_lng'=>$journey['pickup_longitude'], 'origin_name'=>$journey['pickup_label'],
                'dest_lat'=>$journey['dropoff_latitude'], 'dest_lng'=>$journey['dropoff_longitude'], 'dest_name'=>$journey['dropoff_label'],
            ]);
            $trip = (new RoutesController)->computeRoute($routeRequest)->getData(true);
            return response()->json(['journey'=>$trip, 'fare'=>FareQuote::issueNow((int)Auth::id(), $journey, $trip), 'bookingReady'=>$ready, 'nearby'=>DriverWaitingRoutes::nearby((int)Auth::id(),$journey)])
                ->header('Cache-Control', 'no-store, private');
        }
        $active=$ready ? OnDemandRides::active((int)Auth::id()) : null;
        return response()->view('findride', [
            'currentUser'=>Auth::user(), 'bookingReady'=>$ready,
            'rideRequest'=>OnDemandRides::payload($active),
            'nearbyRides'=>$active?->displayStatus()==='pending'?DriverWaitingRoutes::nearby((int)Auth::id(),$active->only(['pickup_latitude','pickup_longitude','dropoff_latitude','dropoff_longitude','seats_requested'])):[],
        ])->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request)
    {
        abort_unless(OnDemandRides::ready(), 503, 'Ride requests are temporarily unavailable. Please try again later.');
        $journey = $this->journey($request);
        $data = $request->validate(['fare_quote'=>'required|string|max:4096']);
        $fare = FareQuote::verifyNow($data['fare_quote'], (int)Auth::id(), $journey);
        $rideRequest = OnDemandRides::create((int)Auth::id(), $journey, $fare);
        if ($request->expectsJson()) return response()->json(['request'=>OnDemandRides::payload($rideRequest)], 201)->header('Cache-Control', 'no-store, private');
        return redirect('findride.php')->with('bookingNotice', 'Your request is visible to approved campus drivers.');
    }

    public function status()
    {
        RideAppointments::dispatchDue();
        $latest=OnDemandRides::ready()?OnDemandRides::latest((int)Auth::id()):null;
        return response()->json(['request'=>OnDemandRides::payload($latest), 'nearby'=>$latest?->displayStatus()==='pending'?DriverWaitingRoutes::nearby((int)Auth::id(),$latest->only(['pickup_latitude','pickup_longitude','dropoff_latitude','dropoff_longitude','seats_requested'])):[]])
            ->header('Cache-Control', 'no-store, private');
    }

    public function cancel(Request $request, int $id)
    {
        abort_unless(OnDemandRides::ready(), 503);
        OnDemandRides::cancel((int)Auth::id(), $id);
        if ($request->expectsJson()) return $this->status();
        return redirect('findride.php')->with('bookingNotice', 'Your ride request was cancelled.');
    }

    public function driverFeed(?Request $httpRequest = null)
    {
        $httpRequest??=request();
        $location=$httpRequest->validate(['latitude'=>'nullable|required_with:longitude|numeric|between:-90,90','longitude'=>'nullable|required_with:latitude|numeric|between:-180,180']);
        $origin=isset($location['latitude'],$location['longitude'])?['lat'=>(float)$location['latitude'],'lng'=>(float)$location['longitude']]:null;
        $pool=\App\Services\SharedRidePool::current((int)Auth::id());
        $requests = OnDemandRides::ready() ? OnDemandRides::feed((int)Auth::id(),$origin)->map(fn ($request) => OnDemandRides::payload($request) + [
            'passenger'=>$request->passenger->full_name,
            'pickupDistanceKm'=>$origin?round(Journey::km($origin['lat'],$origin['lng'],$request->pickup_latitude,$request->pickup_longitude),2):null,
        ])->values() : collect();
        return response()->json(['requests'=>$requests,'waiting'=>DriverWaitingRoutes::summary(DriverWaitingRoutes::current((int)Auth::id())),'pool'=>$pool?['id'=>$pool->ride_id,'vehicleId'=>(int)$pool->vehicle_id,
            'capacity'=>(int)$pool->pool_seat_capacity,'remaining'=>$pool->available_seats,'destination'=>\App\Services\LocationLabel::short($pool->destination),
            'scheduleUrl'=>url('driver/schedule#ride-'.$pool->ride_id)]:null])->header('Cache-Control', 'no-store, private');
    }

    public function accept(Request $request)
    {
        abort_unless(OnDemandRides::ready(), 503);
        $data = $request->validate(['request_id'=>'required|integer', 'vehicle_id'=>'required|integer']);
        $ride = OnDemandRides::accept((int)Auth::id(), $data['request_id'], $data['vehicle_id'],$request->only(['pool_origin_latitude','pool_origin_longitude']));
        if(\App\Services\SharedRidePool::isPool($ride))return redirect('driver/requests#driverAcceptedTrips')->with('driver_notice','Passenger added. '.$ride->available_seats.' passenger seats left. Accept more requests for the same drop-off, or press Start trip below to open the driver map.')->with('driver_notice_type','success');
        return redirect('driver/requests#driverAcceptedTrips')->with('driver_notice', 'Ride accepted. Press Start trip to open the driver map and head to the pickup point.')->with('driver_notice_type', 'success');
    }
}
