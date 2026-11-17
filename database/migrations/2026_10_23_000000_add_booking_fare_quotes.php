<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        if (!Schema::hasColumn('ride_bookings', 'fare_per_seat')) Schema::table('ride_bookings', fn (Blueprint $t) => $t->decimal('fare_per_seat', 7, 2)->nullable());
        if (!Schema::hasColumn('ride_bookings', 'journey_distance_km')) Schema::table('ride_bookings', fn (Blueprint $t) => $t->decimal('journey_distance_km', 10, 3)->nullable());
        foreach (['distance_source', 'fare_policy'] as $column) {
            if (!Schema::hasColumn('ride_bookings', $column)) Schema::table('ride_bookings', fn (Blueprint $t) => $t->string($column, 50)->nullable());
        }
        // Existing bookings retain their original published contribution.
    }

    public function down() { /* Preserve quotes already agreed by passengers and drivers. */ }
};
