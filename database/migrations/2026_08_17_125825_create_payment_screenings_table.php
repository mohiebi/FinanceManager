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
        Schema::create('payment_screenings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('subscription_payment_id')
                ->constrained('subscription_payments')->cascadeOnDelete();
            $table->string('stage', 20);
            $table->string('risk', 20);
            $table->string('provider', 80);
            $table->json('categories')->nullable();
            $table->string('provider_reference', 160)->nullable();
            $table->string('error_code', 80)->nullable();
            $table->timestamp('screened_at');
            $table->timestamps();

            $table->index(['subscription_payment_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_screenings');
    }
};
