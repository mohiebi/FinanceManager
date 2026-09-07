<?php

use App\Support\Numerals;

test('a number typed on a Persian keyboard reads as a number', function (string $typed, ?float $expected) {
    expect(Numerals::parse($typed))->toBe($expected);
})->with([
    'persian digits' => ["\u{06F1}\u{06F2}\u{06F3}", 123.0],
    'arabic-indic digits' => ["\u{0661}\u{0662}\u{0663}", 123.0],
    'persian grouping separator' => ["\u{06F5}\u{066C}\u{06F0}\u{06F0}\u{06F0}", 5000.0],
    'persian decimal separator' => ["\u{06F1}\u{066B}\u{06F5}", 1.5],
    'mixed digit sets' => ["1\u{06F2}3", 123.0],
    'ascii grouping the app itself prints' => ['2,100,000', 2100000.0],
    'a plain ascii number still works' => ['5000000', 5000000.0],
    'a decimal still works' => ['12.75', 12.75],
    'a negative number' => ["-\u{06F4}\u{06F2}", -42.0],
    'surrounding whitespace' => ['  750  ', 750.0],

    // Refused rather than salvaged: reading "12kg" as 12 would silently record
    // an amount the user did not type.
    'letters mixed in' => ['12kg', null],
    'a bare separator' => ['.', null],
    'empty' => ['', null],
    'words' => ["\u{062F}\u{0648}", null],
]);

test('digit conversion leaves the rest of the text alone', function () {
    // normalizeText in the CSV importer runs on category names, so this must
    // not quietly strip separators the way parse() does.
    expect(Numerals::toLatin("\u{0642}\u{0628}\u{0636} \u{06F1}\u{06F4}\u{06F0}\u{06F5}"))
        ->toBe("\u{0642}\u{0628}\u{0636} 1405");
});
