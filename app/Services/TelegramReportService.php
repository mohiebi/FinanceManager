<?php

namespace App\Services;

use App\Enums\AssetType;
use App\Enums\TransactionType;
use App\Models\User;
use Carbon\Carbon;

class TelegramReportService
{
    public function daily(User $user, Carbon $date): string
    {
        return $this->buildReport($user, $date->copy()->startOfDay(), $date->copy()->endOfDay(), "Daily Report - {$date->format('D, M j')}");
    }

    public function weekly(User $user, Carbon $date): string
    {
        return $this->buildReport($user, $date->copy()->startOfWeek(), $date->copy()->endOfWeek(), "Weekly Report - {$date->format('M j')} week");
    }

    public function monthly(User $user, Carbon $date): string
    {
        return $this->buildReport($user, $date->copy()->startOfMonth(), $date->copy()->endOfMonth(), "Monthly Report - {$date->format('F Y')}");
    }

    private function buildReport(User $user, Carbon $from, Carbon $to, string $title): string
    {
        $transactions = $user->transactions()
            ->with('category')
            ->whereBetween('occurred_at', [$from->toDateString(), $to->toDateString()])
            ->get();

        $income = $transactions->where('type', TransactionType::Income)->sum('amount');
        $cost = $transactions->where('type', TransactionType::Cost)->sum('amount');
        $net = $income - $cost;

        $costByCategory = $transactions
            ->where('type', TransactionType::Cost)
            ->groupBy(fn ($transaction) => $transaction->category?->name ?? 'Other');

        $lines = ["*{$title}*", ''];
        $lines[] = 'Income: *'.number_format($income, 0).'*';
        $lines[] = 'Costs: *'.number_format($cost, 0).'*';
        $lines[] = 'Net: *'.number_format($net, 0).'*';

        if ($costByCategory->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '*Cost Breakdown:*';
            foreach ($costByCategory as $category => $items) {
                $lines[] = "- {$category}: ".number_format($items->sum('amount'), 0);
            }
        }

        $investments = $user->investments()
            ->whereBetween('occurred_at', [$from->toDateString(), $to->toDateString()])
            ->get();

        if ($investments->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '*Investments:*';
            foreach ($investments as $investment) {
                $type = $investment->asset_type instanceof AssetType
                    ? $investment->asset_type->label()
                    : $investment->asset_type;

                $lines[] = "- {$type}: {$investment->quantity}";
            }
        }

        if ($transactions->isEmpty() && $investments->isEmpty()) {
            $lines[] = '';
            $lines[] = '_No activity in this period._';
        }

        return implode("\n", $lines);
    }
}
