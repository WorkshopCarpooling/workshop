<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CarbonRewardsController;

Route::middleware(['auth'])->group(function(){
    Route::get('carbon/rewards',[CarbonRewardsController::class,'index'])->name('carbon.rewards');
    Route::post('carbon/rewards/trips/{ride}/declaration',[CarbonRewardsController::class,'declare'])->whereNumber('ride')->middleware(['commuter','throttle:20,1']);
    Route::post('carbon/rewards/{reward}/redeem',[CarbonRewardsController::class,'redeem'])->whereNumber('reward')->middleware(['passenger','throttle:10,1']);
    Route::get('carbon/rewards/receipts/{receipt}',[CarbonRewardsController::class,'receipt'])->whereNumber('receipt')->middleware('passenger');
    Route::middleware('admin')->group(function(){
        Route::post('carbon/rewards/catalog',[CarbonRewardsController::class,'create']);
        Route::post('carbon/rewards/catalog/{reward}/settings',[CarbonRewardsController::class,'settings'])->whereNumber('reward');
        Route::post('carbon/rewards/catalog/{reward}/vouchers',[CarbonRewardsController::class,'vouchers'])->whereNumber('reward');
        Route::post('carbon/rewards/catalog/{reward}/availability',[CarbonRewardsController::class,'availability'])->whereNumber('reward');
    });
});
