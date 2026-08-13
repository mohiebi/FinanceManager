<?php

use App\Services\Advisor\InvestorProfileScorer;
use Tests\TestCase;

uses(TestCase::class);

function advisorScoreVectors(): array
{
    return json_decode(
        file_get_contents(dirname(__DIR__, 2).'/fixtures/advisor-score-vectors.json'),
        true,
    );
}

test('the PHP scorer matches every canonical vector', function () {
    $scorer = app(InvestorProfileScorer::class);

    foreach (advisorScoreVectors()['cases'] as $case) {
        $actual = $scorer->score($case['input']);

        expect($actual['scores'])->toBe($case['expected']['scores'], $case['name'])
            ->and($actual['risk_band'])->toBe($case['expected']['risk_band'], $case['name'])
            ->and($actual['persona'])->toBe($case['expected']['persona'], $case['name'])
            ->and($actual['maximum_tolerated_drawdown'])->toBe($case['expected']['maximum_tolerated_drawdown'], $case['name'])
            ->and($actual['warnings'])->toBe($case['expected']['warnings'], $case['name']);
    }
});

test('risk capacity caps a user with high willingness and weak finances', function () {
    $case = collect(advisorScoreVectors()['cases'])->firstWhere('name', 'high willingness low capacity');
    $scores = app(InvestorProfileScorer::class)->score($case['input'])['scores'];

    expect($scores['risk_willingness'])->toBeGreaterThan(90)
        ->and($scores['risk_capacity'])->toBeLessThan(40)
        ->and($scores['effective_risk'])->toBe(20)
        ->and($scores['effective_risk'])->toBeLessThan($scores['raw_risk']);
});

test('willingness also caps a high-capacity user who cannot tolerate losses', function () {
    $case = collect(advisorScoreVectors()['cases'])->firstWhere('name', 'high capacity low willingness');
    $scores = app(InvestorProfileScorer::class)->score($case['input'])['scores'];

    expect($scores['risk_capacity'])->toBeGreaterThan(80)
        ->and($scores['risk_willingness'])->toBeLessThan(30)
        ->and($scores['effective_risk'])->toBe(30);
});
