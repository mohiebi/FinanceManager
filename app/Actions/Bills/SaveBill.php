<?php

namespace App\Actions\Bills;

use App\Enums\BillRecurrenceLimitType;
use App\Enums\BillRecurrenceType;
use App\Enums\TransactionType;
use App\Models\Bill;
use App\Models\Category;
use App\Models\User;
use App\Support\BillRecurrenceSchedule;
use App\Support\Encryption\SealedField;
use App\Support\FrontendLocalization;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Shared bill validation and persistence used by the web BillController and
 * the MCP tools so both surfaces enforce identical rules and side effects
 * (occurrence synchronization).
 */
class SaveBill
{
    public function __construct(
        private readonly SyncBillOccurrence $syncBillOccurrence,
        private readonly BillRecurrenceSchedule $recurrenceSchedule,
    ) {}

    /**
     * @param  bool  $vaultArmed  when true, title/amount arrive already encrypted by
     *                            the browser and the server can no longer inspect them
     * @return array<string, mixed>
     */
    public static function rules(User $user, bool $vaultArmed = false): array
    {
        return [
            'title' => $vaultArmed
                ? SealedField::rules()
                : ['required', 'string', 'max:100'],
            'amount' => $vaultArmed
                ? SealedField::rules()
                : ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'max:10'],
            'category_id' => [
                'nullable',
                'integer',
                // Delegates to the same check the write path runs, so validation
                // and persistence can never disagree about what is allowed.
                function (string $attribute, mixed $value, \Closure $fail) use ($user): void {
                    try {
                        self::assertCategoryIsUsable($user, $value);
                    } catch (ValidationException) {
                        $fail(__('finance.bills.invalid_category'));
                    }
                },
            ],
            'recurrence_type' => ['required', 'string', 'in:one_time,monthly'],
            'due_day_of_month' => ['required_if:recurrence_type,monthly', 'nullable', 'integer', 'min:1', 'max:31'],
            'due_date' => ['required_if:recurrence_type,one_time', 'nullable', 'date'],
            'recurrence_limit_type' => ['nullable', 'string', 'in:infinite,count,date'],
            'recurrence_count' => [
                'required_if:recurrence_limit_type,count',
                'nullable',
                'integer',
                'min:1',
                'max:'.BillRecurrenceSchedule::MAX_OCCURRENCES,
            ],
            'recurrence_end_date' => ['required_if:recurrence_limit_type,date', 'nullable', 'date'],
            'telegram_reminder_enabled' => ['boolean'],
            'reminder_time' => ['nullable', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'reminder_timezone' => ['nullable', 'string', 'max:50', 'timezone:all'],
        ];
    }

    /**
     * Normalizes validated data the same way the web controller always has:
     * blank category becomes null, reminders default to enabled, a blank
     * reminder timezone falls back to the user's account timezone (Settings
     * > Preferences) rather than UTC, and the irrelevant due field for the
     * recurrence type is cleared.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public static function normalize(array $validated, User $user, bool $categoryProvided, ?bool $reminderEnabled, bool $vaultArmed = false): array
    {
        $validated['category_id'] = $categoryProvided && $validated['category_id'] !== null
            ? (int) $validated['category_id']
            : null;
        $validated['telegram_reminder_enabled'] = $reminderEnabled ?? true;

        if (blank($validated['reminder_timezone'] ?? null)) {
            $validated['reminder_timezone'] = $user->resolvedTimezone();
        }

        if ($validated['recurrence_type'] === BillRecurrenceType::Monthly->value) {
            $validated['due_date'] = null;
            $limitType = BillRecurrenceLimitType::tryFrom((string) ($validated['recurrence_limit_type'] ?? ''));
            $validated['recurrence_limit_type'] = in_array($limitType, [
                BillRecurrenceLimitType::Count,
                BillRecurrenceLimitType::Date,
            ], true) ? $limitType->value : null;

            if ($limitType !== BillRecurrenceLimitType::Count) {
                $validated['recurrence_count'] = null;
            } else {
                $validated['recurrence_count'] = (int) $validated['recurrence_count'];
            }

            if ($limitType !== BillRecurrenceLimitType::Date) {
                $validated['recurrence_end_date'] = null;
            }
        } else {
            $validated['due_day_of_month'] = null;
            $validated['recurrence_limit_type'] = null;
            $validated['recurrence_count'] = null;
            $validated['recurrence_end_date'] = null;
        }

        // Wrapped so the cast stores the browser's ciphertext verbatim instead of
        // trying to encrypt it again with a key the server no longer has.
        return $vaultArmed
            ? SealedField::wrap($validated, ['title', 'amount'])
            : $validated;
    }

    /**
     * @param  array<string, mixed>  $data  normalized bill attributes
     *
     * @throws ValidationException when the category is not the user's to use
     */
    public function create(User $user, array $data, ?string $calendar = null): Bill
    {
        self::assertCategoryIsUsable($user, $data['category_id'] ?? null);
        $data = $this->prepareRecurrenceLimit($user, $data, null, $calendar);

        $bill = $user->bills()->create($data);

        $this->syncBillOccurrence->ensureInitial($bill, $calendar);

        return $bill;
    }

    /**
     * @param  array<string, mixed>  $data  normalized bill attributes
     *
     * @throws ValidationException when the category is not the user's to use
     */
    public function update(Bill $bill, array $data, ?string $calendar = null): Bill
    {
        self::assertCategoryIsUsable($bill->user, $data['category_id'] ?? null);
        $data = $this->prepareRecurrenceLimit($bill->user, $data, $bill, $calendar);

        $bill->update($data);

        $this->syncBillOccurrence->syncPending($bill, $calendar);
        $this->syncBillOccurrence->trimToRecurrenceLimit($bill);

        return $bill;
    }

    /**
     * The category rule from {@see self::rules()}, enforced where writes happen.
     *
     * Callers that validate still fail earlier and with a better message; this is
     * the floor beneath them, for the paths that do not — a confirmed MCP
     * proposal replays a payload stored earlier and re-validates none of it.
     *
     * @throws ValidationException
     */
    public static function assertCategoryIsUsable(User $user, mixed $categoryId): void
    {
        if ($categoryId === null || $categoryId === '') {
            return;
        }

        $usable = Category::query()
            ->availableFor($user)
            ->forType(TransactionType::Cost)
            ->whereKey($categoryId)
            ->exists();

        if (! $usable) {
            throw ValidationException::withMessages([
                'category_id' => __('finance.bills.invalid_category'),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepareRecurrenceLimit(User $user, array $data, ?Bill $bill, ?string $calendar): array
    {
        if (($data['recurrence_type'] ?? null) !== BillRecurrenceType::Monthly->value) {
            return $data;
        }

        $calendar ??= FrontendLocalization::normalizeCalendar($user->calendar);
        $limitType = BillRecurrenceLimitType::tryFrom((string) ($data['recurrence_limit_type'] ?? ''));
        $paidCount = $bill?->occurrences()->whereNotNull('paid_at')->count() ?? 0;

        if ($limitType === BillRecurrenceLimitType::Count) {
            $paymentCount = (int) $data['recurrence_count'];

            if ($paymentCount < $paidCount) {
                throw ValidationException::withMessages([
                    'recurrence_count' => __('finance.bills.limit_before_paid', ['count' => $paidCount]),
                ]);
            }

            $data['is_active'] = $paymentCount > $paidCount;

            return $data;
        }

        if ($limitType === BillRecurrenceLimitType::Date) {
            $futureCount = $this->recurrenceSchedule->countThrough(
                (int) $data['due_day_of_month'],
                $calendar,
                $this->nextScheduleSearchDate($bill),
                Carbon::parse($data['recurrence_end_date']),
            );

            if ($futureCount === 0) {
                throw ValidationException::withMessages([
                    'recurrence_end_date' => __('finance.bills.end_before_next'),
                ]);
            }

            $paymentCount = $paidCount + $futureCount;

            if ($paymentCount > BillRecurrenceSchedule::MAX_OCCURRENCES) {
                throw ValidationException::withMessages([
                    'recurrence_end_date' => __('finance.bills.limit_too_large', [
                        'count' => BillRecurrenceSchedule::MAX_OCCURRENCES,
                    ]),
                ]);
            }

            $data['recurrence_count'] = $paymentCount;
            $data['is_active'] = true;

            return $data;
        }

        // Existing rows and clients that omit the new field remain infinite.
        $data['recurrence_limit_type'] = null;
        $data['recurrence_count'] = null;
        $data['recurrence_end_date'] = null;
        $data['is_active'] = true;

        return $data;
    }

    /**
     * Model date casts come back as CarbonImmutable (Date::use in
     * AppServiceProvider), while the schedule and the Jalali calculator below it
     * need the mutable Carbon. Carbon::instance() is the boundary between them,
     * and it already copies, so no ->copy() is needed on top.
     */
    private function nextScheduleSearchDate(?Bill $bill): Carbon
    {
        if (! $bill) {
            return Carbon::today();
        }

        $nextUnpaid = $bill->occurrences()
            ->whereNull('paid_at')
            ->orderBy('due_date')
            ->first();

        if ($nextUnpaid) {
            return Carbon::instance($nextUnpaid->due_date)->startOfDay();
        }

        $latestPaid = $bill->occurrences()
            ->whereNotNull('paid_at')
            ->orderByDesc('due_date')
            ->first();

        return $latestPaid
            ? Carbon::instance($latestPaid->due_date)->addDay()->startOfDay()
            : Carbon::today();
    }
}
