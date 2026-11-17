<?php
namespace App\Services;

use App\Models\{Ride, RideBooking, User};

final class RidePageState {
    public static function version(User $user): string {
        if ($user->isDriver()) {
            $rows = Ride::where('driver_id', $user->user_id)->whereIn('ride_status', ['scheduled', 'in_progress'])
                ->with(['bookings'=>fn($q)=>$q->orderBy('booking_id')->select('booking_id','ride_id','booking_status')])
                ->orderBy('ride_id')->get(['ride_id','ride_status']);
        } else {
            $rows = RideBooking::where('passenger_id', $user->user_id)->whereIn('booking_status', ['pending', 'accepted'])
                ->whereHas('ride', fn($q)=>$q->whereIn('ride_status', ['scheduled', 'in_progress']))
                ->with('ride:ride_id,ride_status')->orderBy('booking_id')->get(['booking_id','ride_id','booking_status']);
        }
        return hash('sha256', $rows->toJson());
    }
}
