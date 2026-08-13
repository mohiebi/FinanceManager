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
        if (! Schema::hasTable('investor_assessment_answers')) {
            Schema::create('investor_assessment_answers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('investor_assessment_id')->constrained()->cascadeOnDelete();
                $table->string('question_key', 64);
                $table->longText('answer');
                $table->timestamps();
            });
        }

        $uniqueIndex = 'investor_answers_assessment_question_unique';

        if (! Schema::hasIndex('investor_assessment_answers', $uniqueIndex)) {
            Schema::table('investor_assessment_answers', function (Blueprint $table) use ($uniqueIndex) {
                $table->unique(['investor_assessment_id', 'question_key'], $uniqueIndex);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('investor_assessment_answers');
    }
};
