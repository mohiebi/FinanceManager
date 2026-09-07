<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->string('plan', 20)->nullable()->change();
            $table->string('miles_pack', 20)->nullable();
            $table->unsignedInteger('miles')->nullable();
        });
        Schema::table('coupon_redemptions', function (Blueprint $table) {
            $table->string('miles_pack', 20)->nullable();
            $table->unsignedInteger('miles')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('coupon_redemptions', function (Blueprint $table) {
            $table->dropColumn(['miles_pack', 'miles']);
        });
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->dropColumn(['miles_pack', 'miles']);
        });
    }
};
