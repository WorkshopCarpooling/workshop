<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        foreach(['pool_seat_capacity','pool_origin_latitude','pool_origin_longitude'] as $column) {
            if(!Schema::hasColumn('rides',$column)) Schema::table('rides',function(Blueprint $table)use($column){
                if($column==='pool_seat_capacity')$table->unsignedTinyInteger($column)->nullable();
                else $table->decimal($column,10,7)->nullable();
            });
        }
    }
    public function down() { /* Existing pool capacity and starting points remain part of trip history. */ }
};
