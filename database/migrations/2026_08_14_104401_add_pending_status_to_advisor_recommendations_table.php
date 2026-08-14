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
            $table->string('pending_status', 32)->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('advisor_recommendations', function (Blueprint $table) {
            $table->dropColumn('pending_status');
        });
    }
};
