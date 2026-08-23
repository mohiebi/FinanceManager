<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deposit_addresses', function (Blueprint $table) {
            $table->string('key_version', 40)->default('v1')->after('id');
            $table->string('network', 20)->nullable()->change();
        });

        Schema::create('wallet_derivation_states', function (Blueprint $table) {
            $table->id();
            $table->string('key_version', 40)->unique();
            $table->unsignedBigInteger('next_deposit_index')->default(0);
            $table->timestamps();
        });

        Schema::create('risk_addresses', function (Blueprint $table) {
            $table->id();
            $table->string('network', 20);
            $table->string('address', 80);
            $table->string('source', 120);
            $table->text('reason');
            $table->boolean('active')->default(true);
            $table->foreignId('created_by_admin_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('deactivated_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();

            $table->unique(['network', 'address']);
            $table->index(['active', 'network']);
        });

        Schema::create('payment_risk_cases', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('subscription_payment_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('network', 20);
            $table->string('source_address', 80);
            $table->unsignedTinyInteger('user_case_number');
            $table->string('status', 32);
            $table->timestamp('review_expires_at');
            $table->foreignId('authorized_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('authorized_at')->nullable();
            $table->text('authorization_note')->nullable();
            $table->foreignId('granted_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('granted_at')->nullable();
            $table->text('grant_note')->nullable();
            $table->timestamps();

            $table->unique('source_address');
            $table->index(['status', 'review_expires_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('payment_settlements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('subscription_payment_id')->unique()->constrained()->cascadeOnDelete();
            $table->uuid('operation_id')->unique();
            $table->string('status', 32);
            $table->boolean('risk_authorized')->default(false);
            $table->json('transaction_hashes')->nullable();
            $table->string('gas_used_wei', 100)->nullable();
            $table->string('quoted_eth', 100)->nullable();
            $table->string('minimum_eth', 100)->nullable();
            $table->string('received_eth', 100)->nullable();
            $table->string('remaining_token_balance', 100)->nullable();
            $table->string('remaining_eth_wei', 100)->nullable();
            $table->string('vault_receipt', 80)->nullable();
            $table->string('failure_code', 100)->nullable();
            $table->text('failure_message')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_settlements');
        Schema::dropIfExists('payment_risk_cases');
        Schema::dropIfExists('risk_addresses');
        Schema::dropIfExists('wallet_derivation_states');

        Schema::table('deposit_addresses', function (Blueprint $table) {
            $table->dropColumn('key_version');
        });
    }
};
