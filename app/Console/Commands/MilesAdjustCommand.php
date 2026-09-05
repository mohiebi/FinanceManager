<?php

namespace App\Console\Commands;

use App\Actions\Miles\AdjustMiles;
use App\Enums\MilesReason;
use App\Exceptions\InsufficientMiles;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('miles:adjust
    {email : Account email address}
    {amount : Signed whole-Miles adjustment}
    {idempotency : Stable operation key}
    {--reason=admin_adjustment : Ledger reason}
    {--note= : Audit note}')]
#[Description('Apply an auditable, idempotent Miles balance adjustment')]
class MilesAdjustCommand extends Command
{
    public function handle(AdjustMiles $adjustMiles): int
    {
        $user = User::query()->where('email', (string) $this->argument('email'))->first();

        if (! $user instanceof User) {
            $this->error('No user exists with that email address.');

            return self::FAILURE;
        }

        $amount = filter_var($this->argument('amount'), FILTER_VALIDATE_INT);

        if ($amount === false || $amount === 0) {
            $this->error('Amount must be a non-zero whole number.');

            return self::FAILURE;
        }

        $reason = MilesReason::tryFrom((string) $this->option('reason'));

        if (! $reason instanceof MilesReason) {
            $this->error('The supplied ledger reason is not valid.');

            return self::FAILURE;
        }

        try {
            $entry = $adjustMiles(
                user: $user,
                amount: $amount,
                reason: $reason,
                idempotencyKey: (string) $this->argument('idempotency'),
                metadata: array_filter([
                    'note' => $this->option('note'),
                    'operator' => 'artisan',
                ], fn (mixed $value): bool => $value !== null && $value !== ''),
                action: 'admin_adjustment',
            );
        } catch (InsufficientMiles $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Recorded {$entry->amount} Miles for {$user->email}. Balance: {$entry->balance_after}.");

        return self::SUCCESS;
    }
}
