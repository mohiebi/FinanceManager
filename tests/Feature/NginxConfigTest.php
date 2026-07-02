<?php

test('docker nginx config allows large fastcgi response headers', function () {
    $config = file_get_contents(base_path('docker/nginx.conf'));

    expect($config)->toContain('fastcgi_buffer_size     128k')
        ->and($config)->toContain('fastcgi_buffers         4 256k')
        ->and($config)->toContain('fastcgi_busy_buffers_size 256k')
        ->and($config)->toContain('fastcgi_temp_file_write_size 256k');
});
