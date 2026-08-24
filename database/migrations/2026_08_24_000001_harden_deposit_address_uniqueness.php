<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make address reuse impossible, and drop the manual sweep bookkeeping.
 *
 * The pool became network-agnostic when addresses started being derived on
 * demand — one EVM key controls the same address on every chain, so an unassigned
 * row carries a null network until a payment claims it. That silently disabled
 * every uniqueness guarantee the table had: MySQL does not treat two NULLs as
 * equal, so `unique(network, address)` stopped constraining exactly the rows
 * that most needed constraining. The indexes below do not mention network at
 * all, which is the only way they can hold for a pooled row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deposit_addresses', function (Blueprint $table) {
            $table->dropUnique('deposit_addresses_network_conversion_tx_hash_unique');
            $table->dropUnique('deposit_addresses_network_sweep_tx_hash_unique');
            $table->dropUnique('deposit_addresses_network_address_unique');
            $table->dropUnique('deposit_addresses_network_derivation_index_unique');
        });

        Schema::table('deposit_addresses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sweep_authorized_by_admin_id');
            $table->dropConstrainedForeignId('swept_by_admin_id');
            $table->dropColumn([
                'sweep_authorized_at',
                'sweep_authorization_expires_at',
                'conversion_tx_hash',
                'sweep_tx_hash',
                'sweep_note',
            ]);
        });

        Schema::table('deposit_addresses', function (Blueprint $table) {
            $table->unique('address');
            $table->unique(['key_version', 'derivation_index']);

            // Recovery of funds that arrived at an address nothing will settle:
            // a buyer who paid after their window closed, or on the chain their
            // intent did not name. The operation id is kept so re-running the
            // command resumes the signer's existing operation rather than
            // starting a second one against the same address.
            $table->uuid('recovery_operation_id')->nullable()->unique();
            $table->timestamp('recovered_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('deposit_addresses', function (Blueprint $table) {
            $table->dropUnique('deposit_addresses_address_unique');
            $table->dropUnique('deposit_addresses_key_version_derivation_index_unique');
            $table->dropUnique('deposit_addresses_recovery_operation_id_unique');
            $table->dropColumn(['recovery_operation_id', 'recovered_at']);

            $table->timestamp('sweep_authorized_at')->nullable();
            $table->timestamp('sweep_authorization_expires_at')->nullable();
            $table->foreignId('sweep_authorized_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('conversion_tx_hash', 80)->nullable();
            $table->string('sweep_tx_hash', 80)->nullable();
            $table->foreignId('swept_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('sweep_note')->nullable();
        });

        Schema::table('deposit_addresses', function (Blueprint $table) {
            $table->unique(['network', 'address']);
            $table->unique(['network', 'derivation_index']);
            $table->unique(['network', 'conversion_tx_hash']);
            $table->unique(['network', 'sweep_tx_hash']);
        });
    }
};
