<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\GoogleDriveServiceProvider;
use Bugsnag\BugsnagLaravel\BugsnagServiceProvider;

return [
    // El primero a propósito: así captura los fallos que ocurran mientras
    // arrancan los demás.
    BugsnagServiceProvider::class,
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    GoogleDriveServiceProvider::class,
];
