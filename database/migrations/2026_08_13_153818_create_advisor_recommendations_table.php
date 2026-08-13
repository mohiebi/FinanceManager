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
        Schema::create('advisor_recommendations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('advisor_profile_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('generating');
            $table->string('mode', 20);
            $table->unsignedSmallInteger('profile_version');
            $table->unsignedSmallInteger('scoring_version');
            $table->unsignedSmallInteger('prompt_version');
            $table->string('provider', 40)->nullable();
            $table->string('model', 100)->nullable();
            $table->unsignedSmallInteger('knowledge_version')->default(1);
            $table->char('context_hash', 64);
            $table->char('output_hash', 64)->nullable();
            $table->boolean('current_portfolio_included')->default(false);
            $table->longText('current_portfolio_snapshot')->nullable();
            $table->longText('clarification_answers')->nullable();
            $table->longText('recommendation_payload')->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->unsignedTinyInteger('clarification_rounds')->default(0);
            $table->unsignedTinyInteger('repair_attempts')->default(0);
            $table->unsignedTinyInteger('provider_calls')->default(0);
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advisor_recommendations');
    }
};
