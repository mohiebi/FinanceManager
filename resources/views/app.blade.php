<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'fa' ? 'rtl' : 'ltr' }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    @php
        $component = $page['component'] ?? null;
        $locale = $page['props']['locale'] ?? app()->getLocale();
        $seo = \App\Support\SeoMetadata::forComponent($component, $locale);
        $shouldNoindex = \App\Support\SeoMetadata::shouldNoindexComponent($component);
    @endphp
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @if (config('services.google.site_verification'))
            <meta name="google-site-verification" content="{{ config('services.google.site_verification') }}">
        @endif

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <link rel="icon" href="{{ asset('favicon.svg') }}?v={{ filemtime(public_path('favicon.svg')) }}" type="image/svg+xml">
        <link rel="icon" href="{{ asset('favicon.ico') }}?v={{ filemtime(public_path('favicon.ico')) }}" sizes="any">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v={{ filemtime(public_path('apple-touch-icon.png')) }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
        <link href="https://fonts.bunny.net/css?family=poppins:200,300,400,500,600" rel="stylesheet" />
        <link href="https://fonts.bunny.net/css?family=vazirmatn:300,400,500,600,700" rel="stylesheet" />
        {{-- Advisor only: an editorial serif for headlines and a monospace for every figure. --}}
        <link href="https://fonts.bunny.net/css?family=instrument-serif:400,400i" rel="stylesheet" />
        <link href="https://fonts.bunny.net/css?family=jetbrains-mono:400,500" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            @if ($seo)
                <title>{{ $seo['title'] }}</title>
                <meta name="description" content="{{ $seo['description'] }}">
                <link rel="canonical" href="{{ $seo['canonical'] }}">
                @foreach ($seo['alternates'] as $alternate)
                    <link rel="alternate" hreflang="{{ $alternate['locale'] }}" href="{{ $alternate['url'] }}">
                @endforeach
                <link rel="alternate" hreflang="x-default" href="{{ $seo['xDefault'] }}">
                <meta property="og:type" content="website">
                <meta property="og:site_name" content="{{ $seo['siteName'] }}">
                <meta property="og:title" content="{{ $seo['title'] }}">
                <meta property="og:description" content="{{ $seo['description'] }}">
                <meta property="og:url" content="{{ $seo['canonical'] }}">
                <meta property="og:image" content="{{ $seo['image'] }}">
                <meta property="og:image:width" content="512">
                <meta property="og:image:height" content="512">
                <meta name="twitter:card" content="summary">
                <meta name="twitter:title" content="{{ $seo['title'] }}">
                <meta name="twitter:description" content="{{ $seo['description'] }}">
                <meta name="twitter:image" content="{{ $seo['image'] }}">
                <script type="application/ld+json">{!! $seo['structuredData'] !!}</script>
            @else
                @if ($shouldNoindex)
                    <meta name="robots" content="noindex,follow">
                @endif
                <title>{{ config('app.name', 'Laravel') }}</title>
            @endif
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
