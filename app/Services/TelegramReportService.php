<?php

namespace App\Services;

use App\Actions\Transactions\CurrencyConverter;
use App\Enums\Currency;
use App\Enums\Feature;
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
        $title = $this->line($user, 'daily_title', ['date' => DateFormatter::format($date, $calendar, 'Y-m-d')]);

        return $this->buildReport($user, $date->copy()->startOfDay(), $date->copy()->endOfDay(), $title);
    }

    public function weekly(User $user, Carbon $date): string
    {
        $calendar = FrontendLocalization::normalizeCalendar($user->calendar);
        [$from, $to] = $this->weekRange($date, $calendar);
        $label = $calendar === 'jalali'
            ? DateFormatter::format($date, $calendar, 'F j')
            : $date->copy()->locale($this->locale($user))->translatedFormat('M j');

        return $this->buildReport($user, $from, $to, $this->line($user, 'weekly_title', ['date' => $label]));
    }

    public function monthly(User $user, Carbon $date): string
    {
        $calendar = FrontendLocalization::normalizeCalendar($user->calendar);
        [$from, $to] = $this->monthRange($date, $calendar);
        $title = $this->line($user, 'monthly_title', ['date' => DateFormatter::format($date, $calendar, 'Y-m')]);

        return $this->buildReport($user, $from, $to, $title);
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
            $key = $t->category?->name ?? $this->line($user, 'uncategorized');
            $costByCategory[$key] = ($costByCategory[$key] ?? 0.0)
                + $this->converter->convert($t->amount, $t->currency, Currency::Toman);
        }

        $lines = ["*{$title}*", ''];
        $lines[] = $this->line($user, 'income').': *'.$this->toman($user, $income).'*';
        $lines[] = $this->line($user, 'costs').': *'.$this->toman($user, $cost).'*';
        $lines[] = $this->line($user, 'net').': *'.($net >= 0 ? '' : '-').$this->toman($user, abs($net)).'*';

        if ($costByCategory) {
            $lines[] = '';
            $lines[] = $this->line($user, 'cost_breakdown');
            arsort($costByCategory);
            foreach ($costByCategory as $category => $amount) {
                $lines[] = "- {$category}: ".$this->toman($user, $amount);
            }
        }

        $investments = $user->hasFeature(Feature::Investments)
            ? $user->investments()
                ->with('asset')
                ->whereBetween('occurred_at', [$from->toDateString(), $to->toDateString()])
                ->get()
            : collect();

        if ($investments->isNotEmpty()) {
            $lines[] = '';
            $lines[] = $this->line($user, 'investments');
            foreach ($investments as $investment) {
                $label = $investment->asset?->label() ?? ($investment->asset_type ?? $this->line($user, 'unnamed_asset'));
                $qty = rtrim(rtrim(number_format((float) $investment->quantity, 8, '.', ''), '0'), '.');
                $unit = $investment->asset?->unit ?? '';
                $lines[] = "- {$label}: {$qty} {$unit}";
            }
        }

        if ($transactions->isEmpty() && $investments->isEmpty()) {
            $lines[] = '';
            $lines[] = $this->line($user, 'no_activity');
        }

        return implode("\n", $lines);
    }

    /**
     * Reports are built from a queued job or a webhook, neither of which has set
     * a locale for the request — so the recipient's own language is passed in
     * explicitly rather than read off the application.
     *
     * @param  array<string, mixed>  $replace
     */
    private function line(User $user, string $key, array $replace = []): string
    {
        return trans("telegram.report.{$key}", $replace, $this->locale($user));
    }

    private function locale(User $user): string
    {
        return FrontendLocalization::normalizeLocale($user->locale);
    }

    private function toman(User $user, float $amount): string
    {
        return trans(
            'telegram.amount.'.Currency::Toman->value,
            ['amount' => number_format(round($amount), 0, '.', ',')],
            $this->locale($user),
        );
    }
}
