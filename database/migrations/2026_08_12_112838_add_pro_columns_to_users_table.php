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
        Schema::table('users', function (Blueprint $table) {
            // The entitlement itself. A column rather than a subscriptions relation
            // because User::isPro() runs on every authenticated request via the shared
            // Inertia feature map, and must not cost a query.
            //
            // Never nulled on expiry: keeping the stale value is what makes "extend
            // from the current expiry rather than from today" a plain comparison, and
            // it doubles as a record of when someone lapsed.
            $table->timestamp('pro_until')->nullable()->index()->after('last_active_at');

            // Each marker stores a COPY of the pro_until it refers to, not a boolean or
            // a send time. A renewal moves pro_until, so marker != pro_until becomes
            // true again on its own and the reminder re-arms with no reset step — and
            // an admin shortening the date re-arms it correctly too.
            $table->timestamp('pro_expiry_warned_for')->nullable()->after('pro_until');
            $table->timestamp('pro_expired_notified_for')->nullable()->after('pro_expiry_warned_for');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['pro_until']);
            $table->dropColumn(['pro_until', 'pro_expiry_warned_for', 'pro_expired_notified_for']);
        });
    }
};
