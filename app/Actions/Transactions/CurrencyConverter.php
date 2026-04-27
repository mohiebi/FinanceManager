<?php

namespace App\Actions\Transactions;

use App\Enums\Currency;
use App\Models\Transaction;

class CurrencyConverter
{
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
            Currency::Toman => $amount / 150000,
            Currency::Eur => $amount * 1.17,
        };
    }

    protected function convertFromUsd(float $amountInUsd, Currency $to): float
    {
        return match ($to) {
            Currency::Usd => $amountInUsd,
            Currency::Toman => $amountInUsd * 150000,
            Currency::Eur => $amountInUsd / 1.17,
        };
    }
}
