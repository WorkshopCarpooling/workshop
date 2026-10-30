<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TripNavigationController;
Route::middleware(['auth','throttle:60,1'])->group(function(){
    Route::get('trips/{ride}/map',[TripNavigationController::class,'show'])->whereNumber('ride');
    Route::get('api/trips/{ride}/navigation',[TripNavigationController::class,'status'])->whereNumber('ride');
    Route::post('trips/{ride}/stops',[TripNavigationController::class,'advance'])->whereNumber('ride');
});
