<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        if (!Schema::hasColumn('ride_requests', 'scheduled_for')) {
            Schema::table('ride_requests', function (Blueprint $table) {
                $table->dateTime('scheduled_for')->nullable();
                $table->index(['request_status', 'scheduled_for'], 'ride_requests_appointment_due');
            });
        }
    }

    public function down()
    {
        // Retain saved appointments and trip history.
    }
};
