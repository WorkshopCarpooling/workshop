<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        if (Schema::hasTable('driver_waiting_routes')) return;
        Schema::create('driver_waiting_routes', function (Blueprint $table) {
            $table->id();
            // IDs follow the existing installation without imposing new foreign-key types.
            $table->unsignedBigInteger('driver_id')->unique();
            $table->unsignedBigInteger('vehicle_id');
            $table->unsignedBigInteger('ride_id')->nullable();
            $table->uuid('version');
            foreach (['pickup', 'dropoff'] as $point) {
                $table->string($point.'_label', 180);
                $table->decimal($point.'_latitude', 10, 7);
                $table->decimal($point.'_longitude', 10, 7);
            }
            $table->timestamp('available_until')->index();
            $table->timestamps();
        });
    }
    public function down() { /* Preserve published driver routes. */ }
};
