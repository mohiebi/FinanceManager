<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Switches modules on for users who were already using them, so nobody loses a
 * feature the day this ships. Users with no data in a module start at the new
 * defaults and get the discovery prompt instead.
 *
 * Uses the query builder rather than models so it stays correct if those change.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        DB::table('users')->select('id', 'telegram_chat_id')->orderBy('id')->chunkById(500, function ($users) use ($now): void {
            $ids = collect($users)->pluck('id');

            $withBills = DB::table('bills')
                ->whereIn('user_id', $ids)
                ->distinct()
                ->pluck('user_id')
                ->flip();

            $withInvestments = DB::table('investments')
                ->whereIn('user_id', $ids)
                ->distinct()
                ->pluck('user_id')
                ->merge(
                    DB::table('investment_assets')
                        ->whereIn('user_id', $ids)
                        ->distinct()
                        ->pluck('user_id')
                )
                ->unique()
                ->flip();

            $withTelegram = collect($users)
                ->filter(fn ($user): bool => filled($user->telegram_chat_id))
                ->pluck('id')
                ->flip();

            $withAiAssistant = DB::table('oauth_access_tokens')
                ->whereIn('user_id', $ids)
                ->where('revoked', false)
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', $now))
                ->where('scopes', 'like', '%mcp:use%')
                ->distinct()
                ->pluck('user_id')
                ->flip();

            $rows = [];

            foreach ($ids as $id) {
                // Portfolio is a view over investments, so anyone holding assets
                // was already using it and it rides along.
                $enabled = [
                    'bills' => $withBills->has($id),
                    'investments' => $withInvestments->has($id),
                    'portfolio' => $withInvestments->has($id),
                    'ai_assistant' => $withAiAssistant->has($id),
                    'telegram_bot' => $withTelegram->has($id),
                ];

                foreach ($enabled as $feature => $isEnabled) {
                    if (! $isEnabled) {
                        continue;
                    }

                    $rows[] = [
                        'user_id' => $id,
                        'feature' => $feature,
                        'enabled' => true,
                        'show_promo' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            // insertOrIgnore plus unique(user_id, feature) makes a re-run a no-op.
            if ($rows !== []) {
                DB::table('user_features')->insertOrIgnore($rows);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('user_features')
            ->whereIn('feature', ['bills', 'investments', 'portfolio', 'ai_assistant', 'telegram_bot'])
            ->delete();
    }
};
