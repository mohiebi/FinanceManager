<?php

namespace App\Actions\Investments;

use App\Enums\Currency;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Investment;
use App\Models\InvestmentAsset;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Encryption\SealedField;

/**
 * Mirrors an investment into the cash ledger, when the user asks for it.
 *
 * The two books are deliberately separate: the portfolio tracks what you *hold*,
 * transactions track what moved through your pocket. Buying gold is both — an
 * asset arrives and money leaves — but only the user knows whether the money
 * actually came out of the account CashPilot is tracking, so this is always opt-in
 * and never inferred.
 *
 * Amounts are the *full* sum, never the profit. Someone who sells for 130 having
 * paid 100 received 130; recording 30 would describe a payment that never happened
 * and leave the balance card wrong.
 */
class RecordInvestmentTransaction
{
    /**
     * Fields the browser encrypted for the `transactions` table.
     *
     * Required when the vault is armed: a ciphertext is bound to its table by the
     * AAD, so the investment's own blobs cannot be reused, and the server holds no
     * key to seal fresh ones.
     *
     * @param  array{title: string, amount: string, description?: string|null}|null  $sealed
     */
    public function forPurchase(
        User $user,
        Investment $investment,
        InvestmentAsset $asset,
        ?array $sealed = null,
    ): ?Transaction {
        return $this->record(
            $user,
            $investment,
            TransactionType::Cost,
            $investment->cost_basis_currency,
            $sealed,
            fn (): ?float => $this->totalOf($investment->quantity, $investment->cost_basis),
            fn (): string => $this->title('bought_title', $investment, $asset),
        );
    }

    /**
     * @param  array{title: string, amount: string, description?: string|null}|null  $sealed
     */
    public function forSale(
        User $user,
        Investment $investment,
        InvestmentAsset $asset,
        ?array $sealed = null,
    ): ?Transaction {
        return $this->record(
            $user,
            $investment,
            TransactionType::Income,
            $investment->sale_price_currency,
            $sealed,
            fn (): ?float => $this->totalOf($investment->quantity, $investment->sale_price),
            fn (): string => $this->title('sold_title', $investment, $asset),
        );
    }

    /**
     * Build a mirrored transaction's title.
     *
     * Interpolated by hand because these strings are also rendered by the sell and
     * entry dialogs, and vue-i18n only understands `{param}` — so the `{param}`
     * form wins and this side fills it in rather than keeping two translations
     * that can drift apart.
     */
    private function title(string $key, Investment $investment, InvestmentAsset $asset): string
    {
        return strtr(__("finance.investments.{$key}"), [
            '{quantity}' => $this->formatQuantity($investment->quantity),
            '{unit}' => $asset->unit,
            '{asset}' => $asset->label(),
        ]);
    }

    /**
     * @param  array{title: string, amount: string, description?: string|null}|null  $sealed
     * @param  callable(): ?float  $amount
     * @param  callable(): string  $title
     */
    private function record(
        User $user,
        Investment $investment,
        TransactionType $type,
        ?string $currency,
        ?array $sealed,
        callable $amount,
        callable $title,
    ): ?Transaction {
        $category = $this->categoryFor($user, $type);

        $attributes = [
            'category_id' => $category?->id,
            'type' => $type,
            'currency' => $currency ?? Currency::Toman->value,
            // The same date the user put on the investment, not today: the money
            // moved when they say it moved.
            'occurred_at' => $investment->occurred_at->toDateString(),
        ];

        if ($sealed !== null) {
            return $user->transactions()->create([
                ...$attributes,
                ...SealedField::wrap($sealed, ['amount', 'title', 'description']),
            ]);
        }

        $total = $amount();

        // Nothing to record — a holding logged without a price has no cash side.
        if ($total === null || $total <= 0.0) {
            return null;
        }

        return $user->transactions()->create([
            ...$attributes,
            'amount' => $total,
            'title' => $title(),
            'description' => $investment->note,
        ]);
    }

    /**
     * The category a mirrored transaction lands in.
     *
     * Purchases go to the seeded `investment` cost category, which is exactly what
     * the report's "exclude investments" filter keys off — so money moved into
     * assets can be taken back out of a spending total in one click.
     *
     * Sales have no equivalent: naming an income category after the activity is the
     * ambiguity that got the old income `investment` category removed. They land
     * uncategorised and the user files them wherever they belong.
     */
    private function categoryFor(User $user, TransactionType $type): ?Category
    {
        if ($type !== TransactionType::Cost) {
            return null;
        }

        return Category::query()
            ->availableFor($user)
            ->where('type', TransactionType::Cost)
            ->where('slug', 'investment')
            ->first();
    }

    private function totalOf(mixed $quantity, mixed $perUnit): ?float
    {
        if ($perUnit === null || ! is_numeric((string) $perUnit)) {
            return null;
        }

        return abs((float) $quantity) * (float) $perUnit;
    }

    private function formatQuantity(mixed $quantity): string
    {
        $units = abs((float) $quantity);

        return rtrim(rtrim(number_format($units, 8, '.', ','), '0'), '.');
    }
}
