<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $unsignedRideId = true;
        if (DB::getDriverName() === 'mysql') {
            $rideId = DB::selectOne(
                'SELECT COLUMN_TYPE AS column_type FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
                ['rides', 'ride_id']
            );
            $unsignedRideId = str_contains($rideId->column_type, 'unsigned');
        }

        Schema::create('trip_assistance_reports', function (Blueprint $table) use ($unsignedRideId) {
            $table->id();
            $table->integer('ride_id')->unsigned($unsignedRideId);
            $table->foreign('ride_id')->references('ride_id')->on('rides')->cascadeOnDelete();
            $table->string('reason', 24);
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->string('position_source', 12)->nullable();
            $table->timestamp('position_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['ride_id', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_assistance_reports');
    }
};
