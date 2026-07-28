<?php

namespace App\Support\Encryption;

use App\Exceptions\DecryptionFailed;
use SensitiveParameter;

/**
 * The `cp1` field-encryption format, spoken identically by PHP and the browser.
 *
 *   blob = "cp1" || iv(12) || ciphertext || tag(16),  base64-encoded
 *   AAD  = "cp1:{table}:{column}"
 *
 * Laravel's own Crypt is deliberately not reused here: config/app.php selects
 * AES-256-CBC, which does not interoperate cleanly with the Web Crypto API. Both
 * sides must speak one format, so it is defined once, here.
 *
 * The AAD binds a ciphertext to the column it belongs to, so a `title` blob cannot
 * be moved into an `amount` column and still decrypt. It cannot bind to a row id
 * (unknown at insert time), so an attacker with database write access can still
 * swap ciphertexts between rows of the same column — an accepted residual risk.
 */
final class UserCrypto
{
    private const MAGIC = 'cp1';

    private const IV_BYTES = 12;

    private const TAG_BYTES = 16;

    private const CIPHER = 'aes-256-gcm';

    public static function encrypt(
        string $plaintext,
        #[SensitiveParameter] string $dek,
        string $aad,
    ): string {
        $iv = random_bytes(self::IV_BYTES);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $dek,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad,
            self::TAG_BYTES,
        );

        if ($ciphertext === false) {
            throw new DecryptionFailed('Encryption failed.');
        }

        // Tag trails the ciphertext, matching what Web Crypto's AES-GCM produces.
        return base64_encode(self::MAGIC.$iv.$ciphertext.$tag);
    }

    public static function decrypt(
        string $blob,
        #[SensitiveParameter] string $dek,
        string $aad,
    ): string {
        $raw = base64_decode($blob, true);

        if ($raw === false
            || ! str_starts_with($raw, self::MAGIC)
            || strlen($raw) < strlen(self::MAGIC) + self::IV_BYTES + self::TAG_BYTES) {
            throw new DecryptionFailed('Malformed ciphertext.');
        }

        $iv = substr($raw, strlen(self::MAGIC), self::IV_BYTES);
        $tag = substr($raw, -self::TAG_BYTES);
        $ciphertext = substr($raw, strlen(self::MAGIC) + self::IV_BYTES, -self::TAG_BYTES);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $dek,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad,
        );

        if ($plaintext === false) {
            throw new DecryptionFailed('Authentication failed.');
        }

        return $plaintext;
    }

    /**
     * Whether a stored value looks like a `cp1` blob.
     *
     * Used by the migrations to stay idempotent — a partially converted column
     * must not be double-encrypted on a re-run.
     */
    public static function looksEncrypted(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        $raw = base64_decode($value, true);

        return $raw !== false && str_starts_with($raw, self::MAGIC);
    }

    public static function aadFor(string $table, string $column): string
    {
        return self::MAGIC.':'.$table.':'.$column;
    }
}
