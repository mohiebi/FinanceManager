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
        Schema::create('deposit_recoveries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('deposit_address_id')->constrained()->cascadeOnDelete();
            $table->string('network', 32);
            $table->uuid('operation_id')->unique();
            $table->string('status', 40)->index();
            $table->text('reason');
            $table->json('transaction_hashes')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['deposit_address_id', 'network', 'created_at'], 'deposit_recoveries_address_network_created_index');
        });

        Schema::table('deposit_addresses', function (Blueprint $table) {
            $table->dropUnique('deposit_addresses_recovery_operation_id_unique');
            $table->dropColumn('recovery_operation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deposit_addresses', function (Blueprint $table) {
            $table->uuid('recovery_operation_id')->nullable()->unique();
        });

        Schema::dropIfExists('deposit_recoveries');
    }
};
