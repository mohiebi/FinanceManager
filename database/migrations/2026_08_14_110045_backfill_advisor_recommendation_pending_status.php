<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $legacyStates = [
            'pending_ready' => ['pending_status' => 'ready', 'failure_code' => null],
            'pending_needs_clarification' => ['pending_status' => 'needs_clarification', 'failure_code' => null],
            'pending_failed' => ['pending_status' => 'failed', 'failure_code' => 'cannot_recommend'],
        ];

        foreach ($legacyStates as $legacyFailureCode => $state) {
            DB::table('advisor_recommendations')
                ->where('status', 'awaiting_vault_seal')
                ->where('failure_code', $legacyFailureCode)
                ->update($state);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['ready', 'needs_clarification', 'failed'] as $pendingStatus) {
            DB::table('advisor_recommendations')
                ->where('status', 'awaiting_vault_seal')
                ->where('pending_status', $pendingStatus)
                ->update([
                    'failure_code' => 'pending_'.$pendingStatus,
                    'pending_status' => null,
                ]);
        }
    }
};
