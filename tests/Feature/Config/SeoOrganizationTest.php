<?php

declare(strict_types=1);

/**
 * The production env template ships `SEO_ORG_NAME=` empty, and Laravel reads an
 * empty variable as "" rather than null — so a `??` fallback to the app name
 * never fires and the Organization schema goes out with `"name": ""`.
 */
function seoOrganizationNameFor(?string $value): mixed
{
    $previous = [$_ENV, $_SERVER, getenv('SEO_ORG_NAME')];

    if ($value === null) {
        unset($_ENV['SEO_ORG_NAME'], $_SERVER['SEO_ORG_NAME']);
        putenv('SEO_ORG_NAME');
    } else {
        $_ENV['SEO_ORG_NAME'] = $_SERVER['SEO_ORG_NAME'] = $value;
        putenv('SEO_ORG_NAME='.$value);
    }

    try {
        return (require config_path('seo.php'))['organization']['name'];
    } finally {
        [$_ENV, $_SERVER] = $previous;
        putenv($previous[2] === false ? 'SEO_ORG_NAME' : 'SEO_ORG_NAME='.$previous[2]);
    }
}

describe('seo.organization', function () {
    describe('name', function () {
        it('should be null when SEO_ORG_NAME is empty, so the schema falls back to the app name', function () {
            expect(seoOrganizationNameFor(''))->toBeNull();
        });

        it('should be null when SEO_ORG_NAME is missing', function () {
            expect(seoOrganizationNameFor(null))->toBeNull();
        });

        it('should keep the name when SEO_ORG_NAME is filled in', function () {
            expect(seoOrganizationNameFor('Ruk Ops'))->toBe('Ruk Ops');
        });
    });
});
