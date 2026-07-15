<?php

namespace App\Actions\Admin;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class BuildAdminAnalytics
{
    /**
     * @return array<string, int|float|null>
     */
    public function summary(): array
    {
        $now = now();
        $totalCustomers = $this->customers()->count();
        $newCustomers = $this->customers()
            ->where('created_at', '>=', $now->copy()->subDays(30))
            ->count();
        $previousCustomers = $this->customers()
            ->where('created_at', '>=', $now->copy()->subDays(60))
            ->where('created_at', '<', $now->copy()->subDays(30))
            ->count();
        $telegramCustomers = $this->customers()->whereNotNull('telegram_chat_id')->count();
        $verifiedCustomers = $this->customers()->whereNotNull('email_verified_at')->count();
        $completedProfiles = $this->customers()->whereNotNull('birthdate')->count();

        return [
            'total_customers' => $totalCustomers,
            'new_customers_30d' => $newCustomers,
            'new_customers_change' => $previousCustomers > 0
                ? round((($newCustomers - $previousCustomers) / $previousCustomers) * 100, 1)
                : null,
            'online_customers' => $this->customers()
                ->where('last_active_at', '>=', $now->copy()->subMinutes(15))
                ->count(),
            'active_customers_7d' => $this->customers()
                ->where('last_active_at', '>=', $now->copy()->subDays(7))
                ->count(),
            'active_customers_30d' => $this->customers()
                ->where('last_active_at', '>=', $now->copy()->subDays(30))
                ->count(),
            'telegram_customers' => $telegramCustomers,
            'telegram_adoption' => $this->percentage($telegramCustomers, $totalCustomers),
            'verified_customers' => $verifiedCustomers,
            'verification_rate' => $this->percentage($verifiedCustomers, $totalCustomers),
            'completed_profiles' => $completedProfiles,
            'profile_completion_rate' => $this->percentage($completedProfiles, $totalCustomers),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function charts(): array
    {
        return [
            'growth' => $this->growth(),
            'product_adoption' => $this->productAdoption(),
            'authentication_mix' => $this->authenticationMix(),
            'locales' => $this->locales(),
        ];
    }

    /**
     * @return array{labels: array<int, string>, new_customers: array<int, int>, cumulative_customers: array<int, int>}
     */
    private function growth(): array
    {
        $firstMonth = now()->startOfMonth()->subMonths(11);
        $cumulative = $this->customers()->where('created_at', '<', $firstMonth)->count();
        $labels = [];
        $newCustomers = [];
        $cumulativeCustomers = [];

        foreach (range(0, 11) as $offset) {
            $month = $firstMonth->copy()->addMonths($offset);
            $count = $this->customers()
                ->whereBetween('created_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
                ->count();

            $cumulative += $count;
            $labels[] = $month->format('M Y');
            $newCustomers[] = $count;
            $cumulativeCustomers[] = $cumulative;
        }

        return [
            'labels' => $labels,
            'new_customers' => $newCustomers,
            'cumulative_customers' => $cumulativeCustomers,
        ];
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    private function productAdoption(): array
    {
        return [
            'labels' => ['Transactions', 'Investments', 'Bills', 'Telegram'],
            'values' => [
                $this->customers()->whereHas('transactions')->count(),
                $this->customers()->whereHas('investments')->count(),
                $this->customers()->whereHas('bills')->count(),
                $this->customers()->whereNotNull('telegram_chat_id')->count(),
            ],
        ];
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    private function authenticationMix(): array
    {
        $googleAccount = fn (Builder $query): Builder => $query->where('provider', SocialAccount::ProviderGoogle);

        return [
            'labels' => ['Password only', 'Google only', 'Password and Google'],
            'values' => [
                $this->customers()->whereNotNull('password')->whereDoesntHave('socialAccounts', $googleAccount)->count(),
                $this->customers()->whereNull('password')->whereHas('socialAccounts', $googleAccount)->count(),
                $this->customers()->whereNotNull('password')->whereHas('socialAccounts', $googleAccount)->count(),
            ],
        ];
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    private function locales(): array
    {
        $localeNames = [
            'en' => 'English',
            'fa' => 'Persian',
            'de' => 'German',
        ];
        $counts = $this->customers()
            ->selectRaw('locale, COUNT(*) as aggregate')
            ->groupBy('locale')
            ->pluck('aggregate', 'locale');

        return [
            'labels' => collect($localeNames)->keys()->map(fn (string $locale): string => $localeNames[$locale])->all(),
            'values' => collect($localeNames)->keys()->map(fn (string $locale): int => (int) ($counts[$locale] ?? 0))->all(),
        ];
    }

    /**
     * @return Builder<User>
     */
    private function customers(): Builder
    {
        return User::query()->customers();
    }

    private function percentage(int $value, int $total): float
    {
        return $total > 0 ? round(($value / $total) * 100, 1) : 0.0;
    }
}
