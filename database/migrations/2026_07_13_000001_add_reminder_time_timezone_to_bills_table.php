<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table): void {
            $table->string('reminder_time', 5)->default('09:00')->after('telegram_reminder_enabled');
            $table->string('reminder_timezone', 50)->default('UTC')->after('reminder_time');
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table): void {
            $table->dropColumn(['reminder_time', 'reminder_timezone']);
        });
    }
};
