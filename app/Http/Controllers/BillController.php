<?php

namespace App\Http\Controllers;

use App\Actions\Bills\MarkBillOccurrencePaid;
use App\Actions\Bills\SaveBill;
use App\Actions\Bills\SyncBillOccurrence;
use App\Actions\Transactions\CurrencyConverter;
use App\Enums\AssetType;
use App\Enums\BillRecurrenceType;
use App\Enums\Currency;
use App\Enums\TransactionType;
use App\Http\Resources\CategoryResource;
use App\Models\Bill;
use App\Models\BillOccurrence;
use App\Models\Category;
use App\Models\User;
use App\Services\AssetPriceService;
use App\Support\BillDueDateCalculator;
use App\Support\CurrencyPreference;
use App\Support\Encryption\SealedField;
use App\Support\FrontendLocalization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Morilog\Jalali\Jalalian;

class BillController extends Controller
{
    public function index(Request $request, CurrencyConverter $currencyConverter, SyncBillOccurrence $syncBillOccurrence): Response
    {
        $user = $request->user();
        $calendar = FrontendLocalization::normalizeCalendar($user->calendar);
        $selectedCurrency = CurrencyPreference::resolve($request);
        $vaultArmed = $user->vaultIsArmed();
        [$monthFrom, $monthTo] = $this->currentMonthRange($calendar);

        // Proactively generate 3-month lookahead occurrences for all active monthly bills.
        // withMax fetches the latest due_date per bill in the same query, avoiding N+1.
        $user->bills()
            ->where('recurrence_type', BillRecurrenceType::Monthly->value)
            ->where('is_active', true)
            ->withMax('occurrences', 'due_date')
            ->get()
            ->each(fn (Bill $bill) => $syncBillOccurrence->lookahead($bill, 3, $calendar, $bill->occurrences_max_due_date));

        $paymentNumbers = $this->paymentNumbers($user);
        $recentPaidCutoff = Carbon::now()->subDays(10);
        [$dueSoonFrom, $dueSoonTo] = [Carbon::today(), Carbon::today()->addDays(30)];

        $bills = $user->bills()
            ->with([
                'category',
                'occurrences' => fn ($query) => $query->whereNull('paid_at')->orderBy('due_date'),
                'latestPaidOccurrence',
            ])
            ->withCount(['occurrences as paid_occurrence_count' => fn ($query) => $query->whereNotNull('paid_at')])
            ->withMax('occurrences', 'due_date')
            ->withCount(['occurrences as month_occurrence_count' => fn ($query) => $query
                ->whereDate('due_date', '>=', $monthFrom->toDateString())
                ->whereDate('due_date', '<=', $monthTo->toDateString())])
            ->withCount(['occurrences as due_soon_occurrence_count' => fn ($query) => $query
                ->whereDate('due_date', '>=', $dueSoonFrom->toDateString())
                ->whereDate('due_date', '<=', $dueSoonTo->toDateString())])
            ->get()
            ->map(function (Bill $bill) use ($currencyConverter, $request, $selectedCurrency, $vaultArmed, $user, $paymentNumbers, $recentPaidCutoff) {
                $next = $bill->occurrences->first();
                $billCurrency = Currency::tryFrom((string) $bill->currency) ?? $selectedCurrency;
                $recentPaid = $bill->latestPaidOccurrence;
                $recentPaidOccurrence = $recentPaid !== null && $recentPaid->paid_at !== null && $recentPaid->paid_at->greaterThanOrEqualTo($recentPaidCutoff)
                    ? [
                        'id' => $recentPaid->id,
                        'due_date' => $recentPaid->due_date->toDateString(),
                        'paid_at' => $recentPaid->paid_at->toDateString(),
                    ]
                    : null;

                return [
                    'id' => $bill->id,
                    'title' => $bill->title,
                    'amount' => $vaultArmed ? $bill->amount : (float) $bill->amount,
                    'currency' => $bill->currency,
                    // Null under the vault: the server cannot convert what it cannot
                    // read, and a converted zero reads as "this bill costs nothing".
                    'display_amount' => $vaultArmed ? null : $currencyConverter->format(
                        $bill->amount,
                        $billCurrency,
                        $selectedCurrency,
                    ),
                    'display_currency' => $selectedCurrency->value,
                    'month_occurrence_count' => (int) $bill->month_occurrence_count,
                    'due_soon_occurrence_count' => (int) $bill->due_soon_occurrence_count,
                    'recent_paid_occurrence' => $recentPaidOccurrence,
                    'recurrence_type' => $bill->recurrence_type->value,
                    'due_day_of_month' => $bill->due_day_of_month,
                    'due_date' => $bill->due_date?->toDateString(),
                    'recurrence_limit_type' => $bill->recurrence_limit_type?->value,
                    'recurrence_count' => $bill->recurrence_count,
                    'recurrence_end_date' => $bill->recurrence_end_date?->toDateString(),
                    'payments_made' => (int) $bill->paid_occurrence_count,
                    'schedule_search_date' => $next
                        ? $next->due_date->toDateString()
                        : ($bill->occurrences_max_due_date
                            ? Carbon::parse($bill->occurrences_max_due_date)->addDay()->toDateString()
                            : Carbon::today()->toDateString()),
                    'telegram_reminder_enabled' => $bill->telegram_reminder_enabled,
                    'is_active' => $bill->is_active,
                    'category_id' => $bill->category_id,
                    'category_name' => $this->localizedCategoryName($request, $bill->category),
                    'reminder_time' => $bill->reminder_time ?? '09:00',
                    'reminder_timezone' => $bill->reminder_timezone ?? $user->resolvedTimezone(),
                    'next_occurrence' => $next ? [
                        'id' => $next->id,
                        'due_date' => $next->due_date->toDateString(),
                        'payment_number' => $paymentNumbers[$next->id] ?? null,
                    ] : null,
                ];
            })
            ->sortBy(fn (array $bill): string => sprintf(
                '%d|%s|%s',
                $bill['next_occurrence'] === null ? 1 : 0,
                $bill['next_occurrence']['due_date'] ?? '9999-12-31',
                mb_strtolower((string) $bill['title']),
            ))
            ->values();

        $categories = Category::query()
            ->availableFor($user)
            ->where('type', TransactionType::Cost)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category) => (new CategoryResource($category))->resolve($request));

        return Inertia::render('Bills', [
            'bills' => $bills,
            'categories' => $categories,
            'currencies' => collect(Currency::cases())->map(fn (Currency $c) => [
                'label' => $c->label(),
                'value' => $c->value,
            ]),
            'timezones' => $this->timezoneOptions(),
            'selectedCurrency' => $selectedCurrency->value,
            // Rates rather than a total when the server cannot read the amounts —
            // these are public market prices, so shipping them costs no privacy.
            'rates' => $vaultArmed ? $this->displayRates() : null,
            'monthlyBillSummary' => $this->monthlyBillSummary($user, $currencyConverter, $selectedCurrency, $vaultArmed, $monthFrom, $monthTo),
            'dueSoonSummary' => $this->dueSoonSummary($user, $currencyConverter, $selectedCurrency, $vaultArmed, $dueSoonFrom, $dueSoonTo),
            // Null when the vault is armed — the server cannot read transaction
            // amounts to net a balance, and a wrong number is worse than none.
            'balanceSummary' => $vaultArmed ? null : $this->balanceSummary($user, $currencyConverter, $selectedCurrency, $monthFrom, $monthTo),
            'calendarOccurrences' => $this->calendarOccurrences($user, $monthFrom, $monthTo),
            'upcomingOccurrences' => $this->upcomingOccurrences($user, $currencyConverter, $selectedCurrency, $vaultArmed, $paymentNumbers),
            'userCalendar' => $calendar,
            'today' => Carbon::today()->toDateString(),
        ]);
    }

    public function store(Request $request, SaveBill $saveBill): RedirectResponse
    {
        $saveBill->create($request->user(), $this->validatedBillData($request));

        return back();
    }

    public function update(Request $request, Bill $bill, SaveBill $saveBill): RedirectResponse
    {
        $saveBill->update($bill, $this->validatedBillData($request));

        return back();
    }

    public function destroy(Request $request, Bill $bill): RedirectResponse
    {
        $bill->delete();

        return back();
    }

    /**
     * Mark an occurrence paid, generating the matching Cost transaction.
     *
     * Under the vault the browser sends the title and amount re-encrypted for the
     * `transactions` table — the bill's own ciphertext is bound to the `bills` AAD
     * and the server has no key to re-seal it.
     */
    public function markPaid(Request $request, Bill $bill, BillOccurrence $occurrence, MarkBillOccurrencePaid $markBillOccurrencePaid): RedirectResponse
    {
        // The bill is owner-scoped by its binding; this is the parentage check,
        // which binding cannot make for us — the occurrence has no `user_id`.
        abort_unless((int) $occurrence->bill_id === (int) $bill->id, 404);

        $sealed = null;

        if ($request->user()->vaultIsArmed()) {
            $sealed = $request->validate([
                'title' => SealedField::rules(),
                'amount' => SealedField::rules(),
            ]);
        }

        $markBillOccurrencePaid($bill, $occurrence, $sealed);

        return back();
    }

    /**
     * All unpaid occurrences up to the end of next month, ordered by due date.
     *
     * The step is deliberately overflow-free, like {@see BillDueDateCalculator}:
     * plain `addMonths()` turns the 31st into the 1st of the month after next,
     * and the `endOfMonth()` on top of it then stretched the horizon to that
     * month's end — so on the 31st the page showed a whole extra month of bills
     * that it had not shown the day before.
     *
     * @return array<int, array<string, mixed>>
     */
    private function upcomingOccurrences(
        User $user,
        CurrencyConverter $currencyConverter,
        Currency $selectedCurrency,
        bool $vaultArmed,
        array $paymentNumbers,
    ): array {
        $today = Carbon::today();
        $horizon = $today->copy()->addMonthNoOverflow()->endOfMonth();

        return BillOccurrence::query()
            ->whereNull('paid_at')
            ->whereHas('bill', fn (Builder $q) => $q
                ->where('user_id', $user->id)
                ->where('is_active', true))
            ->with(['bill'])
            ->whereBetween('due_date', [$today->toDateString(), $horizon->toDateString()])
            ->orderBy('due_date')
            ->get()
            ->filter(fn (BillOccurrence $occurrence) => $occurrence->bill !== null)
            ->map(function (BillOccurrence $occurrence) use ($currencyConverter, $selectedCurrency, $today, $vaultArmed, $paymentNumbers): array {
                $bill = $occurrence->bill;
                $billCurrency = Currency::tryFrom((string) $bill->currency) ?? $selectedCurrency;

                return [
                    'occurrence_id' => $occurrence->id,
                    'bill_id' => $bill->id,
                    'title' => $bill->title,
                    'amount' => $vaultArmed ? $bill->amount : (float) $bill->amount,
                    'currency' => $billCurrency->value,
                    'display_amount' => $vaultArmed
                        ? null
                        : $currencyConverter->format($bill->amount, $billCurrency, $selectedCurrency),
                    'display_currency' => $selectedCurrency->value,
                    'due_date' => $occurrence->due_date->toDateString(),
                    'payment_number' => $paymentNumbers[$occurrence->id] ?? null,
                    'payment_count' => $bill->recurrence_count,
                    'is_overdue' => $occurrence->due_date->lt($today),
                    'is_due_today' => $occurrence->due_date->isSameDay($today),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Build exact ordinals in one query so cards and the timeline can say
     * "payment 3 of 7" without an occurrence-count query per rendered row.
     *
     * @return array<int, int>
     */
    private function paymentNumbers(User $user): array
    {
        $numbers = [];
        $billCounts = [];

        BillOccurrence::query()
            ->whereHas('bill', fn (Builder $query) => $query->where('user_id', $user->id))
            ->orderBy('bill_id')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get(['id', 'bill_id'])
            ->each(function (BillOccurrence $occurrence) use (&$numbers, &$billCounts): void {
                $billCounts[$occurrence->bill_id] = ($billCounts[$occurrence->bill_id] ?? 0) + 1;
                $numbers[$occurrence->id] = $billCounts[$occurrence->bill_id];
            });

        return $numbers;
    }

    private function localizedCategoryName(Request $request, ?Category $category): ?string
    {
        if (! $category) {
            return null;
        }

        return (string) (new CategoryResource($category))->resolve($request)['name'];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedBillData(Request $request): array
    {
        $vaultArmed = $request->user()->vaultIsArmed();
        $validated = $request->validate(SaveBill::rules($request->user(), $vaultArmed));

        return SaveBill::normalize(
            $validated,
            $request->user(),
            $request->filled('category_id'),
            $request->has('telegram_reminder_enabled') ? $request->boolean('telegram_reminder_enabled') : null,
            $vaultArmed,
        );
    }

    /**
     * @return array{tomanPerUsd: float, tomanPerEur: float}
     */
    private function displayRates(): array
    {
        $prices = app(AssetPriceService::class);

        return [
            'tomanPerUsd' => $prices->priceFor(AssetType::Usd),
            'tomanPerEur' => $prices->priceFor(AssetType::Eur),
        ];
    }

    /**
     * @return array{amount: string|null, currency: string, count: int, from: string, to: string}
     */
    private function monthlyBillSummary(
        User $user,
        CurrencyConverter $currencyConverter,
        Currency $selectedCurrency,
        bool $vaultArmed,
        Carbon $fromDate,
        Carbon $toDate,
    ): array {
        $bills = $user->bills()
            ->with(['occurrences' => fn ($query) => $query
                ->whereDate('due_date', '>=', $fromDate->toDateString())
                ->whereDate('due_date', '<=', $toDate->toDateString())])
            ->get();

        $total = 0.0;
        $count = 0;

        foreach ($bills as $bill) {
            $occurrenceCount = $bill->occurrences->count();

            if ($occurrenceCount === 0) {
                continue;
            }

            $billCurrency = Currency::tryFrom((string) $bill->currency) ?? $selectedCurrency;
            $count += $occurrenceCount;

            if ($vaultArmed) {
                continue;
            }

            $total += $currencyConverter->convert($bill->amount, $billCurrency, $selectedCurrency) * $occurrenceCount;
        }

        return [
            // The browser totals this from the decrypted amounts and each bill's
            // month_occurrence_count when the vault is armed.
            'amount' => $vaultArmed ? null : number_format(round($total, 2), 2, '.', ''),
            'currency' => $selectedCurrency->value,
            'count' => $count,
            'from' => $fromDate->toDateString(),
            'to' => $toDate->toDateString(),
        ];
    }

    /**
     * Total due across a rolling 30-day window from today — the figure the
     * "due soon" summary bar shows, deliberately not tied to calendar-month
     * boundaries so a bill landing just after month-end still counts.
     *
     * @return array{amount: string|null, currency: string, count: int, from: string, to: string}
     */
    private function dueSoonSummary(
        User $user,
        CurrencyConverter $currencyConverter,
        Currency $selectedCurrency,
        bool $vaultArmed,
        Carbon $fromDate,
        Carbon $toDate,
    ): array {
        $bills = $user->bills()
            ->with(['occurrences' => fn ($query) => $query
                ->whereDate('due_date', '>=', $fromDate->toDateString())
                ->whereDate('due_date', '<=', $toDate->toDateString())])
            ->get();

        $total = 0.0;
        $count = 0;

        foreach ($bills as $bill) {
            $occurrenceCount = $bill->occurrences->count();

            if ($occurrenceCount === 0) {
                continue;
            }

            $billCurrency = Currency::tryFrom((string) $bill->currency) ?? $selectedCurrency;
            $count += $occurrenceCount;

            if ($vaultArmed) {
                continue;
            }

            $total += $currencyConverter->convert($bill->amount, $billCurrency, $selectedCurrency) * $occurrenceCount;
        }

        return [
            // The browser totals this from the decrypted amounts and each bill's
            // due_soon_occurrence_count when the vault is armed.
            'amount' => $vaultArmed ? null : number_format(round($total, 2), 2, '.', ''),
            'currency' => $selectedCurrency->value,
            'count' => $count,
            'from' => $fromDate->toDateString(),
            'to' => $toDate->toDateString(),
        ];
    }

    /**
     * This period's income minus cost — the same "balance" figure the
     * Dashboard's own hero card is built from — so the page can honestly say
     * whether it covers what is coming due. Null (not computed) under the
     * vault, since transaction amounts are ciphertext the server cannot sum.
     *
     * @return array{balance: float, currency: string}
     */
    private function balanceSummary(
        User $user,
        CurrencyConverter $currencyConverter,
        Currency $selectedCurrency,
        Carbon $fromDate,
        Carbon $toDate,
    ): array {
        $transactions = $user->transactions()
            ->whereDate('occurred_at', '>=', $fromDate->toDateString())
            ->whereDate('occurred_at', '<=', $toDate->toDateString())
            ->get();

        $income = (float) $currencyConverter->sumFormatted(
            $transactions->where('type', TransactionType::Income),
            $selectedCurrency,
        );
        $cost = (float) $currencyConverter->sumFormatted(
            $transactions->where('type', TransactionType::Cost),
            $selectedCurrency,
        );

        return [
            'balance' => round($income - $cost, 2),
            'currency' => $selectedCurrency->value,
        ];
    }

    /**
     * Every occurrence — paid or not — due within the given month, for the
     * calendar view's day cells. Titles travel in whatever shape
     * {@see UserEncrypted} already returns them (plaintext or ciphertext),
     * same convention as every other bill field here.
     *
     * @return array<int, array<string, mixed>>
     */
    private function calendarOccurrences(User $user, Carbon $monthFrom, Carbon $monthTo): array
    {
        return BillOccurrence::query()
            ->whereHas('bill', fn (Builder $q) => $q->where('user_id', $user->id))
            ->whereBetween('due_date', [$monthFrom->toDateString(), $monthTo->toDateString()])
            ->with('bill.category')
            ->orderBy('due_date')
            ->get()
            ->filter(fn (BillOccurrence $occurrence) => $occurrence->bill !== null)
            ->map(fn (BillOccurrence $occurrence): array => [
                'id' => $occurrence->id,
                'bill_id' => $occurrence->bill->id,
                'due_date' => $occurrence->due_date->toDateString(),
                'is_paid' => $occurrence->isPaid(),
                'title' => $occurrence->bill->title,
                'color' => $occurrence->bill->category?->color,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function timezoneOptions(): array
    {
        return [
            ['value' => 'UTC', 'label' => 'UTC (UTC+0)'],
            ['value' => 'Asia/Tehran', 'label' => 'Tehran (UTC+3:30)'],
            ['value' => 'Asia/Dubai', 'label' => 'Dubai (UTC+4)'],
            ['value' => 'Asia/Riyadh', 'label' => 'Riyadh (UTC+3)'],
            ['value' => 'Europe/Istanbul', 'label' => 'Istanbul (UTC+3)'],
            ['value' => 'Africa/Cairo', 'label' => 'Cairo (UTC+2)'],
            ['value' => 'Europe/Moscow', 'label' => 'Moscow (UTC+3)'],
            ['value' => 'Europe/London', 'label' => 'London (UTC+0/+1)'],
            ['value' => 'Europe/Paris', 'label' => 'Paris (UTC+1/+2)'],
            ['value' => 'Europe/Berlin', 'label' => 'Berlin (UTC+1/+2)'],
            ['value' => 'Asia/Karachi', 'label' => 'Karachi (UTC+5)'],
            ['value' => 'Asia/Kolkata', 'label' => 'India (UTC+5:30)'],
            ['value' => 'Asia/Dhaka', 'label' => 'Dhaka (UTC+6)'],
            ['value' => 'Asia/Bangkok', 'label' => 'Bangkok (UTC+7)'],
            ['value' => 'Asia/Singapore', 'label' => 'Singapore (UTC+8)'],
            ['value' => 'Asia/Shanghai', 'label' => 'China (UTC+8)'],
            ['value' => 'Asia/Tokyo', 'label' => 'Tokyo (UTC+9)'],
            ['value' => 'Australia/Sydney', 'label' => 'Sydney (UTC+10/+11)'],
            ['value' => 'America/New_York', 'label' => 'New York (UTC-5/-4)'],
            ['value' => 'America/Chicago', 'label' => 'Chicago (UTC-6/-5)'],
            ['value' => 'America/Los_Angeles', 'label' => 'Los Angeles (UTC-8/-7)'],
            ['value' => 'America/Toronto', 'label' => 'Toronto (UTC-5/-4)'],
            ['value' => 'America/Sao_Paulo', 'label' => 'São Paulo (UTC-3)'],
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function currentMonthRange(string $calendar): array
    {
        $today = Carbon::today();

        if ($calendar === 'jalali') {
            $jalaliToday = Jalalian::fromCarbon($today);
            $year = $jalaliToday->getYear();
            $month = $jalaliToday->getMonth();
            $daysInMonth = (int) $jalaliToday->format('t');

            return [
                Carbon::instance((new Jalalian($year, $month, 1))->toCarbon())->startOfDay(),
                Carbon::instance((new Jalalian($year, $month, $daysInMonth))->toCarbon())->endOfDay(),
            ];
        }

        return [
            $today->copy()->startOfMonth(),
            $today->copy()->endOfMonth(),
        ];
    }
}
