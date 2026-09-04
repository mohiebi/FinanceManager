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
        Schema::create('service_usage_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('service', 40);
            $table->string('operation', 40);
            $table->nullableMorphs('source');
            $table->string('provider', 40)->nullable();
            $table->string('model', 120)->nullable();
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->decimal('provider_cost_usd', 12, 8)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('outcome', 40);
            $table->unsignedInteger('shadow_miles')->default(0);
            $table->unsignedInteger('charged_miles')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
            $table->index(['service', 'operation', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_usage_events');
    }
};
