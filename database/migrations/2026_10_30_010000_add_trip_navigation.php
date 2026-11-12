<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(){
        if(!Schema::hasColumn('rides','navigation_plan'))Schema::table('rides',fn(Blueprint $table)=>$table->longText('navigation_plan')->nullable());
        foreach(['picked_up_at','dropped_off_at'] as $column)if(!Schema::hasColumn('ride_bookings',$column))Schema::table('ride_bookings',fn(Blueprint $table)=>$table->timestamp($column)->nullable());
    }
    public function down(){ /* Preserve recorded pickup/drop-off history. */ }
};
