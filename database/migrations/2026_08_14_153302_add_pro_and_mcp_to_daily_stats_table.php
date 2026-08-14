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
        Schema::table('daily_stats', function (Blueprint $table) {
            // Captured daily for the same reason telegram_customers is: the
            // dashboard's period-over-period deltas compare today against a
            // stored snapshot, so a metric with no history can only read null.
            //
            // Defaulting to zero rather than nullable keeps the percentChange
            // arithmetic total — an older row simply reports growth from none.
            $table->unsignedInteger('pro_customers')->default(0)->after('telegram_customers');
            $table->unsignedInteger('mcp_customers')->default(0)->after('pro_customers');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_stats', function (Blueprint $table) {
            $table->dropColumn(['pro_customers', 'mcp_customers']);
        });
    }
};
