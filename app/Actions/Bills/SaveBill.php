<?php

namespace App\Actions\Bills;

use App\Enums\BillRecurrenceType;
use App\Enums\TransactionType;
use App\Models\Bill;
use App\Models\Category;
use App\Models\User;
use App\Support\Encryption\SealedField;

/**
 * Shared bill validation and persistence used by the web BillController and
 * the MCP tools so both surfaces enforce identical rules and side effects
 * (occurrence synchronization).
 */
class SaveBill
{
    public function __construct(private readonly SyncBillOccurrence $syncBillOccurrence) {}

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
                function (string $attribute, mixed $value, \Closure $fail) use ($user): void {
                    $exists = Category::query()
                        ->availableFor($user)
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
        } else {
            $validated['due_day_of_month'] = null;
        }

        // Wrapped so the cast stores the browser's ciphertext verbatim instead of
        // trying to encrypt it again with a key the server no longer has.
        return $vaultArmed
            ? SealedField::wrap($validated, ['title', 'amount'])
            : $validated;
    }

    /**
     * @param  array<string, mixed>  $data  normalized bill attributes
     */
    public function create(User $user, array $data, ?string $calendar = null): Bill
    {
        $bill = $user->bills()->create($data);

        $this->syncBillOccurrence->ensureInitial($bill, $calendar);

        return $bill;
    }

    /**
     * @param  array<string, mixed>  $data  normalized bill attributes
     */
    public function update(Bill $bill, array $data, ?string $calendar = null): Bill
    {
        $bill->update($data);

        $this->syncBillOccurrence->syncPending($bill, $calendar);

        return $bill;
    }
}
