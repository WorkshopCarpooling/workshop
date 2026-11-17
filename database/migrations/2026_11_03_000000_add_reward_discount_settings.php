<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        // Unknown values on existing vouchers and receipts stay unknown.
        foreach (['carbon_reward_catalog', 'carbon_reward_redemptions'] as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'discount_rm')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->decimal('discount_rm', 8, 2)->nullable();
                });
            }
        }
    }

    public function down() { /* Preserve voucher values and claimed receipt snapshots. */ }
};
