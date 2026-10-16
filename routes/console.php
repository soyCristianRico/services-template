<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('landings:publish-scheduled')->dailyAt('07:00');

// El registro de 404 crece con cada rastreo de bot que se cuele por el filtro.
// Sin poda, la pantalla que debería enseñar los enlaces rotos de esta semana
// acaba enseñando los de hace dos años.
Schedule::command('redirects:prune')->weeklyOn(1, '04:00');

// === COPIAS DE SEGURIDAD ===

// Las copias (backup:run, backup:clean y backup:monitor) las programa ruklab/toolkit,
// junto con su configuración: este proyecto no tiene config/backup.php a propósito.

// === MONITORIZACIÓN DEL SERVIDOR ===

// Vigila la máquina, no el sitio: se enciende en uno solo por servidor.
if (config('monitoring.server_monitor')) {
    Schedule::command('server:monitor')
        ->everyTenMinutes()
        ->withoutOverlapping()
        ->appendOutputTo(storage_path('logs/server-monitor.log'));

    Schedule::command('security:check')
        ->everyThirtyMinutes()
        ->withoutOverlapping()
        ->appendOutputTo(storage_path('logs/security-check.log'));

    Schedule::command('security:check-malware')
        ->hourly()
        ->withoutOverlapping()
        ->appendOutputTo(storage_path('logs/malware-check.log'));

    Schedule::command('security:monitor-crontabs')
        ->everyFiveMinutes()
        ->withoutOverlapping()
        ->appendOutputTo(storage_path('logs/crontab-monitor.log'));
}
