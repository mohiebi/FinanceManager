<?php

namespace App\Support\Encryption;

use Closure;

/**
 * Validation and payload handling for fields the browser sealed before sending.
 *
 * Every write path that survives an armed vault — transactions, investments,
 * bills, and the transaction a paid bill generates — needs the same two things:
 * rules that check the client actually encrypted, and a wrapper so the cast
 * stores the ciphertext verbatim instead of re-encrypting it with a key the
 * server no longer holds.
 */
final class SealedField
{
    /**
     * Rules for a field the server cannot read.
     *
     * `numeric` and `max:255` are gone — the server has no way to check a value it
     * cannot decrypt, which is the honest cost of the vault. What it *can* still
     * enforce is that the client sent real ciphertext rather than junk or, worse,
     * plaintext that would sit unencrypted in an encrypted column.
     *
     * @return array<int, mixed>
     */
    public static function rules(bool $required = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            'max:8192',
            function (string $attribute, mixed $value, Closure $fail): void {
                // `nullable` only short-circuits on null, so an empty optional
                // field would otherwise be told to encrypt nothing.
                if ($value === null || $value === '') {
                    return;
                }

                if (! UserCrypto::looksEncrypted(is_string($value) ? $value : null)) {
                    $fail('The :attribute must be encrypted by your browser before it is sent.');
                }
            },
        ];
    }

    /**
     * Wrap the named fields so the `UserEncrypted` cast passes them straight through.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $fields
     * @return array<string, mixed>
     */
    public static function wrap(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            if (filled($data[$field] ?? null)) {
                $data[$field] = new EncryptedValue($data[$field], $field);
            }
        }

        return $data;
    }
}
