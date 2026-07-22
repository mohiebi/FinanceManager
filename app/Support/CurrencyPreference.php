<?php

namespace App\Support;

use App\Enums\Currency;
use App\Models\User;
use Illuminate\Http\Request;

class CurrencyPreference
{
    public static function resolve(Request $request): Currency
    {
        return self::resolveFor($request->user(), (string) $request->query('currency'));
    }

    /**
     * Resolves a currency preference without an HTTP request, e.g. for MCP
     * tools where the requested currency arrives as a tool argument.
     */
    public static function resolveFor(?User $user, ?string $currency = null): Currency
    {
        return Currency::tryFrom((string) $currency)
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
