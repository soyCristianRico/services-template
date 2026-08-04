<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    <meta name="robots" content="noindex, follow">

    {{-- Sin esto el navegador se cae a `/favicon.ico`, que es el de 0 bytes que
         trae el esqueleto de Laravel: pestaña en blanco. Es el mismo icono que
         enlaza el sitio público, porque el panel es la misma marca. --}}
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('favicon.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <script>
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) {
                window.location.reload();
            }
        });
    </script>
</head>
<body class="min-h-screen bg-zinc-100 antialiased">
    <div class="flex min-h-screen flex-col items-center justify-center space-y-4 px-4 py-8">
        <flux:heading size="lg">{{ config('app.name') }}</flux:heading>

        <flux:card class="w-full max-w-sm">
            @yield('content')
        </flux:card>
    </div>

    @fluxScripts
</body>
</html>
