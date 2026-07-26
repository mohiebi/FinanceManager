<?php

use App\Exceptions\DecryptionFailed;
use App\Support\Encryption\UserCrypto;

/**
 * Read without base_path(): these are pure unit tests with no Laravel app booted,
 * which is exactly what keeps the crypto core framework-free.
 */
function cryptoVectors(): array
{
    return json_decode(
        file_get_contents(dirname(__DIR__).'/fixtures/crypto-vectors.json'),
        true,
    );
}

test('it round-trips a value', function () {
    $dek = random_bytes(32);
    $aad = UserCrypto::aadFor('transactions', 'title');

    $blob = UserCrypto::encrypt('Weekly groceries', $dek, $aad);

    expect(UserCrypto::decrypt($blob, $dek, $aad))->toBe('Weekly groceries');
});

test('the same plaintext encrypts to a different blob every time', function () {
    $dek = random_bytes(32);
    $aad = UserCrypto::aadFor('transactions', 'amount');

    $first = UserCrypto::encrypt('1250.00', $dek, $aad);
    $second = UserCrypto::encrypt('1250.00', $dek, $aad);

    // A fresh IV per write. This is exactly why SQL equality on an encrypted
    // column can never match, and why the import duplicate check had to change.
    expect($first)->not->toBe($second)
        ->and(UserCrypto::decrypt($first, $dek, $aad))->toBe('1250.00')
        ->and(UserCrypto::decrypt($second, $dek, $aad))->toBe('1250.00');
});

test('a ciphertext bound to one column cannot be decrypted as another', function () {
    $dek = random_bytes(32);

    $blob = UserCrypto::encrypt('Rent', $dek, UserCrypto::aadFor('transactions', 'title'));

    expect(fn () => UserCrypto::decrypt($blob, $dek, UserCrypto::aadFor('transactions', 'amount')))
        ->toThrow(DecryptionFailed::class);
});

test('a ciphertext cannot be decrypted with another key', function () {
    $aad = UserCrypto::aadFor('transactions', 'title');
    $blob = UserCrypto::encrypt('Rent', random_bytes(32), $aad);

    expect(fn () => UserCrypto::decrypt($blob, random_bytes(32), $aad))
        ->toThrow(DecryptionFailed::class);
});

test('tampering with the tag or the ciphertext is detected', function () {
    $dek = random_bytes(32);
    $aad = UserCrypto::aadFor('transactions', 'title');
    $raw = base64_decode(UserCrypto::encrypt('Rent', $dek, $aad), true);

    $flippedTag = $raw;
    $flippedTag[strlen($flippedTag) - 1] = chr(ord($flippedTag[strlen($flippedTag) - 1]) ^ 0x01);

    $flippedBody = $raw;
    $flippedBody[20] = chr(ord($flippedBody[20]) ^ 0x01);

    expect(fn () => UserCrypto::decrypt(base64_encode($flippedTag), $dek, $aad))
        ->toThrow(DecryptionFailed::class)
        ->and(fn () => UserCrypto::decrypt(base64_encode($flippedBody), $dek, $aad))
        ->toThrow(DecryptionFailed::class);
});

test('malformed input is rejected rather than misread', function () {
    $dek = random_bytes(32);
    $aad = UserCrypto::aadFor('transactions', 'title');

    expect(fn () => UserCrypto::decrypt('not base64 !!!', $dek, $aad))->toThrow(DecryptionFailed::class)
        ->and(fn () => UserCrypto::decrypt(base64_encode('xx9short'), $dek, $aad))->toThrow(DecryptionFailed::class)
        ->and(fn () => UserCrypto::decrypt(base64_encode('zzz'.str_repeat('a', 40)), $dek, $aad))->toThrow(DecryptionFailed::class);
});

test('it recognises its own blobs and passes over plaintext', function () {
    $blob = UserCrypto::encrypt('Rent', random_bytes(32), UserCrypto::aadFor('bills', 'title'));

    expect(UserCrypto::looksEncrypted($blob))->toBeTrue()
        ->and(UserCrypto::looksEncrypted('Rent'))->toBeFalse()
        ->and(UserCrypto::looksEncrypted(null))->toBeFalse()
        ->and(UserCrypto::looksEncrypted(''))->toBeFalse();
});

test('it decrypts the shared cross-language vectors', function () {
    $vectors = cryptoVectors();

    expect($vectors['encryption'])->not->toBeEmpty();

    foreach ($vectors['encryption'] as $vector) {
        $plaintext = UserCrypto::decrypt(
            $vector['blob'],
            hex2bin($vector['dek_hex']),
            $vector['aad'],
        );

        expect($plaintext)->toBe($vector['plaintext'], "vector: {$vector['name']}");
    }
});

test('it decrypts blobs produced by the browser implementation', function () {
    $vectors = json_decode(
        file_get_contents(dirname(__DIR__).'/fixtures/crypto-vectors-js.json'),
        true,
    );

    expect($vectors['encryption'])->not->toBeEmpty();

    // The reverse of the test above. Together they prove the cp1 format
    // interoperates in both directions, which is the whole point of pinning it.
    foreach ($vectors['encryption'] as $vector) {
        $plaintext = UserCrypto::decrypt(
            $vector['blob'],
            hex2bin($vector['dek_hex']),
            $vector['aad'],
        );

        expect($plaintext)->toBe($vector['plaintext'], "vector: {$vector['name']}");
    }
});

test('it agrees with the shared PBKDF2 and HKDF vectors', function () {
    $vectors = cryptoVectors();

    foreach ($vectors['pbkdf2_sha256'] as $vector) {
        $derived = hash_pbkdf2('sha256', $vector['passphrase'], hex2bin($vector['salt_hex']), $vector['iterations'], 32, true);

        expect(bin2hex($derived))->toBe($vector['derived_hex']);
    }

    foreach ($vectors['hkdf_sha256'] as $vector) {
        $derived = hash_hkdf('sha256', hex2bin($vector['ikm_hex']), 32, $vector['info'], hex2bin($vector['salt_hex']));

        expect(bin2hex($derived))->toBe($vector['derived_hex']);
    }
});

test('the aad is namespaced by table and column', function () {
    expect(UserCrypto::aadFor('transactions', 'amount'))->toBe('cp1:transactions:amount')
        ->and(UserCrypto::aadFor('bills', 'title'))->toBe('cp1:bills:title');
});
