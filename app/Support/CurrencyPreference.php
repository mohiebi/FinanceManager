<?php

namespace App\Support;

use App\Enums\Currency;
use Illuminate\Http\Request;

class CurrencyPreference
{
    public static function resolve(Request $request): Currency
    {
        return Currency::tryFrom((string) $request->query('currency'))
            ?? Currency::tryFrom((string) $request->user()?->default_currency)
            ?? Currency::Toman;
    }
}
