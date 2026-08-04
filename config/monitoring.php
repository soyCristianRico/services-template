<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Developer email
    |--------------------------------------------------------------------------
    |
    | Where the technical alerts of this site go: a failed backup, a backup that
    | stopped being fresh, a full disk, a modified crontab. This is deliberately
    | NOT `leads.notify_email`: that one is the client's inbox, and a client can
    | do nothing with a swap warning at 03:00.
    |
    | Accepts several addresses separated by commas, so a site can reach both the
    | person on call and a shared inbox:
    |
    |     DEVELOPER_EMAIL="tu@agencia.com,avisos@agencia.com"
    |
    | Leaving it empty is legitimate in local development: alerts are then routed
    | nowhere instead of failing, which is why `config/backup.php` never reads it
    | directly. Spatie validates every address when it builds its config object
    | and an empty string would throw there, taking `backup:run` down with it.
    |
    | `config/server-monitor.php` reads the same DEVELOPER_EMAIL variable. It goes
    | through env() rather than through this file because a config file cannot
    | reliably read another one — the load order between them is not guaranteed.
    |
    */

    'developer_email' => env('DEVELOPER_EMAIL'),

    /*
    |--------------------------------------------------------------------------
    | Server monitoring
    |--------------------------------------------------------------------------
    |
    | Whether this site runs the server checks: CPU, memory, swap, disk, malware
    | scanning, crontab changes, watched ports.
    |
    | None of that is a property of the site — it is a property of the machine.
    | Several sites sharing a server would each report the same full disk, and
    | each scan the same filesystem every hour. So this is switched on for ONE
    | site per machine, the one that acts as its watchman, and left off on the
    | rest.
    |
    | Off by default: on a shared server the safe failure is the check that does
    | not run, not four copies of it that do.
    |
    | The backups are the other way round and are always on: those belong to the
    | site, not to the machine.
    |
    */

    'server_monitor' => env('SERVER_MONITOR_ENABLED', false),
];
