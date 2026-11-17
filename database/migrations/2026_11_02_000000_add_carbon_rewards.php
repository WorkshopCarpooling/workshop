<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        if(!Schema::hasTable('carbon_reward_declarations'))Schema::create('carbon_reward_declarations',function(Blueprint $t){
            $t->increments('id');$t->unsignedInteger('ride_id');$t->unsignedInteger('user_id');
            $t->string('alternative',30);$t->unsignedTinyInteger('cars')->default(0);$t->decimal('distance_km',10,3)->nullable();
            $t->string('fuel',30)->nullable();$t->decimal('consumption',6,3)->nullable();$t->decimal('fuel_factor',8,4)->nullable();
            $t->decimal('baseline_kg',14,6);$t->timestamps();$t->unique(['ride_id','user_id']);
        });
        if(!Schema::hasTable('carbon_reward_trips'))Schema::create('carbon_reward_trips',function(Blueprint $t){
            $t->increments('id');$t->unsignedInteger('ride_id')->unique();$t->unsignedInteger('vehicle_id')->nullable()->index();
            $t->dateTime('completed_at')->index();$t->decimal('baseline_kg',14,6);$t->decimal('shared_kg',14,6);
            $t->decimal('saved_kg',14,6);$t->decimal('remaining_percent',12,4)->nullable();$t->unsignedInteger('points_per_kg');
            $t->string('method',80);$t->json('details');$t->timestamps();
        });
        if(!Schema::hasTable('carbon_reward_catalog'))Schema::create('carbon_reward_catalog',function(Blueprint $t){
            $t->increments('id');$t->string('title',120);$t->string('provider',80);$t->text('terms')->nullable();
            $t->unsignedInteger('points_cost');$t->boolean('active')->default(true);$t->unsignedInteger('created_by');$t->timestamps();
        });
        if(!Schema::hasTable('carbon_reward_vouchers'))Schema::create('carbon_reward_vouchers',function(Blueprint $t){
            $t->increments('id');$t->unsignedInteger('reward_id')->index();$t->text('encrypted_code');$t->string('code_hash',64)->unique();
            $t->dateTime('expires_at');$t->dateTime('claimed_at')->nullable();$t->timestamps();
        });
        if(!Schema::hasTable('carbon_reward_redemptions'))Schema::create('carbon_reward_redemptions',function(Blueprint $t){
            $t->increments('id');$t->unsignedInteger('user_id')->index();$t->unsignedInteger('reward_id');$t->unsignedInteger('voucher_id')->unique();
            $t->uuid('request_key')->unique();$t->unsignedInteger('points_cost');$t->string('title',120);$t->string('provider',80);
            $t->text('terms')->nullable();$t->timestamps();
        });
        if(!Schema::hasTable('carbon_reward_ledger'))Schema::create('carbon_reward_ledger',function(Blueprint $t){
            $t->increments('id');$t->unsignedInteger('user_id')->index();$t->unsignedInteger('ride_id')->nullable();
            $t->unsignedInteger('redemption_id')->nullable()->unique();$t->string('kind',20);$t->integer('points');
            $t->decimal('saved_kg',14,6)->default(0);$t->timestamps();$t->unique(['ride_id','user_id','kind']);
        });
    }
    public function down() { /* Preserve earned points, declarations and redemption receipts. */ }
};
