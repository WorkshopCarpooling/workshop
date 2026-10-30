<?php

// DDL only. Records the existing counts, applies the empty schema, then verifies no records changed.
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$tables = array_map(fn ($row) => array_values((array)$row)[0], Illuminate\Support\Facades\DB::select('SHOW TABLES'));
$before = [];
foreach ($tables as $table) $before[$table] = Illuminate\Support\Facades\DB::table($table)->count();
Illuminate\Support\Facades\Artisan::call('commute:install-schema');
echo Illuminate\Support\Facades\Artisan::output();
foreach ($tables as $table) {
    $after = Illuminate\Support\Facades\DB::table($table)->count();
    if ($before[$table] !== $after) { fwrite(STDERR,"Unexpected record-count change in $table.\n");exit(1); }
    echo "$table: record count unchanged ($after).\n";
}
foreach (['commute_timetable','commute_requirement_surveys','travel_time_samples','usability_responses'] as $table) {
    echo "$table: " . Illuminate\Support\Facades\DB::table($table)->count() . " records.\n";
}
