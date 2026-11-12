<?php

namespace App\Http\Controllers;

use App\Models\Ride;
use App\Models\RideBooking;
use App\Services\Journey;
use App\Services\RideMatcher;
use App\Services\FareQuote;
use App\Services\StudentFare;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FindRideController extends Controller
{
    public function index(Request $request)
    {
        $journey = null;
        if ($request->filled('pickup_label')) {
            $journey = Journey::fields($request) + $request->validate([
                'departure_at' => 'nullable|date|after:now',
                'arrive_by' => 'nullable|date|after:now',
                'seats_requested' => 'required|integer|between:1,3',
            ]);
        }
        $trip = null;
        if ($journey) {
            $routeRequest = Request::create('/api/routes/compute', 'POST', [
                'origin_lat' => $journey['pickup_latitude'], 'origin_lng' => $journey['pickup_longitude'], 'origin_name' => $journey['pickup_label'],
                'dest_lat' => $journey['dropoff_latitude'], 'dest_lng' => $journey['dropoff_longitude'], 'dest_name' => $journey['dropoff_label'],
            ]);
            $trip = (new RoutesController)->computeRoute($routeRequest)->getData(true);
            $trip['fare'] = StudentFare::quote($trip['distanceMeters']/1000, null, (int)$journey['seats_requested']);
        }
        $bookingReady = StudentFare::storageReady();
        $rides = Ride::where('driver_id', '<>', Auth::id())
            ->where('ride_status', 'scheduled')->where('departure_at', '>', now())
            ->where('available_seats', '>', 0)
            ->whereHas('driver', fn ($q) => $q->where('role', 'student')->where('account_type', 'driver')->where('account_status', 'active'))
            ->with(['driver', 'bookings' => fn ($q) => $q->where('booking_status', 'accepted')])
            ->orderBy('departure_at')->get();
        $statuses = RideBooking::where('passenger_id', Auth::id())->whereIn('ride_id', $rides->pluck('ride_id'))->pluck('booking_status', 'ride_id');
        $availableRides = [];
        foreach ($rides as $ride) {
            $match = $journey ? (new RideMatcher)->match($ride, $journey) : null;
            if ($journey && !$match) continue;
            $availableRides[] = [
                'id' => $ride->ride_id, 'driver' => $ride->driver->full_name,
                'pickup' => $ride->pickup_point, 'destination' => $ride->destination,
                'departureAt' => $ride->departure_at->format('d M Y, g:i A'),
                'seats' => $ride->available_seats, 'cost' => number_format((float)$ride->contribution_per_seat, 2),
                'vehicle' => implode(' · ', array_filter([$ride->vehicle_type, $ride->vehicle_color])),
                'notes' => $ride->notes, 'bookingStatus' => $statuses[$ride->ride_id] ?? null,
                'match' => $match,
                'driverDistanceKm' => $match['driverDistanceKm'] ?? null,
                'fare' => $journey ? FareQuote::issue((int)Auth::id(), $ride, $journey, $trip) : null,
            ];
        }
        if ($journey) usort($availableRides, fn ($a, $b) => $a['driverDistanceKm'] <=> $b['driverDistanceKm'] ?: $b['match']['score'] <=> $a['match']['score'] ?: $a['id'] <=> $b['id']);
        if ($request->expectsJson()) return response()->json(['rides' => $availableRides, 'journey' => $trip, 'bookingReady' => $bookingReady])->header('Cache-Control', 'no-store, private');
        $timetable = Schema::hasTable('commute_timetable') ? DB::table('commute_timetable')->where('user_id', Auth::id())->orderBy('weekday')->orderBy('starts_at')->get() : collect();
        return response()->view('schedule', ['currentUser' => Auth::user(), 'availableRides' => $availableRides, 'timetable' => $timetable, 'bookingReady' => $bookingReady, 'bookingNotice' => session('bookingNotice', ''), 'passengerActivePage' => 'findride'])->header('Cache-Control', 'no-store, private');
    }

    public function requestSeat(Request $request)
    {
        $journey = Journey::fields($request);
        $data = $request->validate(['ride_id' => 'required|integer', 'seats_requested' => 'required|integer|between:1,3', 'departure_at' => 'nullable|date|after:now', 'arrive_by' => 'nullable|date|after:now', 'fare_quote' => 'required|string|max:4096']);
        if (!StudentFare::storageReady()) throw ValidationException::withMessages(['fare_quote' => 'Seat requests are temporarily unavailable. Please try again later.']);
        $baseline = $request->filled('baseline_mode') && \App\Services\CarbonEstimate::ready() ? \App\Services\CarbonEstimate::baseline($request,(int)$data['seats_requested']) : [];
        try {
            $message = DB::transaction(function () use ($data, $journey, $baseline) {
                $ride = Ride::where('ride_id', $data['ride_id'])->where('driver_id', '<>', Auth::id())
                    ->where('ride_status', 'scheduled')->where('departure_at', '>', now())
                    ->whereHas('driver', fn ($q) => $q->where('role', 'student')->where('account_status', 'active')->where('account_type', 'driver'))
                    ->lockForUpdate()->first();
                if (!$ride) return 'This ride is no longer available.';
                $existing = RideBooking::where('ride_id', $ride->ride_id)->where('passenger_id', Auth::id())->lockForUpdate()->first();
                if ($existing && in_array($existing->booking_status, ['pending', 'accepted'], true)) return 'You already have a request for this ride.';
                $match = (new RideMatcher)->match($ride, $journey + $data);
                if (!$match) return 'This journey no longer fits the available seats, route or arrival deadline. Please search again.';
                $fare = FareQuote::verify($data['fare_quote'], (int)Auth::id(), $ride, $journey + $data);
                $fields = $journey + $fare + $baseline + ['pickup_stop_id' => null, 'seats_requested' => $data['seats_requested'], 'arrive_by' => $data['arrive_by'] ?? null, 'compatibility_score' => $match['score'], 'booking_status' => 'pending'];
                if ($existing) $existing->update($fields);
                else RideBooking::create($fields + ['ride_id' => $ride->ride_id, 'passenger_id' => Auth::id()]);
                return 'Request sent at RM ' . $fare['fare_per_seat'] . ' per seat. The driver must confirm your seats and contribution.';
            });
        } catch (ValidationException $error) {
            throw $error;
        } catch (\Throwable $error) {
            report($error);
            $message = 'The request could not be saved. Please check that the journey migration has been installed and try again.';
        }
        return redirect('passenger/schedule')->with('bookingNotice', $message)->withInput();
    }
}
