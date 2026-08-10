<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opt-out for the "N days before due" bill reminder. Defaults to on so
 * existing bills keep getting an advance reminder — only the day count
 * changes (see BillReminderJob::ADVANCE_REMINDER_DAYS) — and users who don't
 * want it can turn it off from Settings > Notifications. The due-day
 * reminder itself is unconditional and unaffected by this flag.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('bill_advance_reminder_enabled')->default(true)->after('streak_nudge_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('bill_advance_reminder_enabled');
        });
    }
};
