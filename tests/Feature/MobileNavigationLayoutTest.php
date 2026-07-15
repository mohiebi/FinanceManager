<?php

test('mobile navigation occupies its own row below the scrollable page content', function () {
    $layout = file_get_contents(resource_path('js/layouts/app/AppSidebarLayout.vue'));
    $shell = file_get_contents(resource_path('js/components/AppShell.vue'));
    $navigation = file_get_contents(resource_path('js/components/AppBottomNav.vue'));
    $styles = file_get_contents(resource_path('css/app.css'));

    expect($layout)->toMatch('/<AppContent[\s\S]*<AppBottomNav \/>[\s\n]*<\/AppShell>/')
        ->and($shell)->toContain('class="flex-col lg:flex-row"')
        ->and($navigation)->toContain('app-mobile-bottom-nav')
        ->and($navigation)->not->toContain('fixed inset-x-0 bottom-0')
        ->and($styles)->toContain('--mobile-bottom-nav-height: 4rem;')
        ->and($styles)->toContain('env(safe-area-inset-bottom, 0px)');
});
