<?php

namespace App\Enums;

enum Currency: string
{
    case Toman = 'toman';
    case Usd = 'usd';
    case Eur = 'eur';

    public function label(): string
    {
        return __('finance.currencies.'.$this->value);
    }
}
