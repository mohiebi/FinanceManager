<?php

use App\Services\Advisor\AdvisorExecutionTimeLimiter;

test('the php execution allowance exceeds the configured provider timeout', function () {
    expect((new AdvisorExecutionTimeLimiter(60, 10))->requiredSeconds())->toBe(70);
});

test('the execution buffer cannot be configured below five seconds', function () {
    expect((new AdvisorExecutionTimeLimiter(30, 0))->requiredSeconds())->toBe(35);
});
