<?php

namespace App\Http\Controllers;

use App\Actions\Bills\MarkBillOccurrencePaid;
use App\Actions\Bills\SyncBillOccurrence;
use App\Actions\Transactions\CurrencyConverter;
use App\Enums\BillRecurrenceType;
use App\Enums\Currency;
use App\Enums\TransactionType;
use App\Models\Bill;
use App\Models\BillOccurrence;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillController extends Controller
{
    public function index(Request $request, CurrencyConverter $currencyConverter): Response
    {
        $user = $request->user();
        $selectedCurrency = Currency::tryFrom((string) $request->query('currency')) ?? Currency::Toman;

        $bills = $user->bills()
            ->with(['category', 'occurrences' => fn ($query) => $query->whereNull('paid_at')->orderBy('due_date')])
            ->get()
            ->map(function (Bill $bill) use ($currencyConverter, $selectedCurrency) {
                $next = $bill->occurrences->first();
                $billCurrency = Currency::tryFrom((string) $bill->currency) ?? $selectedCurrency;

                return [
                    'id' => $bill->id,
                    'title' => $bill->title,
                    'amount' => (float) $bill->amount,
                    'currency' => $bill->currency,
                    'display_amount' => $currencyConverter->format(
                        $bill->amount,
                        $billCurrency,
                        $selectedCurrency,
                    ),
                    'display_currency' => $selectedCurrency->value,
                    'recurrence_type' => $bill->recurrence_type->value,
                    'due_day_of_month' => $bill->due_day_of_month,
                    'due_date' => $bill->due_date?->toDateString(),
                    'telegram_reminder_enabled' => $bill->telegram_reminder_enabled,
                    'is_active' => $bill->is_active,
                    'category_id' => $bill->category_id,
                    'category_name' => $bill->category?->name,
                    'next_occurrence' => $next ? [
                        'id' => $next->id,
                        'due_date' => $next->due_date->toDateString(),
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
            ->get(['id', 'name']);

        return Inertia::render('Bills', [
            'bills' => $bills,
            'categories' => $categories,
            'currencies' => collect(Currency::cases())->map(fn (Currency $c) => [
                'label' => $c->label(),
                'value' => $c->value,
            ]),
            'selectedCurrency' => $selectedCurrency->value,
            'userCalendar' => $user->calendar ?? 'gregorian',
        ]);
    }

    public function store(Request $request, SyncBillOccurrence $syncBillOccurrence): RedirectResponse
    {
        $validated = $this->validatedBillData($request);

        $bill = $request->user()->bills()->create($validated);

        $syncBillOccurrence->ensureInitial($bill);

        return back();
    }

    public function update(Request $request, Bill $bill, SyncBillOccurrence $syncBillOccurrence): RedirectResponse
    {
        abort_unless((int) $bill->user_id === (int) $request->user()->id, 404);

        $validated = $this->validatedBillData($request);

        $bill->update($validated);

        $syncBillOccurrence->syncPending($bill);

        return back();
    }

    public function destroy(Request $request, Bill $bill): RedirectResponse
    {
        abort_unless((int) $bill->user_id === (int) $request->user()->id, 404);

        $bill->delete();

        return back();
    }

    public function markPaid(Request $request, Bill $bill, BillOccurrence $occurrence, MarkBillOccurrencePaid $markBillOccurrencePaid): RedirectResponse
    {
        abort_unless((int) $bill->user_id === (int) $request->user()->id, 404);
        abort_unless((int) $occurrence->bill_id === (int) $bill->id, 404);

        $markBillOccurrencePaid($bill, $occurrence);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedBillData(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'max:10'],
            'category_id' => [
                'nullable',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    $exists = Category::query()
                        ->availableFor($request->user())
                        ->where('type', TransactionType::Cost)
                        ->whereKey($value)
                        ->exists();

                    if (! $exists) {
                        $fail(__('finance.bills.invalid_category'));
                    }
                },
            ],
            'recurrence_type' => ['required', 'string', 'in:one_time,monthly'],
            'due_day_of_month' => ['required_if:recurrence_type,monthly', 'nullable', 'integer', 'min:1', 'max:31'],
            'due_date' => ['required_if:recurrence_type,one_time', 'nullable', 'date'],
            'telegram_reminder_enabled' => ['boolean'],
        ]);

        $validated['category_id'] = $request->filled('category_id') ? (int) $validated['category_id'] : null;
        $validated['telegram_reminder_enabled'] = $request->boolean('telegram_reminder_enabled', true);

        if ($validated['recurrence_type'] === BillRecurrenceType::Monthly->value) {
            $validated['due_date'] = null;
        } else {
            $validated['due_day_of_month'] = null;
        }

        return $validated;
    }
}
