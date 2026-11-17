<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        foreach (['fuel_type'=>'string', 'fuel_l_per_100km'=>'decimal'] as $column=>$type) {
            if (!Schema::hasColumn('vehicles', $column)) Schema::table('vehicles', function (Blueprint $table) use ($column,$type) {
                $type === 'decimal' ? $table->decimal($column,6,3)->nullable() : $table->string($column,30)->nullable();
            });
        }
        foreach (['baseline_mode','baseline_fuel_type','baseline_fuel_l_per_100km','baseline_cars','baseline_distance_km'] as $column) {
            if (!Schema::hasColumn('ride_bookings', $column)) Schema::table('ride_bookings', function (Blueprint $table) use ($column) {
                if (in_array($column,['baseline_fuel_l_per_100km','baseline_distance_km'],true)) $table->decimal($column,8,3)->nullable();
                elseif ($column === 'baseline_cars') $table->unsignedTinyInteger($column)->nullable();
                else $table->string($column,30)->nullable();
            });
        }
        if (!Schema::hasTable('ride_carbon_reports')) Schema::create('ride_carbon_reports', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('ride_id')->unique();
            $table->unsignedInteger('recorded_by');
            $table->decimal('shared_km',10,3);
            $table->decimal('driver_baseline_km',10,3);
            $table->string('distance_basis',30);
            $table->decimal('shared_kg',12,6);
            $table->decimal('baseline_kg',12,6)->nullable();
            $table->decimal('avoided_kg',12,6)->nullable();
            $table->boolean('needs_review')->default(false);
            $table->string('method',60);
            $table->json('details');
            $table->timestamps();
        });
        // Existing profiles, bookings and trips stay unknown; no fabricated backfill.
    }
    public function down() { /* Preserve historical estimates and their source inputs. */ }
};
