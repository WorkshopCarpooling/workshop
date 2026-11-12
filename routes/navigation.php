<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TripNavigationController;
Route::middleware(['auth','admin','throttle:60,1'])->group(function(){
    Route::get('admin/live/preview/{ride?}',[\App\Http\Controllers\LivePreviewController::class,'show'])->whereNumber('ride');
    Route::get('api/admin/live/preview/{ride?}',[\App\Http\Controllers\LivePreviewController::class,'status'])->whereNumber('ride');
});
Route::middleware(['auth','throttle:60,1'])->group(function(){
    Route::get('trips/{ride}/map',[TripNavigationController::class,'show'])->whereNumber('ride');
    Route::get('api/trips/{ride}/navigation',[TripNavigationController::class,'status'])->whereNumber('ride');
    Route::post('trips/{ride}/stops',[TripNavigationController::class,'advance'])->whereNumber('ride');
    Route::get('trips/{ride}/test',[\App\Http\Controllers\TripTestController::class,'show'])->whereNumber('ride');
    Route::get('api/trips/{ride}/test-navigation',[\App\Http\Controllers\TripTestController::class,'status'])->whereNumber('ride');
    Route::post('trips/{ride}/test/stops',[\App\Http\Controllers\TripTestController::class,'advance'])->whereNumber('ride');
    Route::post('trips/{ride}/test/finish',[\App\Http\Controllers\TripTestController::class,'finish'])->whereNumber('ride');
});
