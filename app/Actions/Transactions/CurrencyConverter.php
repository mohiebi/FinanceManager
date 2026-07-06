<?php

namespace App\Actions\Transactions;

use App\Enums\AssetType;
use App\Enums\Currency;
use App\Models\Transaction;
use App\Services\AssetPriceService;

class CurrencyConverter
{
    public function __construct(private readonly AssetPriceService $priceService) {}

    public function convert(int|float|string $amount, Currency $from, Currency $to): float
    {
        if ($from === $to) {
            return round((float) $amount, 2);
        }

        $amountInUsd = $this->convertToUsd((float) $amount, $from);

        return round($this->convertFromUsd($amountInUsd, $to), 2);
    }

    public function format(int|float|string $amount, Currency $from, Currency $to): string
    {
        return number_format($this->convert($amount, $from, $to), 2, '.', '');
    }

    /**
     * @param  iterable<Transaction>  $transactions
     */
    public function sumFormatted(iterable $transactions, Currency $target): string
    {
        $total = 0.0;

        foreach ($transactions as $transaction) {
            $total += $this->convert($transaction->amount, $transaction->currency, $target);
        }

        return number_format(round($total, 2), 2, '.', '');
    }

    protected function convertToUsd(float $amount, Currency $from): float
    {
        return match ($from) {
            Currency::Usd => $amount,
            Currency::Toman => $this->tomanPerUsd() > 0 ? $amount / $this->tomanPerUsd() : 0.0,
            Currency::Eur => $amount * $this->usdPerEur(),
        };
    }

    protected function convertFromUsd(float $amountInUsd, Currency $to): float
    {
        return match ($to) {
            Currency::Usd => $amountInUsd,
            Currency::Toman => $amountInUsd * $this->tomanPerUsd(),
            Currency::Eur => $this->usdPerEur() > 0 ? $amountInUsd / $this->usdPerEur() : 0.0,
        };
    }

    /**
     * Live Toman-per-USD rate, matching the price the Investments/Portfolio pages
     * already use. Returns 0.0 if live prices are unavailable.
     */
    private function tomanPerUsd(): float
    {
        return $this->priceService->priceFor(AssetType::Usd);
    }

    /**
     * USD-per-EUR cross rate derived from the live Toman quotes for both currencies.
     * Returns 0.0 if either live price is unavailable.
     */
    private function usdPerEur(): float
    {
        $tomanPerUsd = $this->priceService->priceFor(AssetType::Usd);
        $tomanPerEur = $this->priceService->priceFor(AssetType::Eur);

        return $tomanPerUsd > 0 ? $tomanPerEur / $tomanPerUsd : 0.0;
    }
}
