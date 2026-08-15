<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Flight-themed navigation becomes opt-in rather than opt-out.
 *
 * The existing rows are flipped with the column default. Flight naming shipped
 * on 2026-08-14 and was on for everybody by default, so a stored `true` is the
 * old default rather than a choice anyone made — leaving those rows alone would
 * mean the setting reads "off by default" while no existing account is off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('flight_terminology_enabled')->default(false)->change();
        });

        DB::table('users')->where('flight_terminology_enabled', true)->update([
            'flight_terminology_enabled' => false,
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('flight_terminology_enabled')->default(true)->change();
        });

        DB::table('users')->where('flight_terminology_enabled', false)->update([
            'flight_terminology_enabled' => true,
        ]);
    }
};
