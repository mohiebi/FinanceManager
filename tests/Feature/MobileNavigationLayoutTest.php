<?php

test('mobile pages reserve safe scroll space above the fixed navigation', function () {
    $layout = file_get_contents(resource_path('js/layouts/app/AppSidebarLayout.vue'));
    $navigation = file_get_contents(resource_path('js/components/AppBottomNav.vue'));
    $styles = file_get_contents(resource_path('css/app.css'));
    $normalizedStyles = preg_replace('/\s+/', ' ', $styles);

    expect($layout)->toContain('app-mobile-scroll-content')
        ->and($navigation)->toContain('app-mobile-bottom-nav')
        ->and($styles)->toContain('--mobile-bottom-nav-height: 4rem;')
        ->and($normalizedStyles)->toContain('env(safe-area-inset-bottom, 0px) + 1rem')
        ->and($styles)->toContain('scroll-padding-bottom: calc(');
});
