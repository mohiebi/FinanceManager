<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'fa' ? 'rtl' : 'ltr' }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('settings.ai.authorize.title', ['client' => $client->name]) }} - {{ config('app.name', 'CashPilot') }}</title>

    <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    <link rel="shortcut icon" href="/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'CashPilot') }}" />
    <link rel="manifest" href="/site.webmanifest" />

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

    @vite(['resources/css/app.css'])
</head>
<body class="min-h-dvh bg-[#101010] font-sans text-white antialiased">
<main class="relative isolate flex min-h-dvh items-center overflow-hidden px-4 py-8 sm:px-6">
    <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
        <div class="absolute top-0 left-1/2 h-96 w-[42rem] -translate-x-1/2 rounded-full bg-[#02CD86]/10 blur-3xl"></div>
        <div class="absolute right-[-12rem] bottom-[-14rem] h-96 w-96 rounded-full bg-[#02CD86]/5 blur-3xl"></div>
    </div>

    <article class="w-full max-w-lg mx-auto overflow-hidden rounded-[1.75rem] bg-[#1A1A1A] shadow-[0_32px_80px_rgba(0,0,0,0.42)] ring-1 ring-white/10">
        <div id="authorizationContent">
            <header class="border-b border-white/5 px-6 pt-6 pb-5 sm:px-8 sm:pt-8">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <img src="{{ Vite::asset('resources/img/Logo-green.svg') }}" alt="{{ config('app.name', 'CashPilot') }}" class="h-8 w-7" />
                        <span class="text-sm font-semibold tracking-tight">{{ config('app.name', 'CashPilot') }}</span>
                    </div>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-[#02CD86]/10 px-2.5 py-1 text-[11px] font-medium tracking-[0.08em] text-[#02CD86] uppercase ring-1 ring-[#02CD86]/20">
                        <span class="size-1.5 rounded-full bg-[#02CD86]"></span>
                        {{ __('settings.ai.authorize.secure_connection') }}
                    </span>
                </div>

                <div class="mt-8">
                    <div class="grid size-12 place-items-center rounded-2xl bg-[#02CD86]/10 ring-1 ring-[#02CD86]/20">
                        <svg class="size-6 text-[#02CD86]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3.75 19.5 7.5v4.875c0 4.125-2.85 6.825-7.5 7.875-4.65-1.05-7.5-3.75-7.5-7.875V7.5L12 3.75Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.75 12 2.1 2.1 4.4-4.4" />
                        </svg>
                    </div>
                    <p class="mt-5 text-xs font-medium tracking-[0.16em] text-[#989898] uppercase">
                        {{ __('settings.ai.authorize.client_label') }}
                    </p>
                    <h1 class="mt-2 text-2xl font-semibold tracking-[-0.03em] text-white sm:text-3xl">
                        {{ __('settings.ai.authorize.title', ['client' => $client->name]) }}
                    </h1>
                    <p class="mt-3 max-w-md text-sm leading-6 text-[#989898]">
                        {{ __('settings.ai.authorize.description') }}
                    </p>
                </div>
            </header>

            <div class="space-y-5 px-6 py-6 sm:px-8">
                <section class="rounded-2xl bg-[#252525] p-4 ring-1 ring-white/8" aria-labelledby="account-title">
                    <p id="account-title" class="text-[11px] font-medium tracking-[0.12em] text-[#989898] uppercase">
                        {{ __('settings.ai.authorize.logged_in_as') }}
                    </p>
                    <p class="mt-2 truncate text-sm font-medium text-white">{{ $user->email }}</p>
                </section>

                @if(count($scopes) > 0)
                    <section class="rounded-2xl bg-[#02CD86]/7 p-4 ring-1 ring-[#02CD86]/18" aria-labelledby="permissions-title">
                        <div class="flex items-start gap-3">
                            <svg class="mt-0.5 size-4 shrink-0 text-[#02CD86]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m9 12 2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            <div class="min-w-0">
                                <p id="permissions-title" class="text-sm font-medium text-white">{{ __('settings.ai.authorize.permissions') }}</p>
                                <ul class="mt-3 space-y-2">
                                    @foreach($scopes as $scope)
                                        <li class="flex gap-2 text-sm leading-5 text-[#C8D4D0]">
                                            <span class="mt-2 size-1 shrink-0 rounded-full bg-[#02CD86]"></span>
                                            <span>{{ $scope->description }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </section>
                @endif

                <p class="px-1 text-xs leading-5 text-[#989898]">
                    {{ __('settings.ai.authorize.privacy_note') }}
                </p>
            </div>

            <footer class="border-t border-white/5 px-6 py-5 sm:px-8">
                <p class="mb-4 text-xs text-[#989898]">
                    {{ __('settings.ai.authorize.returning', ['client' => $client->name]) }}
                </p>
                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <form method="POST" action="{{ route('passport.authorizations.deny') }}">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="state" value="">
                        <input type="hidden" name="client_id" value="{{ $client->id }}">
                        <input type="hidden" name="auth_token" value="{{ $authToken }}">
                        <button type="submit" class="inline-flex h-11 w-full cursor-pointer items-center justify-center rounded-xl border border-white/10 bg-[#252525] px-5 text-sm font-medium text-white transition hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#02CD86] sm:w-auto">
                            {{ __('settings.ai.authorize.cancel') }}
                        </button>
                    </form>

                    <form method="POST" action="{{ route('passport.authorizations.approve') }}" id="authorizeForm">
                        @csrf
                        <input type="hidden" name="state" value="">
                        <input type="hidden" name="client_id" value="{{ $client->id }}">
                        <input type="hidden" name="auth_token" value="{{ $authToken }}">
                        <button type="submit" class="inline-flex h-11 w-full cursor-pointer items-center justify-center gap-2 rounded-xl bg-[#02CD86] px-5 text-sm font-semibold text-[#101010] transition hover:brightness-110 active:scale-[0.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#02CD86] focus-visible:ring-offset-2 focus-visible:ring-offset-[#1A1A1A] sm:w-auto" id="authorizeButton">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4.5 4.5L19 7" />
                            </svg>
                            <span id="authorizeText">{{ __('settings.ai.authorize.approve') }}</span>
                        </button>
                    </form>
                </div>
            </footer>
        </div>

        <section id="approvalComplete" class="hidden px-6 py-14 text-center sm:px-8" aria-live="polite">
            <div class="mx-auto grid size-14 place-items-center rounded-full bg-[#02CD86]/12 ring-1 ring-[#02CD86]/25">
                <svg class="size-7 text-[#02CD86]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4.5 4.5L19 7" />
                </svg>
            </div>
            <h2 class="mt-6 text-2xl font-semibold tracking-[-0.03em] text-white">{{ __('settings.ai.authorize.success_title') }}</h2>
            <p class="mx-auto mt-3 max-w-sm text-sm leading-6 text-[#989898]">{{ __('settings.ai.authorize.success_description', ['client' => $client->name]) }}</p>
        </section>
    </article>
</main>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('authorizeForm');
        const content = document.getElementById('authorizationContent');
        const complete = document.getElementById('approvalComplete');
        const button = document.getElementById('authorizeButton');
        const authorizeText = document.getElementById('authorizeText');

        form.addEventListener('submit', function(event) {
            if (form.dataset.submitting === 'true') {
                return;
            }

            event.preventDefault();
            form.dataset.submitting = 'true';
            button.disabled = true;
            authorizeText.textContent = @json(__('settings.ai.authorize.authorizing'));
            content.classList.add('hidden');
            complete.classList.remove('hidden');

            window.setTimeout(function() {
                form.submit();
            }, 450);
        });
    });
</script>
</body>
</html>
