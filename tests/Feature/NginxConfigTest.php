<?php

test('nginx configs allow large fastcgi response headers', function (string $path) {
    $config = file_get_contents(base_path($path));

    expect($config)->toContain('fastcgi_buffer_size')
        ->and($config)->toContain('512k')
        ->and($config)->toContain('fastcgi_buffers')
        ->and($config)->toContain('16 512k')
        ->and($config)->toContain('fastcgi_busy_buffers_size')
        ->and($config)->toContain('1024k')
        ->and($config)->toContain('fastcgi_temp_file_write_size');
})->with([
    'docker nginx config' => 'docker/nginx.conf',
    'startup nginx config' => 'default',
]);
