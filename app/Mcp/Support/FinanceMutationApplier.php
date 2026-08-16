<?php

namespace App\Mcp\Support;

use App\Actions\Bills\MarkBillOccurrencePaid;
use App\Actions\Bills\SaveBill;
use App\Actions\Investments\SaveInvestment;
use App\Actions\Transactions\SaveTransaction;
use App\Enums\Feature;
use App\Enums\InvestmentAssetPriceSource;
use App\Enums\TransactionType;
use App\Exceptions\FeatureDisabledException;
use App\Models\Bill;
use App\Models\Category;
use App\Models\Investment;
use App\Models\InvestmentAsset;
use App\Models\Transaction;
use App\Models\User;
use App\Support\CalendarDates;
use App\Support\FrontendLocalization;
use App\Support\TransactionRules;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class FinanceMutationApplier
{
    public function __construct(
        private readonly SaveTransaction $saveTransaction,
        private readonly SaveBill $saveBill,
        private readonly SaveInvestment $saveInvestment,
        private readonly MarkBillOccurrencePaid $markBillOccurrencePaid,
    ) {}

    /**
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    public function apply(User $user, array $operation): array
    {
        $resource = (string) ($operation['resource'] ?? '');
        $action = (string) ($operation['action'] ?? '');

        $this->assertFeatureEnabled($user, $resource);

        return match ($resource) {
            'transaction' => $this->applyTransaction($user, $action, $operation),
            'category' => $this->applyCategory($user, $action, $operation),
            'bill' => $this->applyBill($user, $action, $operation),
            'bill_occurrence' => $this->applyBillOccurrence($user, $action, $operation),
            'investment' => $this->applyInvestment($user, $action, $operation),
            'investment_asset' => $this->applyInvestmentAsset($user, $action, $operation),
            default => throw new InvalidArgumentException(
                'resource must be one of: transaction, category, bill, bill_occurrence, investment, investment_asset.',
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private function applyTransaction(User $user, string $action, array $operation): array
    {
        $this->ensureAction($action, ['create', 'update', 'delete'], 'transaction');

        $transaction = null;

        if ($action !== 'create') {
            $transaction = $user->transactions()->find($this->requiredId($operation, 'transaction'));

            if (! $transaction instanceof Transaction) {
                throw new InvalidArgumentException('Transaction not found. Use list-transactions to find valid ids.');
            }
        }

        if ($action === 'delete') {
            $id = $transaction->id;
            $transaction->delete();

            return ['deleted_transaction_id' => $id];
        }

        $operation['occurred_at'] = CalendarDates::normalizeToGregorian($operation['occurred_at'] ?? null);
        $validated = $this->validate($operation, TransactionRules::rules());

        if ($errors = TransactionRules::categoryErrors($user, $validated['category_id'], $validated['type'])) {
            throw ValidationException::withMessages($errors);
        }

        $saved = $this->saveTransaction->handle(
            $user,
            [...$validated, 'description' => $validated['description'] ?? null],
            $transaction,
        );

        return ['transaction_id' => $saved->id];
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private function applyCategory(User $user, string $action, array $operation): array
    {
        $this->ensureAction($action, ['create'], 'category');

        $validated = $this->validate($operation, [
            'type' => ['required', Rule::enum(TransactionType::class)],
            'name' => ['required', 'string', 'max:100'],
        ]);

        $name = trim($validated['name']);

        $exists = Category::query()
            ->availableFor($user)
            ->where('type', $validated['type'])
            ->where('slug', Category::slugForName($name))
            ->exists();

        if ($exists) {
            throw new InvalidArgumentException('A category with this name already exists for this type.');
        }

        $category = Category::query()->create([
            'user_id' => $user->id,
            'type' => $validated['type'],
            'name' => $name,
        ]);

        return ['category_id' => $category->id];
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private function applyBill(User $user, string $action, array $operation): array
    {
        $this->ensureAction($action, ['create', 'update'], 'bill');

        $bill = null;

        if ($action === 'update') {
            $bill = $user->bills()->find($this->requiredId($operation, 'bill'));

            if (! $bill instanceof Bill) {
                throw new InvalidArgumentException('Bill not found. Use list-bills to find valid ids.');
            }
        }

        $operation['due_date'] = CalendarDates::normalizeToGregorian($operation['due_date'] ?? null);
        $validated = $this->validate($operation, SaveBill::rules($user));
        $payload = SaveBill::normalize(
            $validated,
            $user,
            array_key_exists('category_id', $operation),
            array_key_exists('telegram_reminder_enabled', $operation)
                ? (bool) $operation['telegram_reminder_enabled']
                : null,
        );

        $calendar = FrontendLocalization::normalizeCalendar($user->calendar);
        $saved = $bill instanceof Bill
            ? $this->saveBill->update($bill, $payload, $calendar)
            : $this->saveBill->create($user, $payload, $calendar);

        return ['bill_id' => $saved->id];
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private function applyBillOccurrence(User $user, string $action, array $operation): array
    {
        $this->ensureAction($action, ['pay'], 'bill_occurrence');

        $bill = $user->bills()->find($this->requiredId($operation, 'bill'));

        if (! $bill instanceof Bill) {
            throw new InvalidArgumentException('Bill not found. Use list-bills to find valid ids.');
        }

        $occurrenceId = $operation['occurrence_id'] ?? null;
        $occurrence = filled($occurrenceId)
            ? $bill->occurrences()->whereKey((int) $occurrenceId)->first()
            : $bill->occurrences()->whereNull('paid_at')->orderBy('due_date')->first();

        if (! $occurrence) {
            throw new InvalidArgumentException('No matching occurrence found for this bill.');
        }

        if ($occurrence->isPaid()) {
            throw new InvalidArgumentException('This occurrence is already paid.');
        }

        $transaction = ($this->markBillOccurrencePaid)($bill, $occurrence);

        return [
            'bill_id' => $bill->id,
            'occurrence_id' => $occurrence->id,
            'transaction_id' => $transaction?->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private function applyInvestment(User $user, string $action, array $operation): array
    {
        $this->ensureAction($action, ['create', 'update', 'delete'], 'investment');

        $investment = null;

        if ($action !== 'create') {
            $investment = $user->investments()->find($this->requiredId($operation, 'investment'));

            if (! $investment instanceof Investment) {
                throw new InvalidArgumentException('Investment entry not found. Use list-investments to find valid ids.');
            }
        }

        if ($action === 'delete') {
            $id = $investment->id;
            $investment->delete();

            return ['deleted_investment_id' => $id];
        }

        // Same reason the web route refuses one: this speaks the buy vocabulary,
        // and a disposal is stored with a negative quantity. Rewriting it here
        // turned a sale into a purchase of the same size — a holding that moved
        // by twice the sale — while `kind` still read `sell`. There is no sell
        // action on this surface, so nothing here can express the edit properly.
        if ($investment instanceof Investment && $investment->isSell()) {
            throw new InvalidArgumentException('A sale cannot be edited. Delete it and record the sale again.');
        }

        $operation['occurred_at'] = CalendarDates::normalizeToGregorian($operation['occurred_at'] ?? null);
        $validated = $this->validate($operation, SaveInvestment::rules());
        $payload = SaveInvestment::normalize($user, $validated);
        $saved = $investment instanceof Investment
            ? $this->saveInvestment->update($investment, $payload)
            : $this->saveInvestment->create($user, $payload);

        return ['investment_id' => $saved->id];
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private function applyInvestmentAsset(User $user, string $action, array $operation): array
    {
        $this->ensureAction($action, ['create'], 'investment_asset');

        // URL sources remain excluded so an AI client cannot point background
        // price fetching at an arbitrary host.
        $validated = $this->validate($operation, [
            'name' => ['required', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:20'],
            'price_source_type' => ['required', Rule::in([
                InvestmentAssetPriceSource::Manual->value,
                InvestmentAssetPriceSource::Formula->value,
            ])],
            'price' => ['required_if:price_source_type,manual', 'nullable', 'numeric', 'min:0'],
            'formula' => ['required_if:price_source_type,formula', 'nullable', 'string', 'max:500'],
        ]);

        $name = trim($validated['name']);
        $slug = InvestmentAsset::slugForName($name);

        if (InvestmentAsset::query()->availableFor($user)->where('slug', $slug)->exists()) {
            throw new InvalidArgumentException('An asset with this name already exists.');
        }

        $sourceType = $validated['price_source_type'];
        $asset = InvestmentAsset::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'slug' => $slug,
            'unit' => trim($validated['unit']),
            'color' => '#02CD86',
            'price_source_type' => $sourceType,
            'price_source_config' => $sourceType === InvestmentAssetPriceSource::Manual->value
                ? ['price' => (float) $validated['price']]
                : ['formula' => trim((string) $validated['formula'])],
        ]);

        return ['investment_asset_id' => $asset->id];
    }

    private function assertFeatureEnabled(User $user, string $resource): void
    {
        $required = match ($resource) {
            'bill', 'bill_occurrence' => Feature::Bills,
            'investment', 'investment_asset' => Feature::Investments,
            default => null,
        };

        if ($required !== null && ! $user->hasFeature($required)) {
            throw new FeatureDisabledException($required);
        }
    }

    /**
     * @param  array<int, string>  $allowed
     */
    private function ensureAction(string $action, array $allowed, string $resource): void
    {
        if (! in_array($action, $allowed, true)) {
            throw new InvalidArgumentException(
                "{$resource} action must be one of: ".implode(', ', $allowed).'.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    private function requiredId(array $operation, string $resource): int
    {
        $id = filter_var($operation['id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($id === false) {
            throw new InvalidArgumentException("A valid id is required for this {$resource} action.");
        }

        return $id;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function validate(array $data, array $rules): array
    {
        return Validator::make($data, $rules)->validate();
    }
}
