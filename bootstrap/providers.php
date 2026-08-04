<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\GoogleDriveServiceProvider;
use Bugsnag\BugsnagLaravel\BugsnagServiceProvider;

return [
    AppServiceProvider::class,
    BugsnagServiceProvider::class,
    FortifyServiceProvider::class,
    GoogleDriveServiceProvider::class,
];
