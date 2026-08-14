<?php

test('mobile navigation uses the sticky top header without a bottom nav', function () {
    $layout = file_get_contents(resource_path('js/layouts/app/AppSidebarLayout.vue'));
    $shell = file_get_contents(resource_path('js/components/AppShell.vue'));
    $header = file_get_contents(resource_path('js/components/AppSidebarHeader.vue'));
    $styles = file_get_contents(resource_path('css/app.css'));

    expect(resource_path('js/components/AppBottomNav.vue'))->not->toBeFile()
        ->and($layout)->not->toContain('AppBottomNav')
        ->and($layout)->toContain('class="app-page-scroll h-svh')
        ->and($layout)->toContain('overflow-y-auto pb-4')
        ->and($layout)->not->toContain('lg:pb-0')
        ->and($shell)->toContain('class="h-svh min-h-svh flex-col overflow-hidden lg:flex-row"')
        ->and($header)->toContain('class="sticky top-0')
        ->and($header)->toContain('lg:static')
        ->and($styles)->not->toContain('app-mobile-bottom-nav')
        ->and($styles)->not->toContain('--mobile-bottom-nav-height');
});
