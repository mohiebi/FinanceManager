<?php

namespace App\Http\Controllers;

use App\Actions\Admin\BuildAdminAnalytics;
use App\Actions\Admin\BuildCustomerDirectoryQuery;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __invoke(
        Request $request,
        BuildAdminAnalytics $analytics,
        BuildCustomerDirectoryQuery $directory,
    ): Response {
        $range = $this->range($request);
        $filters = $directory->filters($request);

        return Inertia::render('admin/Dashboard', [
            'summary' => $analytics->summary($range),
            'analytics' => Inertia::defer(fn (): array => $analytics->charts($range), 'analytics'),
            'users' => $this->users($directory, $filters),
            'filters' => $filters,
            'range' => $range,
        ]);
    }

    /**
     * @param  array{search: string, activity: string, telegram: string, verification: string, sort: string}  $filters
     * @return array<string, mixed>
     */
    private function users(BuildCustomerDirectoryQuery $directory, array $filters): array
    {
        return $directory->query($filters)
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'locale' => $user->locale,
                'signup_source' => $user->signup_source,
                'joined_at' => $user->created_at->toIso8601String(),
                'last_active_at' => $user->last_active_at?->toIso8601String(),
                'is_verified' => $user->email_verified_at !== null,
                'profile_complete' => $user->birthdate !== null,
                'telegram_connected' => $user->hasTelegram(),
                'auth_method' => $directory->authenticationMethod($user),
                'transaction_count' => (int) $user->transactions_count,
                'investment_count' => (int) $user->investments_count,
                'bill_count' => (int) $user->bills_count,
            ])
            ->toArray();
    }

    private function range(Request $request): string
    {
        $range = (string) $request->query('range');

        return in_array($range, BuildAdminAnalytics::RANGES, true) ? $range : BuildAdminAnalytics::RANGES[0];
    }
}
