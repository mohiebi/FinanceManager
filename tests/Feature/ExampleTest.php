<?php

test('returns a successful response', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
});

test('favicon links are versioned', function () {
    $response = $this->get(route('home'));

    $response
        ->assertOk()
        ->assertSee('favicon.svg?v=', false)
        ->assertSee('favicon.ico?v=', false)
        ->assertSee('apple-touch-icon.png?v=', false);
});
