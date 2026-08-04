<?php

use App\Http\Middleware\LogNotFoundRequests;
use App\Http\Middleware\RedirectRequests;
use Bugsnag\BugsnagLaravel\OomBootstrapper;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// Antes de configure() y antes de cualquier otro bootstrapper: sube el límite de
// memoria al agotarse para que el aviso llegue a salir.
(new OomBootstrapper)->bootstrap();

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            require base_path('routes/ai.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Antes del enrutado, no colgando del 404: una dirección retirada puede
        // seguir resolviendo contra una ruta viva o contra el catch-all
        // `/{slug}`, así que una redirección enganchada al 404 no llegaría a
        // verla nunca. El registro de 404 va por fuera para poder mirar el
        // código de la respuesta ya generada.
        $middleware->prepend([
            LogNotFoundRequests::class,
            RedirectRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
