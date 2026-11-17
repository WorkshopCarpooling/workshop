<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->increments('user_id');
                $table->string('full_name', 100);
                $table->string('email', 150)->unique();
                $table->string('password_hash', 255);
                $table->string('phone_number', 20)->nullable();
                $table->enum('role', ['student', 'staff', 'admin'])->default('student');
                $table->enum('account_type', ['passenger', 'driver'])->default('passenger');
                $table->enum('gender', ['male', 'female', 'other'])->nullable();
                $table->boolean('email_verified')->default(false);
                $table->enum('account_status', ['pending', 'active', 'suspended'])->default('pending');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('vehicles')) {
            Schema::create('vehicles', function (Blueprint $table) {
                $table->increments('vehicle_id');
                $table->unsignedInteger('user_id');
                $table->string('vehicle_type', 80);
                $table->string('vehicle_color', 60);
                $table->string('vehicle_plate', 20);
                $table->enum('verification_status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->string('admin_note', 255)->nullable();
                $table->unsignedInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
                $table->foreign('reviewed_by')->references('user_id')->on('users')->onDelete('set null');
                $table->unique(['user_id', 'vehicle_plate'], 'uq_vehicles_user_plate');
                $table->index(['verification_status', 'created_at'], 'idx_vehicles_review');
            });
        }

        if (!Schema::hasTable('campus_routes')) {
            Schema::create('campus_routes', function (Blueprint $table) {
                $table->increments('route_id');
                $table->string('route_code', 50)->unique();
                $table->string('route_name', 150);
                $table->enum('direction', ['to_campus', 'from_campus']);
                $table->string('origin_name', 150);
                $table->string('destination_name', 150);
                $table->boolean('is_active')->default(true);
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('campus_route_stops')) {
            Schema::create('campus_route_stops', function (Blueprint $table) {
                $table->increments('stop_id');
                $table->unsignedInteger('route_id');
                $table->unsignedTinyInteger('stop_order');
                $table->string('stop_name', 150);
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('route_id')->references('route_id')->on('campus_routes')->onDelete('cascade');
                $table->unique(['route_id', 'stop_order'], 'uq_route_stop_order');
                $table->unique(['route_id', 'stop_name'], 'uq_route_stop_name');
            });
        }

        if (!Schema::hasTable('rides')) {
            Schema::create('rides', function (Blueprint $table) {
                $table->increments('ride_id');
                $table->unsignedInteger('driver_id');
                $table->unsignedInteger('vehicle_id')->nullable();
                $table->unsignedInteger('route_id')->nullable();
                $table->string('pickup_point', 180);
                $table->string('destination', 180);
                $table->dateTime('departure_at');
                $table->unsignedTinyInteger('available_seats');
                $table->decimal('contribution_per_seat', 7, 2)->default(0.00);
                $table->string('vehicle_description', 120);
                $table->string('vehicle_type', 80)->default('');
                $table->string('vehicle_color', 60)->default('');
                $table->string('vehicle_plate', 20)->default('');
                $table->string('notes', 500)->nullable();
                $table->enum('ride_status', ['scheduled', 'in_progress', 'completed', 'cancelled'])->default('scheduled');
                $table->timestamps();

                $table->foreign('driver_id')->references('user_id')->on('users')->onDelete('cascade');
                $table->foreign('vehicle_id')->references('vehicle_id')->on('vehicles')->onDelete('set null');
                $table->foreign('route_id')->references('route_id')->on('campus_routes')->onDelete('set null');
                $table->index(['driver_id', 'departure_at'], 'idx_rides_driver_departure');
                $table->index(['ride_status', 'departure_at'], 'idx_rides_status_departure');
            });
        }

        if (!Schema::hasTable('ride_route_stops')) {
            Schema::create('ride_route_stops', function (Blueprint $table) {
                $table->unsignedInteger('ride_id');
                $table->unsignedInteger('stop_id');
                $table->primary(['ride_id', 'stop_id']);

                $table->foreign('ride_id')->references('ride_id')->on('rides')->onDelete('cascade');
                $table->foreign('stop_id')->references('stop_id')->on('campus_route_stops')->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('ride_bookings')) {
            Schema::create('ride_bookings', function (Blueprint $table) {
                $table->increments('booking_id');
                $table->unsignedInteger('ride_id');
                $table->unsignedInteger('passenger_id');
                $table->unsignedInteger('pickup_stop_id')->nullable();
                $table->unsignedTinyInteger('seats_requested')->default(1);
                $table->string('pickup_label', 180)->nullable();
                $table->decimal('pickup_latitude', 10, 7)->nullable();
                $table->decimal('pickup_longitude', 10, 7)->nullable();
                $table->decimal('pickup_accuracy_m', 8, 2)->nullable();
                $table->enum('booking_status', ['pending', 'accepted', 'declined', 'cancelled'])->default('pending');
                $table->timestamps();

                $table->foreign('ride_id')->references('ride_id')->on('rides')->onDelete('cascade');
                $table->foreign('passenger_id')->references('user_id')->on('users')->onDelete('cascade');
                $table->foreign('pickup_stop_id')->references('stop_id')->on('campus_route_stops')->onDelete('set null');
                $table->unique(['ride_id', 'passenger_id'], 'uq_ride_passenger');
                $table->index(['ride_id', 'booking_status'], 'idx_ride_bookings_driver_status');
                $table->index(['passenger_id', 'created_at'], 'idx_ride_bookings_passenger');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Safe baseline: Do not drop tables with live user data
    }
};
