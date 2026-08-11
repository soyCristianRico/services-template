<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default OG image
    |--------------------------------------------------------------------------
    |
    | Path relative to public/ (passed to asset()), used by every page that
    | does not pass an image of its own. The file ships with the repo, so this
    | is not an environment concern: hardcoding it means a deploy can never
    | end up silently without an og:image. Set to null to omit the tag.
    |
    */

    'default_image' => 'images/og-default.png',

    /*
    |--------------------------------------------------------------------------
    | Landing slug separator
    |--------------------------------------------------------------------------
    |
    | Word inserted between the category slug and city slug when auto-building
    | a Landing slug. Default '' produces "alquiler-generadores-madrid".
    | Set to "en" via .env (LANDING_SLUG_SEPARATOR=en) for the longer, more
    | readable "alquiler-generadores-en-madrid".
    |
    */

    'landing_slug_separator' => env('LANDING_SLUG_SEPARATOR', ''),

    /*
    |--------------------------------------------------------------------------
    | Organization (JSON-LD)
    |--------------------------------------------------------------------------
    |
    | Seeded once per request into the global @graph. Each cloned site fills
    | this in with its own brand identity.
    |
    */

    'organization' => [
        'name' => env('SEO_ORG_NAME'), // null falls back to config('app.name')
        'type' => ['Organization'],
        'logo' => env('SEO_ORG_LOGO'), // path under public/, e.g. 'images/logo.png'
        'area_served' => ['@type' => 'Country', 'name' => 'España'],
        'same_as' => array_values(array_filter([
            env('SEO_ORG_LINKEDIN'),
            env('SEO_ORG_INSTAGRAM'),
            env('SEO_ORG_TWITTER'),
        ])),
    ],
];
