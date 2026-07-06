<?php

beforeEach(function () {
    config(['app.url' => 'https://cashpilot.mohiebi.com']);
});

test('the landing page exposes fallback seo metadata', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<link rel="canonical" href="https://cashpilot.mohiebi.com/">', false)
        ->assertSee('<link rel="alternate" hreflang="fa" href="https://cashpilot.mohiebi.com/fa">', false)
        ->assertSee('<link rel="alternate" hreflang="x-default" href="https://cashpilot.mohiebi.com/">', false)
        ->assertSee('<meta property="og:url" content="https://cashpilot.mohiebi.com/">', false)
        ->assertSee('<meta name="twitter:image" content="https://cashpilot.mohiebi.com/apple-touch-icon.png">', false)
        ->assertSee('<script type="application/ld+json">', false)
        ->assertSee('"@type": "SoftwareApplication"', false)
        ->assertSee('"@type": "FAQPage"', false);
});

test('localized landing urls render localized props and canonical metadata', function () {
    $this->get(route('home.localized', ['locale' => 'fa']))
        ->assertOk()
        ->assertSee('<link rel="canonical" href="https://cashpilot.mohiebi.com/fa">', false)
        ->assertInertia(fn ($assert) => $assert
            ->component('Landing')
            ->where('locale', 'fa')
            ->where('dir', 'rtl')
            ->where('seo.canonical', 'https://cashpilot.mohiebi.com/fa'));
});

test('the sitemap lists localized landing urls with alternates', function () {
    $this->get(route('sitemap'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('<loc>https://cashpilot.mohiebi.com/</loc>', false)
        ->assertSee('<loc>https://cashpilot.mohiebi.com/fa</loc>', false)
        ->assertSee('<loc>https://cashpilot.mohiebi.com/de</loc>', false)
        ->assertSee('hreflang="x-default"', false);
});

test('robots txt advertises the sitemap', function () {
    expect(file_get_contents(public_path('robots.txt')))
        ->toContain('Sitemap: https://cashpilot.mohiebi.com/sitemap.xml');
});

test('google site verification can be rendered from configuration', function () {
    config(['services.google.site_verification' => 'verification-token']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<meta name="google-site-verification" content="verification-token">', false);
});

test('auth pages expose noindex fallback metadata', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex,follow">', false);
});
