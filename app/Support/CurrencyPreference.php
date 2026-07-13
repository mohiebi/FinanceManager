<?php

namespace App\Support;

use App\Enums\Currency;
use App\Models\User;
use Illuminate\Http\Request;

class CurrencyPreference
{
    public static function resolve(Request $request): Currency
    {
        $user = $request->user();

        return Currency::tryFrom((string) $request->query('currency'))
            ?? Currency::tryFrom(self::defaultCurrency($user))
            ?? Currency::Toman;
    }

    private static function defaultCurrency(?User $user): string
    {
        if (! $user || ! array_key_exists('default_currency', $user->getAttributes())) {
            return '';
        }

        return (string) $user->getAttribute('default_currency');
    }
}
