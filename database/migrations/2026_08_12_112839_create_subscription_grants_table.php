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
        Schema::create('subscription_grants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Indexed but deliberately unconstrained: subscription_payments is created
            // in a later migration, so the foreign key is added there. Grants also
            // outlive payments — an admin grant has no payment at all.
            $table->ulid('subscription_payment_id')->nullable()->index();

            $table->foreignId('granted_by_admin_id')->nullable()->constrained('users')->nullOnDelete();

            // Zero for a revoke, which is why this is signed.
            $table->smallInteger('months');

            $table->timestamp('pro_until_before')->nullable();
            $table->timestamp('pro_until_after');

            // A GrantReason value.
            $table->string('reason', 32);

            $table->text('note')->nullable();

            // Append-only, so there is nothing an updated_at could describe.
            $table->timestamp('created_at');

            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_grants');
    }
};
