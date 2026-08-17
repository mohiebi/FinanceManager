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
        Schema::create('deposit_addresses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('network', 20);
            $table->unsignedBigInteger('derivation_index');
            $table->string('address', 80);
            $table->string('status', 24);
            $table->foreignUlid('assigned_payment_id')->nullable()->unique()
                ->constrained('subscription_payments')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('quarantined_at')->nullable();
            $table->string('quarantine_reason', 80)->nullable();
            $table->timestamp('sweep_authorized_at')->nullable();
            $table->timestamp('sweep_authorization_expires_at')->nullable();
            $table->foreignId('sweep_authorized_by_admin_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('conversion_tx_hash', 80)->nullable();
            $table->string('sweep_tx_hash', 80)->nullable();
            $table->timestamp('swept_at')->nullable();
            $table->foreignId('swept_by_admin_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->text('sweep_note')->nullable();
            $table->timestamps();

            $table->unique(['network', 'derivation_index']);
            $table->unique(['network', 'address']);
            $table->unique(['network', 'conversion_tx_hash']);
            $table->unique(['network', 'sweep_tx_hash']);
            $table->index(['network', 'status', 'derivation_index']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deposit_addresses');
    }
};
