<?php

namespace App\Http\Controllers;

use App\Actions\Admin\BuildCustomerDirectoryQuery;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminCustomerExportController extends Controller
{
    /**
     * Stream the filtered customer directory as a CSV download.
     */
    public function __invoke(Request $request, BuildCustomerDirectoryQuery $directory): StreamedResponse
    {
        $query = $directory->query($directory->filters($request));

        return response()->streamDownload(function () use ($query, $directory): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Name',
                'Email',
                'Locale',
                'Signup source',
                'Joined at',
                'Last active at',
                'Email verified',
                'Profile complete',
                'Telegram connected',
                'Authentication',
                'Transactions',
                'Investments',
                'Bills',
            ]);

            $query->lazy(500)->each(function (User $user) use ($handle, $directory): void {
                fputcsv($handle, [
                    $user->name,
                    $user->email,
                    $user->locale,
                    $user->signup_source ?? 'direct',
                    $user->created_at->toIso8601String(),
                    $user->last_active_at?->toIso8601String() ?? '',
                    $user->email_verified_at !== null ? 'yes' : 'no',
                    $user->birthdate !== null ? 'yes' : 'no',
                    $user->hasTelegram() ? 'yes' : 'no',
                    $directory->authenticationMethod($user),
                    (int) $user->transactions_count,
                    (int) $user->investments_count,
                    (int) $user->bills_count,
                ]);
            });

            fclose($handle);
        }, 'cashpilot-customers-'.now()->toDateString().'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
