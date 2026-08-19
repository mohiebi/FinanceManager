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
        Schema::table('bills', function (Blueprint $table) {
            // Null means the monthly bill continues indefinitely. Date-limited
            // bills also persist the calculated count so every occurrence can be
            // presented as an exact payment number (for example, 3 of 7).
            $table->string('recurrence_limit_type', 20)->nullable()->after('due_date');
            $table->unsignedSmallInteger('recurrence_count')->nullable()->after('recurrence_limit_type');
            $table->date('recurrence_end_date')->nullable()->after('recurrence_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn([
                'recurrence_limit_type',
                'recurrence_count',
                'recurrence_end_date',
            ]);
        });
    }
};
