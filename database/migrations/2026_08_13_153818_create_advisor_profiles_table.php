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
        Schema::create('advisor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('investor_assessment_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('profile_version')->default(1);
            $table->json('profile_payload');
            $table->timestamp('ai_consent_at')->nullable();
            $table->timestamps();

            $table->unique('investor_assessment_id');
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advisor_profiles');
    }
};
