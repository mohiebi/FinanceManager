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
        Schema::table('advisor_recommendations', function (Blueprint $table) {
            $table->unsignedSmallInteger('quoted_miles')->default(0)->after('provider_calls');
            $table->unsignedSmallInteger('reserved_miles')->default(0)->after('quoted_miles');
            $table->unsignedSmallInteger('charged_miles')->default(0)->after('reserved_miles');
            $table->string('miles_outcome', 24)->nullable()->after('charged_miles');
            $table->timestamp('miles_settled_at')->nullable()->after('miles_outcome');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('advisor_recommendations', function (Blueprint $table) {
            $table->dropColumn(['quoted_miles', 'reserved_miles', 'charged_miles', 'miles_outcome', 'miles_settled_at']);
        });
    }
};
