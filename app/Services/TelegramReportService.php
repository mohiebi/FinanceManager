<?php

namespace App\Services;

use App\Actions\Transactions\CurrencyConverter;
use App\Enums\Currency;
use App\Enums\TransactionType;
use App\Models\User;
use App\Support\DateFormatter;
use App\Support\FrontendLocalization;
use Carbon\Carbon;
use Morilog\Jalali\Jalalian;

class TelegramReportService
{
    public function __construct(private readonly CurrencyConverter $converter) {}

    public function daily(User $user, Carbon $date): string
    {
        $calendar = FrontendLocalization::normalizeCalendar($user->calendar);

        return $this->buildReport($user, $date->copy()->startOfDay(), $date->copy()->endOfDay(), 'Daily Report - '.DateFormatter::format($date, $calendar, 'Y-m-d'));
    }

    public function weekly(User $user, Carbon $date): string
    {
        $calendar = FrontendLocalization::normalizeCalendar($user->calendar);
        [$from, $to] = $this->weekRange($date, $calendar);

        return $this->buildReport($user, $from, $to, 'Weekly Report - '.DateFormatter::format($date, $calendar, 'M j').' week');
    }

    public function monthly(User $user, Carbon $date): string
    {
        $calendar = FrontendLocalization::normalizeCalendar($user->calendar);
        [$from, $to] = $this->monthRange($date, $calendar);

        return $this->buildReport($user, $from, $to, 'Monthly Report - '.DateFormatter::format($date, $calendar, 'Y-m'));
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function monthRange(Carbon $date, string $calendar): array
    {
        if ($calendar !== 'jalali') {
            return [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()];
        }

        $jalali = Jalalian::fromCarbon($date);
        $from = Carbon::instance(
            (new Jalalian($jalali->getYear(), $jalali->getMonth(), 1))->toCarbon()
        )->startOfDay();

        $to = Carbon::instance(
            (new Jalalian($jalali->getYear(), $jalali->getMonth(), (int) $jalali->format('t')))->toCarbon()
        )->endOfDay();

        return [$from, $to];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function weekRange(Carbon $date, string $calendar): array
    {
        if ($calendar !== 'jalali') {
            return [$date->copy()->startOfWeek(), $date->copy()->endOfWeek()];
        }

        $jalali = Jalalian::fromCarbon($date);

        return [
            Carbon::instance($jalali->getFirstDayOfWeek()->toCarbon())->startOfDay(),
            Carbon::instance($jalali->getEndDayOfWeek()->toCarbon())->endOfDay(),
        ];
    }

    private function buildReport(User $user, Carbon $from, Carbon $to, string $title): string
    {
        $transactions = $user->transactions()
            ->with('category')
            ->whereBetween('occurred_at', [$from->toDateString(), $to->toDateString()])
            ->get();

        // Convert every transaction to Toman before summing
        $income = 0.0;
        $cost = 0.0;

        foreach ($transactions as $t) {
            $toman = $this->converter->convert($t->amount, $t->currency, Currency::Toman);
            if ($t->type === TransactionType::Income) {
                $income += $toman;
            } else {
                $cost += $toman;
            }
        }

        $net = $income - $cost;

        // Cost breakdown per category — also in Toman
        $costByCategory = [];
        foreach ($transactions->where('type', TransactionType::Cost) as $t) {
            $key = $t->category?->name ?? 'Other';
            $costByCategory[$key] = ($costByCategory[$key] ?? 0.0)
                + $this->converter->convert($t->amount, $t->currency, Currency::Toman);
        }

        $lines = ["*{$title}*", ''];
        $lines[] = 'Income: *'.$this->fmt($income).' T*';
        $lines[] = 'Costs: *'.$this->fmt($cost).' T*';
        $lines[] = 'Net: *'.($net >= 0 ? '' : '-').$this->fmt(abs($net)).' T*';

        if ($costByCategory) {
            $lines[] = '';
            $lines[] = '*Cost Breakdown:*';
            arsort($costByCategory);
            foreach ($costByCategory as $category => $amount) {
                $lines[] = "- {$category}: ".$this->fmt($amount).' T';
            }
        }

        $investments = $user->investments()
            ->with('asset')
            ->whereBetween('occurred_at', [$from->toDateString(), $to->toDateString()])
            ->get();

        if ($investments->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '*Investments:*';
            foreach ($investments as $investment) {
                $label = $investment->asset?->label() ?? ($investment->asset_type ?? 'Asset');
                $qty = rtrim(rtrim(number_format((float) $investment->quantity, 8, '.', ''), '0'), '.');
                $unit = $investment->asset?->unit ?? '';
                $lines[] = "- {$label}: {$qty} {$unit}";
            }
        }

        if ($transactions->isEmpty() && $investments->isEmpty()) {
            $lines[] = '';
            $lines[] = '_No activity in this period._';
        }

        return implode("\n", $lines);
    }

    private function fmt(float $amount): string
    {
        return number_format(round($amount), 0, '.', ',');
    }
}
