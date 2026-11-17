<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up()
    {
        $unsigned = function (string $table, string $column): bool {
            if (DB::connection()->getDriverName() !== 'mysql') return true;
            $type = DB::selectOne('SELECT COLUMN_TYPE AS column_type FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?', [DB::connection()->getDatabaseName(), $table, $column]);
            return stripos($type->column_type, 'unsigned') !== false;
        };
        $userUnsigned = $unsigned('users', 'user_id');
        $sessionUnsigned = $unsigned('commute_timetable', 'id');
        if (!Schema::hasTable('timetable_ride_plans')) {
            Schema::create('timetable_ride_plans', function (Blueprint $table) use ($userUnsigned, $sessionUnsigned) {
                $table->increments('id');
                $table->integer('user_id')->unsigned($userUnsigned);
                $table->integer('timetable_id')->unsigned($sessionUnsigned)->nullable()->unique();
                foreach (['pickup','dropoff'] as $point) {
                    $table->string($point.'_label',180);
                    $table->decimal($point.'_latitude',10,7);
                    $table->decimal($point.'_longitude',10,7);
                }
                $table->unsignedTinyInteger('seats_requested');
                $table->unsignedTinyInteger('arrival_buffer_minutes');
                $table->unsignedTinyInteger('driver_allowance_minutes')->default(10);
                $table->unsignedSmallInteger('travel_minutes');
                $table->decimal('journey_distance_km',10,3);
                $table->decimal('fare_per_seat',7,2);
                $table->string('distance_source',50);
                $table->string('fare_policy',50);
                $table->date('starts_on');
                $table->date('ends_on');
                $table->boolean('is_enabled')->default(true);
                $table->timestamps();
                $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
                $table->foreign('timetable_id')->references('id')->on('commute_timetable')->onDelete('set null');
                $table->index(['is_enabled','ends_on']);
            });
        }
        if (!Schema::hasColumn('ride_requests','timetable_plan_id')) {
            Schema::table('ride_requests',function (Blueprint $table) use ($sessionUnsigned) {
                $table->unsignedInteger('timetable_plan_id')->nullable();
                $table->integer('timetable_entry_id')->unsigned($sessionUnsigned)->nullable();
                $table->string('timetable_session_title',100)->nullable();
                $table->dateTime('timetable_session_at')->nullable();
                $table->foreign('timetable_plan_id')->references('id')->on('timetable_ride_plans')->onDelete('set null');
                $table->foreign('timetable_entry_id')->references('id')->on('commute_timetable')->onDelete('set null');
                $table->unique(['timetable_plan_id','timetable_session_at'],'unique_weekly_ride_occurrence');
            });
        }
    }

    public function down()
    {
        // Preserve weekly settings, generated appointments and journey history.
    }
};
