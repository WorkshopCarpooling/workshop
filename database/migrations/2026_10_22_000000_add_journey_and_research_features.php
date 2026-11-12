<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up()
    {
        $mysql = DB::connection()->getDriverName() === 'mysql';
        $type = $mysql ? DB::selectOne('SELECT COLUMN_TYPE AS column_type FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?', [DB::connection()->getDatabaseName(), 'users', 'user_id'])->column_type : 'int unsigned';
        $userUnsigned = stripos($type, 'unsigned') !== false;
        foreach (['rides', 'ride_bookings'] as $name) {
            foreach (['pickup', 'dropoff'] as $point) {
                foreach (['latitude', 'longitude'] as $axis) {
                    $column = $point . '_' . $axis;
                    if (!Schema::hasColumn($name, $column)) {
                        Schema::table($name, fn (Blueprint $t) => $t->decimal($column, 10, 7)->nullable());
                    }
                }
            }
        }
        foreach (['dropoff_label' => 'string', 'arrive_by' => 'dateTime', 'compatibility_score' => 'unsignedTinyInteger'] as $column => $type) {
            if (!Schema::hasColumn('ride_bookings', $column)) {
                Schema::table('ride_bookings', fn (Blueprint $t) => $t->$type($column)->nullable());
            }
        }
        foreach (['started_at', 'completed_at'] as $column) {
            if (!Schema::hasColumn('rides', $column)) {
                Schema::table('rides', fn (Blueprint $t) => $t->dateTime($column)->nullable());
            }
        }
        if (!Schema::hasTable('commute_timetable')) {
            Schema::create('commute_timetable', function (Blueprint $t) use ($userUnsigned) {
                $t->increments('id');
                $t->integer('user_id')->unsigned($userUnsigned);
                $t->string('title', 100);
                $t->unsignedTinyInteger('weekday');
                $t->time('starts_at');
                $t->time('ends_at');
                $t->string('location', 180);
                $t->timestamps();
            });
        }
        if (!Schema::hasTable('commute_requirement_surveys')) {
            Schema::create('commute_requirement_surveys', function (Blueprint $t) use ($userUnsigned) {
                $t->increments('id');
                $t->integer('user_id')->unsigned($userUnsigned)->unique();
                $t->string('travel_mode', 30);
                $t->unsignedTinyInteger('days_per_week');
                $t->unsignedSmallInteger('usual_minutes');
                $t->time('usual_departure');
                $t->decimal('weekly_cost', 8, 2);
                $t->unsignedTinyInteger('willingness');
                $t->text('requirements')->nullable();
                $t->timestamps();
            });
        }
        if (!Schema::hasTable('travel_time_samples')) {
            Schema::create('travel_time_samples', function (Blueprint $t) use ($userUnsigned) {
                $t->increments('id');
                $t->integer('user_id')->unsigned($userUnsigned);
                $t->string('route_key', 180);
                $t->dateTime('departed_at');
                $t->dateTime('arrived_at');
                $t->decimal('distance_km', 8, 3);
                $t->decimal('actual_minutes', 8, 2);
                $t->timestamps();
                $t->index(['route_key', 'departed_at']);
                $t->unique(['user_id', 'route_key', 'departed_at'], 'unique_observation');
            });
        }
        if (!Schema::hasTable('usability_responses')) {
            Schema::create('usability_responses', function (Blueprint $t) use ($userUnsigned) {
                $t->increments('id');
                $t->integer('user_id')->unsigned($userUnsigned)->unique();
                $t->json('answers');
                $t->decimal('sus_score', 5, 2);
                $t->text('feedback')->nullable();
                $t->timestamps();
            });
        }
        // Match the legacy users ID signedness, including recovery from a partial DDL run.
        foreach (['commute_timetable', 'commute_requirement_surveys', 'travel_time_samples', 'usability_responses'] as $name) {
            if ($mysql) {
                $columnType = DB::selectOne('SELECT COLUMN_TYPE AS column_type FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?', [DB::connection()->getDatabaseName(), $name, 'user_id'])->column_type;
                if ((stripos($columnType, 'unsigned') !== false) !== $userUnsigned) DB::statement('ALTER TABLE `' . $name . '` MODIFY `user_id` INT ' . ($userUnsigned ? 'UNSIGNED ' : '') . 'NOT NULL');
                $hasForeign = DB::selectOne('SELECT COUNT(*) AS n FROM information_schema.key_column_usage WHERE table_schema = ? AND table_name = ? AND column_name = ? AND referenced_table_name = ?', [DB::connection()->getDatabaseName(), $name, 'user_id', 'users'])->n;
                if (!$hasForeign) Schema::table($name, fn (Blueprint $t) => $t->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade'));
            }
        }
        // Schema only: no accounts, routes, survey responses, or training samples are inserted.
    }

    public function down()
    {
        // Preserve user-collected research and journey data.
    }
};
