<?php

namespace App\Http\Controllers;

use App\Services\PassengerReminders;
use App\Services\RideAppointments;
use Illuminate\Support\Facades\Auth;

class PassengerReminderController extends Controller
{
    public function index()
    {
        abort_unless(Auth::user()->isPassenger() && Auth::user()->isActive(), 403);
        RideAppointments::dispatchDue();
        return response()->json(['serverTime'=>now()->toIso8601String(), 'timezone'=>config('app.timezone'),
            'events'=>PassengerReminders::upcoming((int)Auth::id())])->header('Cache-Control', 'no-store, private');
    }
}
