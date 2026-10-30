<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        foreach(['removed_at','removed_by','status_before_removal','access_version'] as $column)if(!Schema::hasColumn('users',$column))Schema::table('users',function(Blueprint $table)use($column){
            if($column==='removed_at')$table->timestamp($column)->nullable();
            elseif($column==='status_before_removal')$table->string($column,20)->nullable();
            elseif($column==='access_version')$table->unsignedInteger($column)->default(0);
            else $table->unsignedInteger($column)->nullable();
        });
    }
    public function down() { /* Preserve account-removal audit data and access versions. */ }
};
