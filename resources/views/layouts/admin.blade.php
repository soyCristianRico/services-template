<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') · {{ config('app.name') }}</title>
    <meta name="robots" content="noindex, follow">

    {{-- Sin esto el navegador se cae a `/favicon.ico`, que es el de 0 bytes que
         trae el esqueleto de Laravel: pestaña en blanco. Es el mismo icono que
         enlaza el sitio público, porque el panel es la misma marca. --}}
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('favicon.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased" data-admin-site>
    <flux:sidebar sticky stashable class="border-r border-zinc-200 bg-white">
        <flux:sidebar.toggle class="lg:hidden" icon="x-mark" inset="left" />

        @if (file_exists(public_path('images/logo.png')))
            <flux:brand href="{{ url('/admin') }}" class="px-2">
                <x-slot:logo class="h-8 min-w-0 rounded-none">
                    <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }}" class="h-8 w-auto">
                </x-slot:logo>
            </flux:brand>
        @else
            <flux:brand href="{{ url('/admin') }}" name="{{ config('app.name') }} admin" class="px-2" />
        @endif

        <flux:navlist variant="outline">
            <flux:navlist.item icon="home" href="{{ url('/admin') }}">Dashboard</flux:navlist.item>
            <flux:navlist.group expandable heading="Catálogo" :expanded="request()->is('admin/categories*', 'admin/services*')">
                <flux:navlist.item href="{{ url('/admin/categories') }}">Categorías</flux:navlist.item>
                <flux:navlist.item href="{{ url('/admin/services') }}">Servicios</flux:navlist.item>
            </flux:navlist.group>
            <flux:navlist.group expandable heading="SEO" :expanded="request()->is('admin/locations*', 'admin/landings*', 'admin/redirects*')">
                @if (config('site.locations'))
                    <flux:navlist.item href="{{ url('/admin/locations') }}">Ubicaciones</flux:navlist.item>
                    <flux:navlist.item href="{{ url('/admin/landings') }}">Landings</flux:navlist.item>
                @endif
                <flux:navlist.item href="{{ url('/admin/redirects') }}">Redirecciones</flux:navlist.item>
                <flux:navlist.item href="{{ url('/admin/redirects/404') }}">Direcciones rotas</flux:navlist.item>
            </flux:navlist.group>
            <flux:navlist.group expandable heading="Contenido" :expanded="request()->is('admin/blog*', 'admin/pages*', 'admin/menus*')">
                <flux:navlist.item href="{{ url('/admin/blog') }}">Blog</flux:navlist.item>
                <flux:navlist.item href="{{ url('/admin/pages') }}">Páginas</flux:navlist.item>
                <flux:navlist.item href="{{ url('/admin/menus') }}">Menús</flux:navlist.item>
            </flux:navlist.group>
            <flux:navlist.item icon="inbox" href="{{ url('/admin/leads') }}">Leads</flux:navlist.item>
        </flux:navlist>

        <flux:spacer />

        <flux:navlist variant="outline">
            <flux:navlist.item icon="arrow-top-right-on-square" href="{{ url('/') }}" target="_blank">
                Ver la web
            </flux:navlist.item>
        </flux:navlist>

        <flux:dropdown position="top" align="start" class="max-lg:hidden">
            <flux:profile :name="auth()->user()?->name ?? 'Admin'" :initials="strtoupper(substr(auth()->user()?->name ?? 'A', 0, 1))" />
            <flux:menu>
                <flux:menu.item href="{{ url('/admin/profile') }}" icon="user">Perfil</flux:menu.item>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" variant="danger">
                        Cerrar sesión
                    </flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    </flux:sidebar>

    <flux:header class="lg:hidden">
        <flux:sidebar.toggle icon="bars-2" inset="left" />
        <flux:spacer />
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <flux:button type="submit" variant="ghost" size="sm" icon="arrow-right-start-on-rectangle">Salir</flux:button>
        </form>
    </flux:header>

    <flux:main>
        {{ $slot ?? '' }}
        @yield('content')
    </flux:main>

    @fluxScripts
</body>
</html>
