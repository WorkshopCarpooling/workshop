<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\DriverLocationController;
use App\Http\Controllers\FindRideController;
use App\Http\Controllers\OnDemandRideController;
use App\Http\Controllers\RideAppointmentController;
use App\Http\Controllers\TimetableController;
use App\Http\Controllers\RoutesController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\CarbonController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    if (auth()->check()) {
        $user = auth()->user();
        if ($user->isAdmin()) return redirect('admin.php');
        if ($user->isDriver()) return redirect('driver.php');
        return redirect('dashboard.php');
    }
    return view('landing');
});

Route::view('/welcome', 'landing')->name('landing');

// Auth Routes (both .php and clean URLs)
Route::controller(AuthController::class)->group(function () {
    Route::get('login.php', 'showLogin')->name('login');
    Route::get('login', 'showLogin');
    Route::post('login.php', 'login');
    Route::post('login', 'login');

    Route::get('signup.php', 'showSignup')->name('signup');
    Route::get('signup', 'showSignup');
    Route::post('signup.php', 'signup');
    Route::post('signup', 'signup');

    Route::post('logout.php', 'logout')->name('logout');
    Route::post('logout', 'logout');
    // Also allow GET logout redirect if someone visits logout directly
    Route::get('logout.php', fn () => redirect('login.php'));
    Route::get('logout', fn () => redirect('login.php'));
});

// Commuter / Passenger Routes
Route::middleware(['auth', 'commuter'])->group(function () {
    // Dashboard (Passenger only)
    Route::middleware('passenger')->group(function () {
        Route::get('dashboard.php', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('dashboard', [DashboardController::class, 'index']);
        Route::get('passenger/schedule', [RideAppointmentController::class, 'index'])->name('passenger.schedule');
        Route::post('passenger/schedule', [RideAppointmentController::class, 'store'])->middleware('throttle:20,1');
        Route::get('api/passenger/appointments', [RideAppointmentController::class, 'status']);
        Route::post('timetable',[TimetableController::class,'store']);
        Route::post('timetable/{id}/remove',[TimetableController::class,'destroy'])->whereNumber('id');
        Route::get('timetable/{id}/ride',[TimetableController::class,'ride'])->whereNumber('id');
        Route::post('timetable/{id}/ride',[TimetableController::class,'confirm'])->whereNumber('id')->middleware('throttle:20,1');
        Route::post('timetable/{id}/weekly',[TimetableController::class,'toggle'])->whereNumber('id');
        Route::post('passenger/appointments/{id}/cancel', [RideAppointmentController::class, 'cancel'])->whereNumber('id');
        Route::get('passenger/{section}', [DashboardController::class, 'index'])->whereIn('section',['bookings','history']);
        Route::get('api/passenger/ride-request', [OnDemandRideController::class, 'status']);
        Route::post('passenger/ride-requests/{id}/cancel', [OnDemandRideController::class, 'cancel'])->whereNumber('id');

        Route::get('findride.php', [OnDemandRideController::class, 'index'])->middleware('throttle:30,1')->name('findride');
        Route::get('findride', [OnDemandRideController::class, 'index'])->middleware('throttle:30,1');
        Route::post('findride.php', [OnDemandRideController::class, 'store'])->middleware('throttle:20,1');
        Route::post('findride', [OnDemandRideController::class, 'store'])->middleware('throttle:20,1');
    });

    // Tracking preview (Both passenger and driver can preview)
    Route::get('tracking.php', [TrackingController::class, 'index'])->name('tracking');
    Route::get('tracking', [TrackingController::class, 'index']);
});

// Google Routes API endpoint
Route::middleware(['auth', 'throttle:60,1'])->post('api/routes/compute', [RoutesController::class, 'computeRoute'])->name('routes.compute');

// Driver Routes
Route::middleware(['auth', 'commuter', 'driver'])->group(function () {
    Route::get('driver.php', [DriverController::class, 'index'])->name('driver');
    Route::get('driver', [DriverController::class, 'index']);
    Route::get('api/driver/ride-requests', [OnDemandRideController::class, 'driverFeed']);
    Route::post('driver/ride-requests/accept', [OnDemandRideController::class, 'accept'])->middleware('throttle:20,1');
    Route::post('driver.php', [DriverController::class, 'handleAction']);
    Route::post('driver', [DriverController::class, 'handleAction']);
    Route::get('driver/{section}', [DriverController::class, 'index'])->whereIn('section',['requests','vehicles','offer','schedule','history']);
});

// Admin Routes
Route::middleware(['auth', 'throttle:60,1'])->group(function () {
    Route::post('api/driver/rides/{ride}/tracking/start', [DriverLocationController::class, 'start'])->whereNumber('ride');
    Route::post('api/driver/rides/{ride}/location', [DriverLocationController::class, 'update'])->whereNumber('ride');
    Route::delete('api/driver/rides/{ride}/location', [DriverLocationController::class, 'stop'])->whereNumber('ride');
    Route::get('api/driver/rides/{ride}/tracking', [DriverLocationController::class, 'status'])->whereNumber('ride');
    Route::post('api/driver/rides/{ride}/tracking', [DriverLocationController::class, 'report'])->whereNumber('ride');
    Route::get('api/admin/driver-locations', [DriverLocationController::class, 'feed']);
});

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('admin.php', [AdminController::class, 'index'])->name('admin');
    Route::get('admin', [AdminController::class, 'index']);
    Route::post('admin/members/{member}/remove',[\App\Http\Controllers\AdminMemberController::class,'remove'])->whereNumber('member');
    Route::post('admin/members/{member}/restore',[\App\Http\Controllers\AdminMemberController::class,'restore'])->whereNumber('member');
    Route::post('admin.php', [AdminController::class, 'handleAction']);
    Route::post('admin', [AdminController::class, 'handleAction']);
    Route::get('admin/{section}', [AdminController::class, 'index'])->whereIn('section',['live','rides','members','vehicles']);
});

// Journey tools share the authenticated application session and CSRF protection.
Route::middleware(['auth', 'commuter', 'passenger', 'throttle:30,1'])->get('api/passenger/reminders', [\App\Http\Controllers\PassengerReminderController::class, 'index']);
Route::middleware(['auth', 'throttle:60,1'])->get('api/locations/search', [\App\Http\Controllers\LocationController::class, 'search']);
Route::middleware(['auth', 'throttle:60,1'])->get('api/locations/reverse', [\App\Http\Controllers\LocationController::class, 'reverse']);
Route::middleware(['auth'])->group(function () {
    Route::get('timetable', [TimetableController::class, 'index'])->name('timetable');
});
Route::middleware(['auth'])->group(function () {
    Route::get('carbon', [CarbonController::class,'index'])->name('carbon');
    Route::middleware('admin')->get('carbon/export/monthly', [\App\Http\Controllers\CarbonToolsController::class,'export']);
    Route::middleware('admin')->post('carbon/prices', [\App\Http\Controllers\CarbonToolsController::class,'prices']);
    Route::middleware('commuter')->post('carbon/preferences', [\App\Http\Controllers\CarbonToolsController::class,'preferences']);
    Route::middleware('admin')->get('carbon/export', [CarbonController::class,'export']);
    Route::middleware(['commuter','passenger'])->post('carbon/bookings/{booking}/baseline', [CarbonController::class,'baseline'])->whereNumber('booking');
    Route::middleware(['commuter','driver'])->post('carbon/rides/{ride}/report', [CarbonController::class,'report'])->whereNumber('ride');
    Route::middleware(['commuter','driver'])->post('driver/vehicles/{vehicle}/fuel', [CarbonController::class,'vehicle'])->whereNumber('vehicle');
});
Route::middleware(['auth', 'commuter', 'driver'])->get('driver/route/{ride}', [\App\Http\Controllers\DriverRouteController::class, 'show']);

require __DIR__.'/navigation.php';
