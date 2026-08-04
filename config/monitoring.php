<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Developer email
    |--------------------------------------------------------------------------
    |
    | Technical alerts, not `leads.notify_email`: a client can do nothing with a
    | swap warning at 03:00. Several addresses, comma separated. Empty is fine
    | locally — alerts go nowhere instead of failing.
    |
    | `config/server-monitor.php` reads the same variable through env(): a config
    | file cannot reliably read another one.
    |
    */

    'developer_email' => env('DEVELOPER_EMAIL'),

    /*
    |--------------------------------------------------------------------------
    | Server monitoring
    |--------------------------------------------------------------------------
    |
    | The server checks watch the MACHINE, not the site: sites sharing a server
    | would each report the same full disk and each scan the same filesystem. On
    | for one site per machine, off on the rest.
    |
    | The backups are the other way round and always run: those are the site's.
    |
    */

    'server_monitor' => env('SERVER_MONITOR_ENABLED', false),
];
