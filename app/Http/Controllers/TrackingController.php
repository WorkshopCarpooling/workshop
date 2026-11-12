<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class TrackingController extends Controller
{
    public function index()
    {
        $currentUser = Auth::user();
        abort_unless($currentUser&&$currentUser->isActive()&&$currentUser->role==='student',403);
        $ride=\App\Models\Ride::where('ride_status','in_progress')->where(function($query)use($currentUser){
            if($currentUser->isDriver())$query->where('driver_id',$currentUser->user_id);
            else $query->whereHas('bookings',fn($b)=>$b->where('passenger_id',$currentUser->user_id)->where('booking_status','accepted'));
        })->orderByDesc('ride_id')->first();
        return redirect($ride?'trips/'.$ride->ride_id.'/map':($currentUser->isDriver()?'driver/schedule':'passenger/bookings'))->header('Cache-Control','no-store, private');
    }
}
