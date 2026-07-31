<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives every user a local day.
 *
 * Until now "today" was a UTC day for everyone, which stays invisible while
 * nothing depends on a day boundary — but the logging streak does, and a UTC
 * midnight lands at 03:30 for a Tehran user. Existing `fa` accounts are
 * backfilled to Asia/Tehran rather than left on the default: it is an inferred
 * preference, not financial data, and UTC is the wrong answer for all of them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('timezone', 50)->default('UTC')->after('calendar');
        });

        DB::table('users')->where('locale', 'fa')->update(['timezone' => 'Asia/Tehran']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('timezone');
        });
    }
};
