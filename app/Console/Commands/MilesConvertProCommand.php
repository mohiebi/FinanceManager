<?php

namespace App\Console\Commands;

use App\Actions\Miles\AdjustMiles;
use App\Enums\MilesReason;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('miles:convert-pro {--execute : Apply the conversion when the batch threshold is met}')]
#[Description('Audit or batch-convert remaining Pro time into Miles')]
class MilesConvertProCommand extends Command
{
    private const BATCH_THRESHOLD = 20;

    private const MILES_PER_BLOCK = 250;

    private const BLOCK_SECONDS = 30 * 24 * 60 * 60;

    public function handle(AdjustMiles $adjustMiles): int
    {
        $now = now();
        $users = User::query()
            ->where('pro_until', '>', $now)
            ->orderBy('id')
            ->get(['id', 'email', 'pro_until']);

        if ($users->isEmpty()) {
            $this->info('No active Pro accounts require conversion.');

            return self::SUCCESS;
        }

        if ($users->count() < self::BATCH_THRESHOLD) {
            $this->warn("{$users->count()} active Pro accounts found; use individual miles:adjust commands:");
            $this->table(
                ['Email', 'Miles', 'Idempotency key'],
                $users->map(fn (User $user): array => [
                    $user->email,
                    $this->conversionMiles($user, $now),
                    $this->idempotencyKey($user),
                ])->all(),
            );

            return self::SUCCESS;
        }

        $totalMiles = $users->sum(fn (User $user): int => $this->conversionMiles($user, $now));
        $this->line("{$users->count()} active Pro accounts require {$totalMiles} Miles in total.");

        if (! $this->option('execute')) {
            $this->comment('Dry run only. Re-run with --execute to apply this idempotent batch.');

            return self::SUCCESS;
        }

        foreach ($users as $user) {
            $adjustMiles(
                user: $user,
                amount: $this->conversionMiles($user, $now),
                reason: MilesReason::LegacyProConversion,
                idempotencyKey: $this->idempotencyKey($user),
                metadata: [
                    'previous_pro_expiry' => $user->pro_until->toIso8601String(),
                    'miles_per_started_30_day_block' => self::MILES_PER_BLOCK,
                ],
            );
        }

        $this->info("Converted {$users->count()} active Pro accounts to {$totalMiles} Miles.");

        return self::SUCCESS;
    }

    private function conversionMiles(User $user, CarbonInterface $now): int
    {
        $remainingSeconds = max(1, $user->pro_until->getTimestamp() - $now->getTimestamp());
        $blocks = intdiv($remainingSeconds - 1, self::BLOCK_SECONDS) + 1;

        return $blocks * self::MILES_PER_BLOCK;
    }

    private function idempotencyKey(User $user): string
    {
        return "legacy-pro:{$user->getKey()}:{$user->pro_until->getTimestamp()}";
    }
}
