<?php

namespace App\Actions\Miles;

use App\Enums\MilesReason;
use App\Models\User;
use App\Models\UserCosmetic;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class PurchaseCosmetic
{
    public function __construct(private AdjustMiles $adjustMiles) {}

    public function __invoke(User $user, string $key): UserCosmetic
    {
        if (! config('miles.cosmetics_enabled')) {
            throw ValidationException::withMessages(['cosmetic' => __('Cosmetics are not available yet.')]);
        }

        /** @var array{type: string, label: string, price: int}|null $cosmetic */
        $cosmetic = config("miles.cosmetics.{$key}");

        if ($cosmetic === null) {
            throw ValidationException::withMessages(['cosmetic' => __('Choose an available cosmetic.')]);
        }

        return DB::transaction(function () use ($user, $key, $cosmetic): UserCosmetic {
            $owned = UserCosmetic::query()->where('user_id', $user->getKey())->where('key', $key)->first();

            if ($owned instanceof UserCosmetic) {
                UserCosmetic::query()->where('user_id', $user->getKey())->where('type', $owned->type)->update(['selected' => false]);
                $owned->forceFill(['selected' => true])->save();

                return $owned->refresh();
            }

            $entry = ($this->adjustMiles)(
                $user,
                -$cosmetic['price'],
                MilesReason::Cosmetic,
                "cosmetic:{$user->getKey()}:{$key}",
                metadata: ['key' => $key, 'type' => $cosmetic['type']],
                action: 'cosmetic',
            );

            UserCosmetic::query()->where('user_id', $user->getKey())->where('type', $cosmetic['type'])->update(['selected' => false]);

            return UserCosmetic::query()->create([
                'user_id' => $user->getKey(),
                'key' => $key,
                'type' => $cosmetic['type'],
                'selected' => true,
                'mile_ledger_entry_id' => $entry->getKey(),
                'acquired_at' => now(),
            ]);
        }, 3);
    }
}
