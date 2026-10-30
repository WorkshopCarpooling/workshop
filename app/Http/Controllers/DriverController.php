<?php

namespace App\Http\Controllers;

use App\Models\CampusRoute;
use App\Models\CampusRouteStop;
use App\Models\Ride;
use App\Models\RideBooking;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;
use App\Services\Journey;
use App\Services\RideMatcher;
use App\Services\RoutePlanner;
use App\Services\OnDemandRides;

class DriverController extends Controller
{
    public function index(string $section = 'overview')
    {
        abort_unless(in_array($section,['overview','requests','vehicles','offer','schedule','history'],true),404);
        $pageTitle = ['overview'=>'Driver overview','requests'=>'Ride requests','vehicles'=>'My vehicles','offer'=>'Offer a ride','schedule'=>'My schedule','history'=>'Trip history'][$section];
        $currentUser = Auth::user();
        $onDemandReady = OnDemandRides::ready();

        $campusRoutes = CampusRoute::where('is_active', true)->with('stops')->orderBy('route_id')->get();
        $routeStopsByRoute = [];
        foreach ($campusRoutes as $route) {
            $routeStopsByRoute[$route->route_id] = $route->stops;
        }

        $rides = Ride::where('driver_id', $currentUser->user_id)
            ->with(['pickupStops', 'bookings'])
            ->orderByDesc('departure_at')
            ->get();

        $activeRides = $rides->where('ride_status','in_progress')->values();
        $rideStats = ['scheduled'=>$rides->where('ride_status','scheduled')->count(),'active'=>$activeRides->count(),'completed'=>$rides->where('ride_status','completed')->count()];
        if ($section === 'history') $rides=$rides->whereIn('ride_status',['completed','cancelled'])->values();
        elseif ($section === 'schedule') $rides=$rides->whereIn('ride_status',['scheduled','in_progress'])->values();
        else $rides=collect();
        $rideStopsByRide = [];
        foreach ($rides as $ride) {
            $rideStopsByRide[$ride->ride_id] = $ride->pickupStops->pluck('stop_name')->toArray();
        }

        $vehicles = Vehicle::where('user_id', $currentUser->user_id)
            ->orderByDesc('created_at')
            ->get();

        $approvedVehicles = $vehicles->where('verification_status', 'approved')->values();

        $bookingRequests = RideBooking::whereHas('ride', fn ($q) => $q->where('driver_id', $currentUser->user_id)->when($onDemandReady, fn ($ride) => $ride->where('ride_mode', 'scheduled')))
            ->with(['ride', 'passenger', 'pickupStop'])
            ->orderByDesc('created_at')
            ->get();

        $pendingRequestCount = $bookingRequests->where('booking_status', 'pending')->count();

        $notice = session('driver_notice', '');
        $noticeType = session('driver_notice_type', '');

        return view('driver', [
            'onDemandReady'=>$onDemandReady,
            'section'=>$section, 'pageTitle'=>$pageTitle, 'activePage'=>'driver_'.$section, 'activeRides'=>$activeRides, 'rideStats'=>$rideStats,
            'currentUser' => $currentUser,
            'campusRoutes' => $campusRoutes,
            'routeStopsByRoute' => $routeStopsByRoute,
            'rides' => $rides,
            'rideStopsByRide' => $rideStopsByRide,
            'vehicles' => $vehicles,
            'approvedVehicles' => $approvedVehicles,
            'bookingRequests' => $bookingRequests,
            'pendingRequestCount' => $pendingRequestCount,
            'notice' => $notice,
            'noticeType' => $noticeType,
            'passengerActivePage' => 'driver',
            'routePlans' => $rides->mapWithKeys(fn ($ride) => [$ride->ride_id => (new RoutePlanner)->plan($ride)]),
        ]);
    }

    public function handleAction(Request $request)
    {
        $action = $request->input('action');

        return match ($action) {
            'submit_vehicle' => $this->submitVehicle($request),
            'create_ride' => $this->createRide($request),
            'accept_booking', 'decline_booking' => $this->handleBooking($request),
            'update_status' => $this->updateRideStatus($request),
            default => redirect('driver.php')->with('driver_notice', 'That action is not valid.')->with('driver_notice_type', 'error'),
        };
    }

    protected function submitVehicle(Request $request)
    {
        $currentUser = Auth::user();

        $vehicleType = trim((string) $request->input('vehicle_type', ''));
        $vehicleColor = trim((string) $request->input('vehicle_color', ''));
        $vehiclePlate = strtoupper(trim((string) $request->input('vehicle_plate', '')));

        if ($vehicleType === '' || mb_strlen($vehicleType) > 80 || $vehicleColor === '' || mb_strlen($vehicleColor) > 60 || $vehiclePlate === '' || mb_strlen($vehiclePlate) > 20) {
            return redirect('driver/vehicles')->with('driver_notice', 'Enter a valid car model, colour, and number plate.')->with('driver_notice_type', 'error');
        }

        $existingVehicle = Vehicle::where('user_id', $currentUser->user_id)
            ->where('vehicle_plate', $vehiclePlate)
            ->first();

        $fuelProfile = \App\Services\CarbonEstimate::ready() && $request->filled('fuel_type')
            ? $request->validate(\App\Services\CarbonEstimate::profileRules()) : [];

        if ($existingVehicle && $existingVehicle->verification_status !== 'rejected') {
            $msg = $existingVehicle->verification_status === 'approved'
                ? 'This vehicle is already approved.'
                : 'This vehicle is already waiting for admin review.';
            return redirect('driver/vehicles')->with('driver_notice', $msg)->with('driver_notice_type', 'error');
        }

        if ($existingVehicle) {
            $existingVehicle->update([
                'vehicle_type' => $vehicleType,
                'vehicle_color' => $vehicleColor,
                'verification_status' => 'pending',
                'admin_note' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ] + $fuelProfile);
            return redirect('driver/vehicles')->with('driver_notice', 'Vehicle details updated and sent for admin review.')->with('driver_notice_type', 'success');
        }

        Vehicle::create([
            'user_id' => $currentUser->user_id,
            'vehicle_type' => $vehicleType,
            'vehicle_color' => $vehicleColor,
            'vehicle_plate' => $vehiclePlate,
            'verification_status' => 'pending',
        ] + $fuelProfile);

        return redirect('driver/vehicles')->with('driver_notice', 'Vehicle details sent. An administrator must approve them before you can publish a ride.')->with('driver_notice_type', 'success');
    }

    protected function createRide(Request $request)
    {
        $currentUser = Auth::user();

        $journey = Journey::fields($request);

        $departure = trim((string) $request->input('departure_at', ''));
        $seats = filter_var($request->input('available_seats'), FILTER_VALIDATE_INT);
        $contribution = filter_var($request->input('contribution_per_seat'), FILTER_VALIDATE_FLOAT);
        $vehicleId = filter_var($request->input('vehicle_id'), FILTER_VALIDATE_INT);
        $notes = trim((string) $request->input('notes', ''));
        $departureTimestamp = strtotime($departure);

        $vehicle = Vehicle::where('vehicle_id', $vehicleId)
            ->where('user_id', $currentUser->user_id)
            ->where('verification_status', 'approved')
            ->first();

        if (!$departureTimestamp || $departureTimestamp <= time()) {
            return redirect('driver.php')->with('driver_notice', 'Choose a departure date and time in the future.')->with('driver_notice_type', 'error');
        }

        if (!$seats || $seats < 1 || $seats > 6) {
            return redirect('driver.php')->with('driver_notice', 'Choose between 1 and 6 available seats.')->with('driver_notice_type', 'error');
        }

        if ($contribution === false || $contribution < 0 || $contribution > 9999.99) {
            return redirect('driver.php')->with('driver_notice', 'Enter a valid contribution per seat.')->with('driver_notice_type', 'error');
        }

        if (!$vehicle) {
            return redirect('driver.php')->with('driver_notice', 'Choose one of your admin-approved vehicles before publishing a ride.')->with('driver_notice_type', 'error');
        }

        if (mb_strlen($notes) > 500) {
            return redirect('driver.php')->with('driver_notice', 'Keep trip notes under 500 characters.')->with('driver_notice_type', 'error');
        }

        try {
            DB::beginTransaction();

            $ride = Ride::create([
                'driver_id' => $currentUser->user_id,
                'vehicle_id' => $vehicle->vehicle_id,
                'route_id' => null,
                'pickup_point' => $journey['pickup_label'],
                'destination' => $journey['dropoff_label'],
                'pickup_latitude' => $journey['pickup_latitude'],
                'pickup_longitude' => $journey['pickup_longitude'],
                'dropoff_latitude' => $journey['dropoff_latitude'],
                'dropoff_longitude' => $journey['dropoff_longitude'],
                'departure_at' => date('Y-m-d H:i:s', $departureTimestamp),
                'available_seats' => $seats,
                'contribution_per_seat' => number_format((float) $contribution, 2, '.', ''),
                'vehicle_description' => $vehicle->vehicle_type,
                'vehicle_type' => $vehicle->vehicle_type,
                'vehicle_color' => $vehicle->vehicle_color,
                'vehicle_plate' => $vehicle->vehicle_plate,
                'notes' => $notes !== '' ? $notes : null,
                'ride_status' => 'scheduled',
            ]);


            DB::commit();

            return redirect('driver/schedule')->with('driver_notice', 'Your scheduled ride is published with your pickup and drop-off locations.')->with('driver_notice_type', 'success');
        } catch (Throwable $error) {
            DB::rollBack();
            report($error);
            return redirect('driver.php')->with('driver_notice', 'Could not publish the ride. Please try again.')->with('driver_notice_type', 'error');
        }
    }

    protected function handleBooking(Request $request)
    {
        $currentUser = Auth::user();
        $bookingId = filter_var($request->input('booking_id'), FILTER_VALIDATE_INT);
        $action = $request->input('action');

        if (!$bookingId) {
            return redirect('driver/requests')->with('driver_notice', 'That seat request could not be found.')->with('driver_notice_type', 'error');
        }

        try {
            DB::beginTransaction();

            $rideId = RideBooking::where('booking_id', $bookingId)->value('ride_id');
            $ride = Ride::where('ride_id', $rideId)->where('driver_id', $currentUser->user_id)
                ->where('ride_status', 'scheduled')->where('departure_at', '>', now())->lockForUpdate()->first();
            $booking = $ride ? RideBooking::where('booking_id', $bookingId)->where('ride_id', $ride->ride_id)->lockForUpdate()->first() : null;

            if (!$booking || $booking->booking_status !== 'pending') {
                DB::rollBack();
                return redirect('driver/requests')->with('driver_notice', 'This request has already been handled or the ride is no longer available.')->with('driver_notice_type', 'error');
            }

            if ($action === 'accept_booking') {
                $journey = $booking->only(['pickup_label', 'pickup_latitude', 'pickup_longitude', 'dropoff_label', 'dropoff_latitude', 'dropoff_longitude', 'seats_requested']);
                $journey['arrive_by'] = $booking->arrive_by ? $booking->arrive_by->format('Y-m-d H:i:s') : null;
                if ($booking->dropoff_latitude !== null && !(new RideMatcher)->match($ride, $journey)) {
                    DB::rollBack();
                    return redirect('driver/requests')->with('driver_notice', 'This request no longer fits the route or arrival deadline.')->with('driver_notice_type', 'error');
                }
                if ((int) $ride->available_seats < (int) $booking->seats_requested) {
                    DB::rollBack();
                    return redirect('driver/requests')->with('driver_notice', 'There are not enough seats left to accept this request.')->with('driver_notice_type', 'error');
                }

                $ride->decrement('available_seats', $booking->seats_requested);
                $booking->update(['booking_status' => 'accepted']);

                DB::commit();
                return redirect('driver/requests')->with('driver_notice', 'Seat request accepted. The available seat count has been updated.')->with('driver_notice_type', 'success');
            } else {
                $booking->update(['booking_status' => 'declined']);
                DB::commit();
                return redirect('driver/requests')->with('driver_notice', 'Seat request declined.')->with('driver_notice_type', 'success');
            }
        } catch (Throwable $error) {
            DB::rollBack();
            report($error);
            return redirect('driver/requests')->with('driver_notice', 'Could not update the seat request. Please try again.')->with('driver_notice_type', 'error');
        }
    }

    protected function updateRideStatus(Request $request)
    {
        $currentUser = Auth::user();
        $rideId = filter_var($request->input('ride_id'), FILTER_VALIDATE_INT);
        $nextStatus = (string) $request->input('next_status', '');

        $allowedTransitions = [
            'in_progress' => ['scheduled'],
            'completed' => ['in_progress'],
            'cancelled' => ['scheduled'],
        ];

        if (!$rideId || !isset($allowedTransitions[$nextStatus])) {
            return redirect('driver/schedule')->with('driver_notice', 'That ride action is not valid.')->with('driver_notice_type', 'error');
        }

        $ride = Ride::where('ride_id', $rideId)
            ->where('driver_id', $currentUser->user_id)
            ->where('ride_status', $allowedTransitions[$nextStatus][0])
            ->first();

        if (!$ride) {
            return redirect('driver/schedule')->with('driver_notice', 'The ride status has changed or the ride was not found.')->with('driver_notice_type', 'error');
        }

        $changes = ['ride_status' => $nextStatus];
        if ($nextStatus === 'in_progress') $changes['started_at'] = now();
        if ($nextStatus === 'completed') $changes['completed_at'] = now();
        $updated = DB::transaction(function () use ($rideId, $currentUser, $allowedTransitions, $nextStatus, $changes) {
            // Share the driver's lock with instant acceptance; starting closes pooling atomically.
            \App\Models\User::whereKey($currentUser->user_id)->lockForUpdate()->firstOrFail();
            $locked = Ride::where('ride_id', $rideId)->where('driver_id', $currentUser->user_id)->lockForUpdate()->first();
            if (!$locked || $locked->ride_status !== $allowedTransitions[$nextStatus][0]) return false;
            if($nextStatus==='in_progress'&&Ride::where('driver_id',$currentUser->user_id)->where('ride_status','in_progress')->where('ride_id','<>',$rideId)->exists())
                throw \Illuminate\Validation\ValidationException::withMessages(['ride'=>'Finish your active trip before starting another pickup route.']);
            if($nextStatus==='in_progress'&&\App\Services\SharedRidePool::isPool($locked)
                &&!(new RoutePlanner)->plan($locked->load('bookings')))return false;
            if($nextStatus==='in_progress'&&\App\Services\TripNavigation::ready()){
                $plan=\App\Services\TripNavigation::stops($locked->load('bookings'));
                if($plan)$changes['navigation_plan']=$plan;
            }
            $locked->update($changes);
            if (in_array($nextStatus, ['completed', 'cancelled'], true)) \App\Services\DriverLocation::forget($rideId);
            if (in_array($nextStatus, ['cancelled', 'in_progress'], true)) {
                $locked->bookings()->where('booking_status', 'pending')->update(['booking_status' => 'cancelled']);
            }
            if ($nextStatus === 'cancelled') $locked->bookings()->where('booking_status', 'accepted')->update(['booking_status' => 'cancelled']);
            return true;
        },3);

        if (!$updated) return redirect('driver/schedule')->with('driver_notice', 'The ride status changed. Refresh and check the trip.')->with('driver_notice_type', 'error');
        if ($nextStatus === 'completed') {
            try { \App\Services\AutomaticCarbon::recordCompleted($rideId); }
            catch (Throwable $error) { report($error); } // Carbon must never undo completion of a trip.
        }
        $response = redirect($nextStatus==='in_progress'&&\App\Services\TripNavigation::ready()?'trips/'.$rideId.'/map':'driver/schedule#ride-'.$rideId)->with('driver_notice_type', 'success');
        return $response->with('driver_notice', $nextStatus === 'in_progress' ? 'Trip started. Live GPS starts automatically. Allow browser location access if asked and keep this page open.' : 'Ride status updated.');
    }
}
