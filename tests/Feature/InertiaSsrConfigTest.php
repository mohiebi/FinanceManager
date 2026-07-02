<?php

test('inertia ssr is disabled by default', function () {
    expect(config('inertia.ssr.enabled'))->toBeFalse();
});
