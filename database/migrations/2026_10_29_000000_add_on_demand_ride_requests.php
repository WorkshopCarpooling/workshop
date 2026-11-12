<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        if (!Schema::hasColumn('rides', 'ride_mode')) {
            Schema::table('rides', fn (Blueprint $table) => $table->string('ride_mode', 20)->default('scheduled'));
        }
        if (Schema::hasTable('ride_requests')) return;
        $unsigned = function (string $table, string $column): bool {
            if (DB::connection()->getDriverName() !== 'mysql') return true;
            $type = DB::selectOne('SELECT COLUMN_TYPE AS column_type FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?', [DB::connection()->getDatabaseName(), $table, $column]);
            return stripos($type->column_type, 'unsigned') !== false;
        };
        $userUnsigned = $unsigned('users', 'user_id');
        $rideUnsigned = $unsigned('rides', 'ride_id');
        Schema::create('ride_requests', function (Blueprint $table) use ($userUnsigned, $rideUnsigned) {
            $table->increments('request_id');
            $table->integer('passenger_id')->unsigned($userUnsigned);
            $table->integer('driver_id')->unsigned($userUnsigned)->nullable();
            $table->integer('ride_id')->unsigned($rideUnsigned)->nullable();
            foreach (['pickup', 'dropoff'] as $point) {
                $table->string($point.'_label', 180);
                $table->decimal($point.'_latitude', 10, 7);
                $table->decimal($point.'_longitude', 10, 7);
            }
            $table->unsignedTinyInteger('seats_requested');
            $table->decimal('fare_per_seat', 7, 2);
            $table->decimal('journey_distance_km', 10, 3);
            $table->string('distance_source', 50);
            $table->string('fare_policy', 50);
            $table->string('request_status', 20)->default('pending');
            $table->dateTime('expires_at');
            $table->dateTime('accepted_at')->nullable();
            $table->timestamps();
            $table->index(['request_status', 'expires_at']);
            $table->index(['passenger_id', 'created_at']);
            $table->foreign('passenger_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('driver_id')->references('user_id')->on('users')->onDelete('set null');
            $table->foreign('ride_id')->references('ride_id')->on('rides')->onDelete('set null');
        });
    }

    public function down()
    {
        // Keep passenger requests and existing journey records.
    }
};
