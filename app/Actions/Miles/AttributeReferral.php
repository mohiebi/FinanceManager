<?php

namespace App\Actions\Miles;

use App\Models\MileWallet;
use App\Models\Referral;
use App\Models\User;
use App\Support\AcquisitionSource;
use Carbon\CarbonImmutable;

final class AttributeReferral
{
    public function __invoke(User $user): ?Referral
    {
        if ($user->referredBy()->exists()) {
            return $user->referredBy()->first();
        }

        $touch = AcquisitionSource::pullReferral();

        if ($touch === null) {
            return null;
        }

        $capturedAt = CarbonImmutable::parse($touch['captured_at']);
        $wallet = MileWallet::query()->where('referral_code', $touch['code'])->first();

        if (! $wallet instanceof MileWallet
            || (int) $wallet->user_id === (int) $user->getKey()
            || $capturedAt->addDays(30)->isPast()
            || ($user->created_at !== null && $user->created_at->lt($capturedAt))) {
            return null;
        }

        $sameDevice = isset($touch['device_hash']) && Referral::query()
            ->where('touch_device_hash', $touch['device_hash'])
            ->exists();
        $sameIpVelocity = isset($touch['ip_hash']) && Referral::query()
            ->where('touch_ip_hash', $touch['ip_hash'])
            ->where('attributed_at', '>=', now()->subDays(7))
            ->count() >= 3;
        $reviewReason = $sameDevice ? 'same_device' : ($sameIpVelocity ? 'ip_velocity' : null);

        return Referral::query()->firstOrCreate(
            ['referred_user_id' => $user->getKey()],
            [
                'referrer_id' => $wallet->user_id,
                'code' => $wallet->referral_code,
                'status' => $reviewReason === null ? 'pending' : 'review',
                'review_reason' => $reviewReason,
                'touch_ip_hash' => $touch['ip_hash'] ?? null,
                'touch_device_hash' => $touch['device_hash'] ?? null,
                'attributed_at' => $capturedAt,
            ],
        );
    }
}
