<?php

namespace App\Http\Controllers;

use App\Actions\Admin\BuildAdminAnalytics;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __invoke(Request $request, BuildAdminAnalytics $analytics): Response
    {
        $filters = $this->filters($request);

        return Inertia::render('admin/Dashboard', [
            'summary' => $analytics->summary(),
            'analytics' => Inertia::defer(fn (): array => $analytics->charts(), 'analytics'),
            'users' => $this->users($filters),
            'filters' => $filters,
        ]);
    }

    /**
     * @return array{search: string, activity: string, telegram: string, verification: string, sort: string}
     */
    private function filters(Request $request): array
    {
        return [
            'search' => mb_substr(trim((string) $request->query('search')), 0, 100),
            'activity' => $this->allowedValue((string) $request->query('activity'), ['all', 'online', '7d', '30d', 'inactive', 'never']),
            'telegram' => $this->allowedValue((string) $request->query('telegram'), ['all', 'connected', 'disconnected']),
            'verification' => $this->allowedValue((string) $request->query('verification'), ['all', 'verified', 'unverified']),
            'sort' => $this->allowedValue((string) $request->query('sort'), ['newest', 'oldest', 'last_active']),
        ];
    }

    /**
     * @param  array{search: string, activity: string, telegram: string, verification: string, sort: string}  $filters
     * @return array<string, mixed>
     */
    private function users(array $filters): array
    {
        $now = now();
        $query = User::query()
            ->customers()
            ->select([
                'id',
                'name',
                'email',
                'password',
                'email_verified_at',
                'birthdate',
                'created_at',
                'last_active_at',
                'telegram_chat_id',
            ])
            ->withCount(['transactions', 'investments', 'bills'])
            ->withExists([
                'socialAccounts as has_google_account' => fn (Builder $query): Builder => $query
                    ->where('provider', SocialAccount::ProviderGoogle),
            ])
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $query) use ($filters): void {
                    $query->where('name', 'like', "%{$filters['search']}%")
                        ->orWhere('email', 'like', "%{$filters['search']}%");
                });
            })
            ->when($filters['activity'] === 'online', fn (Builder $query): Builder => $query
                ->where('last_active_at', '>=', $now->copy()->subMinutes(15)))
            ->when($filters['activity'] === '7d', fn (Builder $query): Builder => $query
                ->where('last_active_at', '>=', $now->copy()->subDays(7)))
            ->when($filters['activity'] === '30d', fn (Builder $query): Builder => $query
                ->where('last_active_at', '>=', $now->copy()->subDays(30)))
            ->when($filters['activity'] === 'inactive', fn (Builder $query): Builder => $query
                ->where('last_active_at', '<', $now->copy()->subDays(30)))
            ->when($filters['activity'] === 'never', fn (Builder $query): Builder => $query
                ->whereNull('last_active_at'))
            ->when($filters['telegram'] === 'connected', fn (Builder $query): Builder => $query
                ->whereNotNull('telegram_chat_id'))
            ->when($filters['telegram'] === 'disconnected', fn (Builder $query): Builder => $query
                ->whereNull('telegram_chat_id'))
            ->when($filters['verification'] === 'verified', fn (Builder $query): Builder => $query
                ->whereNotNull('email_verified_at'))
            ->when($filters['verification'] === 'unverified', fn (Builder $query): Builder => $query
                ->whereNull('email_verified_at'));

        match ($filters['sort']) {
            'oldest' => $query->oldest('created_at'),
            'last_active' => $query->orderByDesc('last_active_at')->orderByDesc('created_at'),
            default => $query->latest('created_at'),
        };

        return $query
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'joined_at' => $user->created_at->toIso8601String(),
                'last_active_at' => $user->last_active_at?->toIso8601String(),
                'is_verified' => $user->email_verified_at !== null,
                'profile_complete' => $user->birthdate !== null,
                'telegram_connected' => $user->hasTelegram(),
                'auth_method' => $this->authenticationMethod($user),
                'transaction_count' => (int) $user->transactions_count,
                'investment_count' => (int) $user->investments_count,
                'bill_count' => (int) $user->bills_count,
            ])
            ->toArray();
    }

    private function allowedValue(string $value, array $allowed): string
    {
        return in_array($value, $allowed, true) ? $value : $allowed[0];
    }

    private function authenticationMethod(User $user): string
    {
        if ($user->password !== null && $user->has_google_account) {
            return 'Password + Google';
        }

        return $user->has_google_account ? 'Google' : 'Password';
    }
}
