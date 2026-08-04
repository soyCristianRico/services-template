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

// Una al día basta: el catálogo lo edita una persona en horario de oficina, no un
// flujo continuo. Lo que no se puede rehacer —los leads y lo subido desde el
// admin— cabe de sobra en la ventana de un día.
Schedule::command('backup:run')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/backup.log'));

// Poda antes que vigilancia, para que el monitor no avise de un espacio que la
// limpieza iba a liberar media hora después.
Schedule::command('backup:clean')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/backup-clean.log'));

// A las 10:00 y no de madrugada a propósito: si la copia de las 03:00 falló, el
// aviso llega a una hora en la que alguien lo va a leer.
Schedule::command('backup:monitor')
    ->dailyAt('10:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/backup-monitor.log'));

// === MONITORIZACIÓN DEL SERVIDOR ===

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
