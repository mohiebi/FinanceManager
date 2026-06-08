<?php

use App\Support\DateFormatter;
use Illuminate\Support\Carbon;

test('date formatter can render jalali dates while keeping gregorian default', function () {
    $date = Carbon::parse('2026-06-08');

    expect(DateFormatter::format($date))->toBe('2026-06-08')
        ->and(DateFormatter::format($date, 'jalali'))->toBe('1405-03-18');
});
