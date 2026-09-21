<?php

namespace App\Mcp\Support;

use App\Actions\Bills\MarkBillOccurrencePaid;
use App\Actions\Bills\SaveBill;
use App\Actions\Categories\CreateCategory;
use App\Actions\Investments\SaveInvestment;
use App\Actions\Transactions\SaveTransaction;
use App\Enums\Feature;
use App\Exceptions\FeatureDisabledException;
use App\Models\InvestmentAsset;
use App\Models\McpProposal;
use App\Models\User;
use App\Support\FrontendLocalization;
use InvalidArgumentException;

/**
 * Applies a confirmed MCP proposal by dispatching its stored payload to the
 * same shared actions the web controllers use, so MCP-confirmed changes obey
 * identical domain rules and side effects.
 */
class ProposalApplier
{
    public function __construct(
        private readonly SaveTransaction $saveTransaction,
        private readonly SaveBill $saveBill,
        private readonly SaveInvestment $saveInvestment,
        private readonly MarkBillOccurrencePaid $markBillOccurrencePaid,
        private readonly CreateCategory $createCategory,
    ) {}

    /**
     * @return array<string, mixed> summary of what was applied
     */
    public function apply(McpProposal $proposal): array
    {
        $user = $proposal->user;
        $payload = $proposal->payload;

        $this->assertFeatureEnabled($user, $proposal);

        return match ("{$proposal->resource_type}.{$proposal->action}") {
            'transaction.create' => [
                'transaction_id' => $this->saveTransaction->handle($user, $payload)->id,
            ],
            'transaction.update' => [
                'transaction_id' => $this->saveTransaction->handle(
                    $user,
                    $payload,
                    $user->transactions()->findOrFail($proposal->resource_id),
                )->id,
            ],
            'transaction.delete' => $this->deleteTransaction($user, $proposal),
            'category.create' => [
                'category_id' => $this->createCategory->handle($user, $payload)->id,
            ],
            'bill.create' => [
                'bill_id' => $this->saveBill->create($user, $payload, $this->calendarFor($user))->id,
            ],
            'bill.update' => [
                'bill_id' => $this->saveBill->update(
                    $user->bills()->findOrFail($proposal->resource_id),
                    $payload,
                    $this->calendarFor($user),
                )->id,
            ],
            'bill_occurrence.pay' => $this->payBillOccurrence($user, $proposal),
            'investment.create' => [
                'investment_id' => $this->saveInvestment->create($user, $payload)->id,
            ],
            'investment.update' => [
                'investment_id' => $this->saveInvestment->update(
                    $user->investments()->findOrFail($proposal->resource_id),
                    $payload,
                )->id,
            ],
            'investment.delete' => $this->deleteInvestment($user, $proposal),
            'investment_asset.create' => [
                'investment_asset_id' => InvestmentAsset::query()->create([
                    'user_id' => $user->id,
                    ...$payload,
                ])->id,
            ],
            default => throw new InvalidArgumentException(
                "Unknown proposal action [{$proposal->resource_type}.{$proposal->action}].",
            ),
        };
    }

    /**
     * A proposal created while a module was on can still be confirmed after it is
     * switched off, because the confirm tool is deliberately ungated. This is the
     * chokepoint that stops it — and it covers any future non-MCP caller too.
     *
     * @throws FeatureDisabledException
     */
    private function assertFeatureEnabled(User $user, McpProposal $proposal): void
    {
        $required = match ($proposal->resource_type) {
            'bill', 'bill_occurrence' => Feature::Bills,
            'investment', 'investment_asset' => Feature::Investments,
            default => null,
        };

        if ($required !== null && ! $user->hasFeature($required)) {
            throw new FeatureDisabledException($required);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function deleteTransaction(User $user, McpProposal $proposal): array
    {
        $transaction = $user->transactions()->findOrFail($proposal->resource_id);
        $transaction->delete();

        return ['deleted_transaction_id' => $proposal->resource_id];
    }

    /**
     * @return array<string, mixed>
     */
    private function deleteInvestment(User $user, McpProposal $proposal): array
    {
        $investment = $user->investments()->findOrFail($proposal->resource_id);
        $investment->delete();

        return ['deleted_investment_id' => $proposal->resource_id];
    }

    /**
     * @return array<string, mixed>
     */
    private function payBillOccurrence(User $user, McpProposal $proposal): array
    {
        $bill = $user->bills()->findOrFail($proposal->resource_id);
        $occurrence = $bill->occurrences()->findOrFail($proposal->payload['occurrence_id']);

        $transaction = ($this->markBillOccurrencePaid)($bill, $occurrence);

        return [
            'bill_id' => $bill->id,
            'occurrence_id' => $occurrence->id,
            'transaction_id' => $transaction?->id,
        ];
    }

    private function calendarFor(User $user): string
    {
        return FrontendLocalization::normalizeCalendar($user->calendar);
    }
}
