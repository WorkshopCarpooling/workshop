<?php
require __DIR__ . '/../vendor/autoload.php';
$app=require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach(['commute_requirement_surveys','commute_timetable','travel_time_samples','usability_responses','rides','ride_bookings'] as $table) {
 echo $table.': '.implode(', ',Illuminate\Support\Facades\Schema::getColumnListing($table)).PHP_EOL;
}
