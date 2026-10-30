<?php

namespace App\Http\Controllers;

use App\Models\RideBooking;
use App\Services\PassengerOverview;
use App\Services\OnDemandRides;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(string $section = 'overview')
    {
        abort_unless(in_array($section,['overview','bookings','schedule','history'],true),404);
        $pageTitle=['overview'=>'Passenger overview','bookings'=>'My bookings','schedule'=>'My schedule','history'=>'Journey history'][$section];
        $currentUser = Auth::user();

        $allBookings = RideBooking::with(['ride.driver', 'pickupStop'])
            ->where('passenger_id', $currentUser->user_id)
            ->whereHas('ride')
            ->get();

        $visibleBookings = $section === 'history' ? $allBookings : $allBookings->reject(fn ($booking) => $booking->booking_status === 'cancelled' || $booking->ride->ride_status === 'cancelled');
        $myBookings = $visibleBookings->sortByDesc(fn ($b) => $b->ride->departure_at ?? $b->created_at)->take(20);
        $overview = PassengerOverview::fromBookings($allBookings);

        $bookingStats=['all'=>$myBookings->count(),'accepted'=>$myBookings->where('booking_status','accepted')->count(),'pending'=>$myBookings->where('booking_status','pending')->count()];
        if($section==='schedule') $myBookings=$myBookings->filter(fn($b)=>in_array($b->booking_status,['pending','accepted']) && in_array($b->ride->ride_status,['scheduled','in_progress']));
        if($section==='history') $myBookings=$myBookings->filter(fn($b)=>in_array($b->booking_status,['declined','cancelled']) || in_array($b->ride->ride_status,['completed','cancelled']));
        return view('dashboard', [
            'rideRequest'=>OnDemandRides::ready() ? OnDemandRides::payload(OnDemandRides::active((int)$currentUser->user_id)) : null,
            'overview' => $overview,
            'section'=>$section,'pageTitle'=>$pageTitle,'bookingStats'=>$bookingStats,'activePage'=>'passenger_'.$section,
            'currentUser' => $currentUser,
            'myBookings' => $myBookings,
            'passengerActivePage' => 'dashboard',
        ]);
    }
}
