<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->timestamp('chain_verified_at')->nullable()->after('verified_at');
            $table->string('screening_risk', 20)->nullable()->after('chain_verified_at');
            $table->string('screening_provider', 80)->nullable()->after('screening_risk');
            $table->json('screening_categories')->nullable()->after('screening_provider');
            $table->string('screening_reference', 160)->nullable()->after('screening_categories');
            $table->timestamp('screened_at')->nullable()->after('screening_reference');
            $table->index(['screening_risk', 'screened_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->dropIndex(['screening_risk', 'screened_at']);
            $table->dropColumn([
                'chain_verified_at',
                'screening_risk',
                'screening_provider',
                'screening_categories',
                'screening_reference',
                'screened_at',
            ]);
        });
    }
};
