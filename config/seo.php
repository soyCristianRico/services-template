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
    | same_as    — pages that ARE the brand elsewhere: its social accounts and
    |              its own listings (Google Business Profile, directories,
    |              portals). The three social variables stay for the sites that
    |              already use them; any other profile goes in SEO_ORG_SAME_AS,
    |              several URLs separated by spaces or commas.
    | subject_of — pages ABOUT the brand: interviews, podcasts, press articles.
    |              SEO_ORG_SUBJECT_OF holds a JSON list of {type, url, name};
    |              each entry goes out as a CreativeWork of that type
    |              (Article, PodcastEpisode, VideoObject...).
    |
    | Both live in .env so a new listing or interview is added without touching
    | code. Which URLs qualify is decided by Ruk Lab's process SEO289.
    |
    */

    'organization' => [
        'name' => env('SEO_ORG_NAME') ?: null, // an empty SEO_ORG_NAME= reads as "", not null; ?: null lets it fall back to config('app.name')
        'type' => ['Organization'],
        'logo' => env('SEO_ORG_LOGO'), // path under public/, e.g. 'images/logo.png'
        'area_served' => ['@type' => 'Country', 'name' => 'España'],
        'same_as' => array_values(array_unique(array_filter([
            env('SEO_ORG_LINKEDIN'),
            env('SEO_ORG_INSTAGRAM'),
            env('SEO_ORG_TWITTER'),
            ...preg_split('/[\s,]+/', (string) env('SEO_ORG_SAME_AS', ''), flags: PREG_SPLIT_NO_EMPTY),
        ]))),
        'subject_of' => (array) (json_decode((string) env('SEO_ORG_SUBJECT_OF', ''), true) ?: []),
    ],
];
