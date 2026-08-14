<?php

use App\Support\Billing\TokenAmount;

test('hex quantities convert to decimal base units', function () {
    expect(TokenAmount::fromHex('0x0'))->toBe('0')
        ->and(TokenAmount::fromHex('0x1'))->toBe('1')
        ->and(TokenAmount::fromHex('0xff'))->toBe('255')
        // 5.004317 USDT at six decimals.
        ->and(TokenAmount::fromHex('0x4c5a7d'))->toBe('5003901')
        // Case and the 0x prefix are both optional on the way in.
        ->and(TokenAmount::fromHex('FF'))->toBe('255')
        ->and(TokenAmount::fromHex('0x0000ff'))->toBe('255');
});

test('hex conversion survives values no PHP integer can hold', function () {
    // 1 ether in wei — already 10^18, close to PHP_INT_MAX.
    expect(TokenAmount::fromHex('0xde0b6b3a7640000'))->toBe('1000000000000000000')
        // 10 ether. A cast to int overflows here and silently becomes a float.
        ->and(TokenAmount::fromHex('0x8ac7230489e80000'))->toBe('10000000000000000000')
        // A full uint256, which is 78 digits.
        ->and(TokenAmount::fromHex(str_repeat('f', 64)))
        ->toBe('115792089237316195423570985008687907853269984665640564039457584007913129639935');
});

test('decimal amounts convert to base units at the asset precision', function () {
    expect(TokenAmount::fromDecimal('5.004317', 6))->toBe('5004317')
        ->and(TokenAmount::fromDecimal('5', 6))->toBe('5000000')
        ->and(TokenAmount::fromDecimal('0.000001', 6))->toBe('1')
        ->and(TokenAmount::fromDecimal('0', 18))->toBe('0')
        ->and(TokenAmount::fromDecimal('1.5', 18))->toBe('1500000000000000000')
        ->and(TokenAmount::fromDecimal('12.34', 2))->toBe('1234');
});

test('a decimal column padded past the asset precision still converts', function () {
    // decimal(36,18) hands back eighteen places whatever the asset's precision,
    // so a six-decimal token arrives with twelve trailing zeros.
    expect(TokenAmount::fromDecimal('5.004317000000000000', 6))->toBe('5004317');
});

test('precision that would actually be lost is refused rather than rounded', function () {
    expect(fn () => TokenAmount::fromDecimal('5.0043171', 6))
        ->toThrow(InvalidArgumentException::class);
});

test('malformed amounts are rejected', function () {
    expect(fn () => TokenAmount::fromDecimal('-1', 6))->toThrow(InvalidArgumentException::class)
        ->and(fn () => TokenAmount::fromDecimal('1e18', 18))->toThrow(InvalidArgumentException::class)
        ->and(fn () => TokenAmount::fromDecimal('', 6))->toThrow(InvalidArgumentException::class)
        ->and(fn () => TokenAmount::fromHex('0xzz'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => TokenAmount::fromHex('0x'))->toThrow(InvalidArgumentException::class);
});

test('base units render back as decimals without rounding', function () {
    expect(TokenAmount::toDecimal('5004317', 6))->toBe('5.004317')
        ->and(TokenAmount::toDecimal('1', 6))->toBe('0.000001')
        ->and(TokenAmount::toDecimal('0', 6))->toBe('0.000000')
        ->and(TokenAmount::toDecimal('1000000000000000000', 18))->toBe('1.000000000000000000')
        ->and(TokenAmount::toDecimal('1234', 0))->toBe('1234');
});

test('a decimal survives a round trip through base units', function () {
    foreach ([['0.000001', 6], ['5.004317', 6], ['1234.56', 2], ['0.123456789012345678', 18]] as [$amount, $decimals]) {
        expect(TokenAmount::toDecimal(TokenAmount::fromDecimal($amount, $decimals), $decimals))
            ->toBe($amount);
    }
});

test('comparison stays exact across the integer overflow boundary', function () {
    // Both sides exceed PHP_INT_MAX, and differ only in the last digit — the
    // exact case a float comparison gets wrong.
    $a = '10000000000000000000';
    $b = '10000000000000000001';

    expect(TokenAmount::compare($a, $b))->toBe(-1)
        ->and(TokenAmount::compare($b, $a))->toBe(1)
        ->and(TokenAmount::compare($a, $a))->toBe(0)
        // Leading zeros must not make a number look longer, and so larger.
        ->and(TokenAmount::compare('0009', '10'))->toBe(-1)
        ->and(TokenAmount::compare('100', '99'))->toBe(1);
});

test('addition carries correctly past the integer overflow boundary', function () {
    expect(TokenAmount::add('9999999999999999999', '1'))->toBe('10000000000000000000')
        ->and(TokenAmount::add('0', '0'))->toBe('0')
        ->and(TokenAmount::add('999', '1'))->toBe('1000')
        ->and(TokenAmount::add(
            '115792089237316195423570985008687907853269984665640564039457584007913129639935',
            '1'
        ))->toBe('115792089237316195423570985008687907853269984665640564039457584007913129639936');
});

test('the tolerance band accepts the expected amount and a little over, but not the next intent', function () {
    // 5.004317 USDT expected, with a 0.01 band — the nonce space that keeps two
    // open intents from ever expecting the same figure.
    $floor = TokenAmount::fromDecimal('5.004317', 6);
    $width = TokenAmount::fromDecimal('0.01', 6);

    expect(TokenAmount::isWithinBand($floor, $floor, $width))->toBeTrue()
        ->and(TokenAmount::isWithinBand(TokenAmount::fromDecimal('5.009999', 6), $floor, $width))->toBeTrue()
        // A cent short: an exchange that deducted its fee. Refused here, so a
        // human decides rather than the entitlement being granted unpaid.
        ->and(TokenAmount::isWithinBand(TokenAmount::fromDecimal('5.004316', 6), $floor, $width))->toBeFalse()
        // Far enough over that it was meant for a different intent.
        ->and(TokenAmount::isWithinBand(TokenAmount::fromDecimal('5.014317', 6), $floor, $width))->toBeFalse();
});
