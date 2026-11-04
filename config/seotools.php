<?php

declare(strict_types=1);

/**
 * @see https://github.com/artesaos/seotools
 *
 * The defaults below are deliberately minimal — every page calls
 * SeoService::setSEO() which overrides title/description/url/canonical, so the
 * defaults only act as a safety net when a page forgets to set them.
 */
return [
    'inertia' => env('SEO_TOOLS_INERTIA', false),

    'meta' => [
        'defaults' => [
            'title' => env('APP_NAME', 'Services'),
            'titleBefore' => false,
            'description' => false,
            'separator' => ' - ',
            'keywords' => [],
            'canonical' => 'current',
            'robots' => 'index, follow',
        ],
        'webmaster_tags' => [
            'google' => env('SEO_WEBMASTER_GOOGLE'),
            'bing' => null,
            'alexa' => null,
            'pinterest' => null,
            'yandex' => null,
            'norton' => null,
        ],
        'add_notranslate_class' => false,
    ],

    'opengraph' => [
        'defaults' => [
            'title' => false,
            'description' => false,
            'url' => false,
            'type' => 'website',
            'site_name' => env('APP_NAME', 'Services'),
            'images' => [],
        ],
    ],

    'twitter' => [
        'defaults' => [
            'card' => 'summary_large_image',
        ],
    ],

    'json-ld' => [
        'defaults' => [
            'title' => false,
            'description' => false,
            'url' => false,
            'type' => false,
            'images' => [],
        ],
    ],
];
