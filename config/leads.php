<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Notification email
    |--------------------------------------------------------------------------
    |
    | When set, every captured Lead is mailed to this address. When left empty,
    | the notification falls back to the first registered user (the site owner),
    | so leads are never silently lost on a fresh deploy.
    |
    | Accepts several addresses separated by commas. A lead that reaches a
    | single inbox is a lead nobody answers while that person is away, and most
    | clients want their own address plus the agency's:
    |
    |     LEAD_NOTIFY_EMAIL="hola@cliente.com,avisos@agencia.com"
    |
    */

    'notify_email' => env('LEAD_NOTIFY_EMAIL'),
];
