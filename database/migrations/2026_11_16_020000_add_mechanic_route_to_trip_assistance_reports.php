<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_assistance_reports', function (Blueprint $table) {
            $table->json('mechanic_route')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('trip_assistance_reports', function (Blueprint $table) {
            $table->dropColumn('mechanic_route');
        });
    }
};
