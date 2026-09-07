<?php

namespace App\Enums;

enum MilesPack: string
{
    case Starter = 'starter';
    case Everyday = 'everyday';
    case Explorer = 'explorer';
    case Reserve = 'reserve';

    public function miles(): int
    {
        return (int) config("billing.miles_packs.{$this->value}.miles", 0);
    }

    public function priceUsd(): string
    {
        return (string) config("billing.miles_packs.{$this->value}.price_usd", '0.00');
    }

    public function label(): string
    {
        return number_format($this->miles()).' '.__('miles.unit');
    }

    /** @return list<self> */
    public static function available(): array
    {
        return array_values(array_filter(self::cases(), fn (self $pack): bool => $pack->miles() > 0 && (float) $pack->priceUsd() > 0));
    }
}
