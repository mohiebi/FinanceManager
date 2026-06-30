<?php

namespace App\Actions\Transactions;

use App\Enums\AssetType;
use App\Enums\Currency;
use App\Models\Transaction;
use App\Services\AssetPriceService;

class CurrencyConverter
{
    /**
     * Fallback rates used only when live prices are unavailable (e.g. tgju is down,
     * or price fetching is disabled), so conversions degrade gracefully instead of
     * breaking outright.
     */
    private const FALLBACK_TOMAN_PER_USD = 150000.0;

    private const FALLBACK_USD_PER_EUR = 1.17;

    public function __construct(private readonly AssetPriceService $priceService) {}

    public function convert(int|float|string $amount, Currency $from, Currency $to): float
    {
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
            Currency::Toman => $amount / $this->tomanPerUsd(),
            Currency::Eur => $amount * $this->usdPerEur(),
        };
    }

    protected function convertFromUsd(float $amountInUsd, Currency $to): float
    {
        return match ($to) {
            Currency::Usd => $amountInUsd,
            Currency::Toman => $amountInUsd * $this->tomanPerUsd(),
            Currency::Eur => $amountInUsd / $this->usdPerEur(),
        };
    }

    /**
     * Live Toman-per-USD rate, matching the price the Investments/Portfolio pages
     * already use, with a static fallback when prices are unavailable.
     */
    private function tomanPerUsd(): float
    {
        $live = $this->priceService->priceFor(AssetType::Usd);

        return $live > 0 ? $live : self::FALLBACK_TOMAN_PER_USD;
    }

    /**
     * USD-per-EUR cross rate derived from the live Toman quotes for both currencies,
     * with a static fallback when either live price is unavailable.
     */
    private function usdPerEur(): float
    {
        $tomanPerUsd = $this->priceService->priceFor(AssetType::Usd);
        $tomanPerEur = $this->priceService->priceFor(AssetType::Eur);

        if ($tomanPerUsd > 0 && $tomanPerEur > 0) {
            return $tomanPerEur / $tomanPerUsd;
        }

        return self::FALLBACK_USD_PER_EUR;
    }
}
