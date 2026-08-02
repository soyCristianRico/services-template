<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Rutas intocables
    |--------------------------------------------------------------------------
    |
    | Prefixes the redirect middleware never looks at. `admin` is the important
    | one: a careless regex could otherwise bounce the client out of the very
    | panel they need to delete it from, with no way back in.
    |
    */
    'excluded_prefixes' => [
        'admin',
        'api',
        'livewire',
        'storage',
        'up',
        'login',
        'logout',
    ],

    /*
    |--------------------------------------------------------------------------
    | Registro de 404
    |--------------------------------------------------------------------------
    */
    'log_not_found' => env('REDIRECTS_LOG_NOT_FOUND', true),

    /**
     * Addresses never worth logging. Bots probe for these by the thousand on any
     * public site, and without this filter they drown the real broken links —
     * which is the only thing the screen exists to surface.
     */
    'ignored_not_found_patterns' => [
        '\.(php|asp|aspx|jsp|cgi|env|git|sql|bak|zip|tar|gz)$',
        '\.(css|js|map|ico|png|jpe?g|gif|svg|webp|avif|woff2?|ttf|eot|pdf)$',
        '^/(wp-|wordpress|xmlrpc|phpmyadmin|vendor/|\.well-known/|cgi-bin)',
        '^/(autodiscover|owa|ecp|remote|telescope|debug)',
    ],

    /** Rows older than this with no hits since are pruned by `redirects:prune`. */
    'not_found_retention_days' => 90,
];
