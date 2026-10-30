<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

\Illuminate\Support\Facades\Artisan::command('commute:install-schema', function () {
    $migration = require database_path('migrations/2026_10_22_000000_add_journey_and_research_features.php');
    $migration->up();
    $fares = require database_path('migrations/2026_10_23_000000_add_booking_fare_quotes.php');
    $fares->up();
    (require database_path('migrations/2026_10_27_000000_add_carbon_estimates.php'))->up();
    $this->info('Journey and research schema ready. No records or seed data were inserted.');
})->purpose('Add journey columns and empty research tables without inserting any records');

Artisan::command('commute:install-fares', function () {
    $migration = require database_path('migrations/2026_10_23_000000_add_booking_fare_quotes.php');
    $migration->up();
    $this->info('Fare quote columns ready. Existing records were preserved.');
})->purpose('Add nullable fare quote columns to existing bookings');

Artisan::command('commute:install-carbon', function () {
    (require database_path('migrations/2026_10_27_000000_add_carbon_estimates.php'))->up();
    $this->info('Carbon schema ready. Existing trips and bookings were preserved; no estimates or sample records were invented.');
})->purpose('Add nullable fuel/baseline fields and an empty carbon snapshot table');
