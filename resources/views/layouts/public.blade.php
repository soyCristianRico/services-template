<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {!! SEO::generate(true) !!}

    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('favicon.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @if (($gtmId = config('services.google_tag_manager.id')) && auth()->guest())
        {{-- Consent Mode v2 defaults — must precede the GTM snippet --}}
        <x-cookies.consent-mode-default />
        {{-- Google Tag Manager --}}
        <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer',@json($gtmId));</script>
        {{-- End Google Tag Manager --}}
    @endif
</head>
<body data-public-site class="min-h-screen bg-background font-sans text-foreground antialiased">
    @if (($gtmId ?? config('services.google_tag_manager.id')) && auth()->guest())
        {{-- Google Tag Manager (noscript) --}}
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $gtmId ?? config('services.google_tag_manager.id') }}"
            height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        {{-- End Google Tag Manager (noscript) --}}
    @endif
    <header class="border-b border-border bg-surface">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
            <a href="{{ url('/') }}" class="flex items-center" aria-label="{{ config('app.name') }}">
                {{-- Each site drops its own logo at public/images/logo.png. Until it
                     does, fall back to the site name rather than a broken image. --}}
                @if (file_exists(public_path('images/logo.png')))
                    <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }}" class="h-12 w-auto">
                @else
                    <span class="font-display text-xl font-semibold text-foreground">{{ config('app.name') }}</span>
                @endif
            </a>
            <nav class="hidden gap-6 text-sm md:flex">
                {{-- Each site overrides this nav --}}
            </nav>
        </div>
    </header>

    <main>
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    <footer class="mt-24 border-t border-border">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-6 py-8 text-sm text-muted-foreground">
            <span>&copy; {{ date('Y') }} {{ config('app.name') }}</span>
            <a href="{{ route('legal') }}#aviso-legal" wire:navigate class="transition hover:text-foreground">Aviso legal</a>
            <a href="{{ route('legal') }}#privacidad" wire:navigate class="transition hover:text-foreground">Política de privacidad</a>
            <a href="{{ route('legal') }}#cookies" wire:navigate class="transition hover:text-foreground">Política de cookies</a>
            @if (config('services.google_tag_manager.id') && auth()->guest())
                <button type="button"
                    x-data
                    x-on:click="window.dispatchEvent(new CustomEvent('open-cookie-settings'))"
                    class="underline hover:no-underline">
                    Configurar cookies
                </button>
            @endif
        </div>
    </footer>

    @fluxScripts

    {{-- Cookie consent banner (GDPR / ePrivacy) — drives Google Consent Mode v2 --}}
    @if (config('services.google_tag_manager.id') && auth()->guest())
        <x-cookies.banner />
    @endif

    {{-- Explicit so Livewire/Alpine also boot on error pages, where auto-injection doesn't run. --}}
    @livewireScripts
</body>
</html>
