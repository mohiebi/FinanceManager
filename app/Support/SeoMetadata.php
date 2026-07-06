<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Lang;

class SeoMetadata
{
    public static function forRequest(Request $request, string $locale): ?array
    {
        if ($request->routeIs('home', 'home.localized')) {
            return self::landing($locale);
        }

        return null;
    }

    public static function forComponent(?string $component, string $locale): ?array
    {
        if ($component === 'Landing') {
            return self::landing($locale);
        }

        return null;
    }

    public static function shouldNoindexComponent(?string $component): bool
    {
        return is_string($component) && str_starts_with($component, 'auth/');
    }

    /**
     * @return array{
     *     siteName: string,
     *     title: string,
     *     description: string,
     *     canonical: string,
     *     image: string,
     *     alternates: array<int, array{locale: string, url: string}>,
     *     xDefault: string,
     *     structuredData: string
     * }
     */
    public static function landing(string $locale): array
    {
        $locale = FrontendLocalization::normalizeLocale($locale);
        $title = Lang::get('landing.meta.title', [], $locale);
        $description = Lang::get('landing.meta.description', [], $locale);
        $siteName = config('app.name', 'CashPilot');
        $canonical = self::localizedLandingUrl($locale);

        return [
            'siteName' => $siteName,
            'title' => "{$siteName} - {$title}",
            'description' => $description,
            'canonical' => $canonical,
            'image' => self::absoluteUrl('/apple-touch-icon.png'),
            'alternates' => self::landingAlternates(),
            'xDefault' => self::localizedLandingUrl(FrontendLocalization::DEFAULT_LOCALE),
            'structuredData' => self::landingStructuredData($locale),
        ];
    }

    /**
     * @return array<int, array{locale: string, url: string}>
     */
    public static function landingAlternates(): array
    {
        return array_map(
            fn (string $locale): array => [
                'locale' => $locale,
                'url' => self::localizedLandingUrl($locale),
            ],
            FrontendLocalization::locales(),
        );
    }

    public static function localizedLandingUrl(string $locale): string
    {
        $locale = FrontendLocalization::normalizeLocale($locale);

        if ($locale === FrontendLocalization::DEFAULT_LOCALE) {
            return self::absoluteUrl('/');
        }

        return self::absoluteUrl("/{$locale}");
    }

    public static function sitemapXml(): string
    {
        $alternates = self::landingAlternates();
        $xDefault = self::localizedLandingUrl(FrontendLocalization::DEFAULT_LOCALE);
        $urls = array_map(fn (array $alternate): string => $alternate['url'], $alternates);
        $lastmod = Carbon::now()->toDateString();

        $entries = array_map(
            fn (string $url): string => self::sitemapUrlEntry($url, $alternates, $xDefault, $lastmod),
            $urls,
        );

        return implode("\n", [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">',
            ...$entries,
            '</urlset>',
            '',
        ]);
    }

    /**
     * @param  array<int, array{locale: string, url: string}>  $alternates
     */
    private static function sitemapUrlEntry(string $url, array $alternates, string $xDefault, string $lastmod): string
    {
        $alternateLinks = array_map(
            fn (array $alternate): string => sprintf(
                '        <xhtml:link rel="alternate" hreflang="%s" href="%s" />',
                self::xml($alternate['locale']),
                self::xml($alternate['url']),
            ),
            $alternates,
        );

        $alternateLinks[] = sprintf(
            '        <xhtml:link rel="alternate" hreflang="x-default" href="%s" />',
            self::xml($xDefault),
        );

        return implode("\n", [
            '    <url>',
            sprintf('        <loc>%s</loc>', self::xml($url)),
            sprintf('        <lastmod>%s</lastmod>', self::xml($lastmod)),
            '        <changefreq>monthly</changefreq>',
            '        <priority>1.0</priority>',
            ...$alternateLinks,
            '    </url>',
        ]);
    }

    private static function landingStructuredData(string $locale): string
    {
        $siteName = config('app.name', 'CashPilot');
        $url = self::localizedLandingUrl($locale);
        $description = Lang::get('landing.meta.description', [], $locale);
        $faqItems = Lang::get('landing.faq.items', [], $locale);

        $faqQuestions = [];

        if (is_array($faqItems)) {
            foreach ($faqItems as $item) {
                if (! is_array($item) || ! isset($item['q'], $item['a'])) {
                    continue;
                }

                $faqQuestions[] = [
                    '@type' => 'Question',
                    'name' => $item['q'],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $item['a'],
                    ],
                ];
            }
        }

        $graph = [
            [
                '@type' => 'Organization',
                '@id' => self::absoluteUrl('/#organization'),
                'name' => $siteName,
                'url' => self::absoluteUrl('/'),
                'logo' => self::absoluteUrl('/favicon.svg'),
            ],
            [
                '@type' => 'WebSite',
                '@id' => self::absoluteUrl('/#website'),
                'name' => $siteName,
                'url' => self::absoluteUrl('/'),
                'publisher' => [
                    '@id' => self::absoluteUrl('/#organization'),
                ],
                'inLanguage' => $locale,
            ],
            [
                '@type' => 'SoftwareApplication',
                '@id' => "{$url}#software",
                'name' => $siteName,
                'applicationCategory' => 'FinanceApplication',
                'operatingSystem' => 'Web',
                'url' => $url,
                'description' => $description,
                'offers' => [
                    '@type' => 'Offer',
                    'price' => '0',
                    'priceCurrency' => 'USD',
                ],
            ],
        ];

        if ($faqQuestions !== []) {
            $graph[] = [
                '@type' => 'FAQPage',
                '@id' => "{$url}#faq",
                'mainEntity' => $faqQuestions,
            ];
        }

        return json_encode([
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private static function absoluteUrl(string $path): string
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        $path = '/'.ltrim($path, '/');

        if ($path === '/') {
            return "{$baseUrl}/";
        }

        return "{$baseUrl}{$path}";
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
