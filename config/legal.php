<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Legal page
|--------------------------------------------------------------------------
|
| One legal text is shared by every site built on this template: the responsible
| entity is the same everywhere, so only the site's name, its domain and the
| handful of facts a notice must state truthfully live here.
|
| Everything a legal notice DECLARES has to match what the site actually does.
| The processors below are the ones a stock deploy uses — Hetzner via Forge,
| Mailgun, and, when configured, Google Tag Manager and the Discord lead alert.
| A site that adds a newsletter provider, a payment gateway or another vendor
| MUST add it here, or its privacy policy becomes false the day that provider
| starts receiving data.
|
| The page itself is resources/views/pages/⚡legal.blade.php, served at /legal
| with #aviso-legal, #privacidad, #cookies and #terminos anchors.
|
*/

$host = preg_replace('/^www\./', '', (string) (parse_url((string) env('APP_URL', ''), PHP_URL_HOST) ?: ''));

return [

    /*
    |--------------------------------------------------------------------------
    | Responsible entity
    |--------------------------------------------------------------------------
    |
    | The company behind the site. Same across every site; overridable per site
    | through the env vars for the day one of them belongs to someone else.
    |
    */

    'entity' => [
        'name' => env('LEGAL_ENTITY_NAME', 'RUK MAR, COOP. V.'),
        'address' => env('LEGAL_ENTITY_ADDRESS', 'Avenida Primado Reig 129 D, 46020 València, España'),
        'email' => env('LEGAL_EMAIL', $host !== '' ? 'hola@'.$host : null),
    ],

    /*
    | The courts the terms submit to. Kept explicit because it is the one value
    | that cannot be derived and that nobody notices is wrong.
    */

    'jurisdiction' => env('LEGAL_JURISDICTION', 'València'),

    /*
    | Shown at the foot of the page. A literal string, and deliberately not a
    | live date: it must state when the TEXT last changed, not when the page was
    | rendered. Update it by hand whenever you edit the blade.
    */

    'updated_at' => 'agosto de 2026',

    /*
    |--------------------------------------------------------------------------
    | What this site does
    |--------------------------------------------------------------------------
    |
    | Read into "Finalidad de la página web". Rewrite per vertical: the default
    | describes a brokerage site that presents an offering and quotes for it.
    |
    */

    'services' => [
        'Intermediación en el alquiler de equipos y servicios: presentación de la oferta disponible y elaboración de presupuestos.',
        'Publicación de contenidos editoriales y guías sobre el sector.',
        'Atención de las solicitudes de presupuesto enviadas desde la web.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Who else sees the data
    |--------------------------------------------------------------------------
    |
    | The two lists below are the part of the privacy policy that is legally
    | load-bearing. Keep them honest.
    |
    | `recipients` are third parties the data is HANDED OVER to, as opposed to
    | processed on our behalf — a CRM the lead form posts to, a partner that
    | receives the enquiry. Leave it empty and the page states that no data is
    | disclosed to anyone; that sentence is only true while it stays empty.
    |
    | `processors` receive data on our behalf. An entry carrying `requires` is
    | declared only when that config key holds a value, which ties the policy to
    | the switch that actually turns the provider on: the text can neither claim
    | a provider the site is not using nor stay silent about one it is.
    |
    */

    'recipients' => [],

    'processors' => [
        [
            'purpose' => 'Alojamiento',
            'provider' => 'Hetzner Online GmbH',
            'detail' => 'Servidores situados en la Unión Europea, administrados a través de Laravel Forge.',
            'url' => 'https://www.hetzner.com/legal/privacy-policy/',
        ],
        [
            'purpose' => 'Correo electrónico transaccional',
            'provider' => 'Mailgun',
            'detail' => 'Entrega de los avisos que generan los formularios del sitio, sobre su infraestructura europea.',
            'url' => 'https://www.mailgun.com/legal/privacy-policy/',
        ],
        [
            'purpose' => 'Analítica web',
            'provider' => 'Google Ireland Limited',
            'detail' => 'Google Tag Manager y Google Analytics, que sólo se cargan si aceptas las cookies de análisis.',
            'url' => 'https://policies.google.com/privacy',
            'requires' => 'services.google_tag_manager.id',
        ],
        [
            'purpose' => 'Aviso interno de nuevas solicitudes',
            'provider' => 'Discord',
            'detail' => 'Cada solicitud enviada desde un formulario se publica en un canal privado del equipo para poder atenderla, incluyendo el nombre, el teléfono, el correo y el mensaje. Discord trata datos en Estados Unidos.',
            'url' => 'https://discord.com/privacy',
            'requires' => 'services.discord.webhook_url',
        ],
    ],

];
