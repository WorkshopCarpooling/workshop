<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        if(!Schema::hasTable('carbon_travel_preferences')) Schema::create('carbon_travel_preferences',function(Blueprint $table){
            $table->unsignedInteger('user_id')->primary();
            $table->string('baseline_mode',30)->nullable();
            $table->unsignedTinyInteger('baseline_cars')->nullable();
            $table->string('baseline_fuel_type',30)->nullable();
            $table->decimal('baseline_fuel_l_per_100km',6,3)->nullable();
            $table->string('driver_would_travel',10)->nullable();
            $table->timestamps();
        });
        if(!Schema::hasTable('carbon_fuel_prices')) Schema::create('carbon_fuel_prices',function(Blueprint $table){
            $table->increments('id');$table->date('month');$table->string('fuel',30);
            $table->decimal('price_per_litre',8,4)->nullable();
            $table->string('reference',180);$table->unsignedInteger('recorded_by');$table->timestamps();
            $table->index(['month','fuel','id']);
        });
    }
    public function down() { /* Preserve user choices and price revisions. */ }
};
