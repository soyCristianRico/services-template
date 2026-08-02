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
