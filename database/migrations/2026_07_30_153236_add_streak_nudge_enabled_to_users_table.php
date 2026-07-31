<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opt-in for the nightly streak reminder.
 *
 * Defaults to off. Switching on a module should not begin sending unrequested
 * nightly messages to people who already linked Telegram for something else.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('streak_nudge_enabled')->default(false)->after('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('streak_nudge_enabled');
        });
    }
};
