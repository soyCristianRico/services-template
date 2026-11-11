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

describe('seo.organization', function (): void {
    describe('name', function (): void {
        it('should be null when SEO_ORG_NAME is empty, so the schema falls back to the app name', function (): void {
            expect(seoOrganizationNameFor(''))->toBeNull();
        });

        it('should be null when SEO_ORG_NAME is missing', function (): void {
            expect(seoOrganizationNameFor(null))->toBeNull();
        });

        it('should keep the name when SEO_ORG_NAME is filled in', function (): void {
            expect(seoOrganizationNameFor('Ruk Ops'))->toBe('Ruk Ops');
        });
    });

    describe('same_as', function (): void {
        it('should join the three social variables with every URL in SEO_ORG_SAME_AS', function (): void {
            $sameAs = seoOrganizationWith([
                'SEO_ORG_LINKEDIN' => 'https://www.linkedin.com/company/ejemplo',
                'SEO_ORG_SAME_AS' => 'https://g.page/r/ejemplo, https://www.paginasamarillas.es/f/ejemplo https://www.linkedin.com/company/ejemplo',
            ])['same_as'];

            expect($sameAs)->toBe([
                'https://www.linkedin.com/company/ejemplo',
                'https://g.page/r/ejemplo',
                'https://www.paginasamarillas.es/f/ejemplo',
            ]);
        });

        it('should be empty when no profile is set', function (): void {
            expect(seoOrganizationWith([])['same_as'])->toBe([]);
        });
    });

    describe('subject_of', function (): void {
        it('should read the JSON list in SEO_ORG_SUBJECT_OF', function (): void {
            $subjectOf = seoOrganizationWith([
                'SEO_ORG_SUBJECT_OF' => '[{"type":"Article","url":"https://prensa.example/reportaje","name":"Reportaje"}]',
            ])['subject_of'];

            expect($subjectOf)->toBe([['type' => 'Article', 'url' => 'https://prensa.example/reportaje', 'name' => 'Reportaje']]);
        });

        it('should be empty when the JSON is broken, instead of breaking the config', function (): void {
            expect(seoOrganizationWith(['SEO_ORG_SUBJECT_OF' => '[{"url":'])['subject_of'])->toBe([]);
        });
    });
});

/**
 * Load config/seo.php's organization block with only these SEO_ORG_* variables set.
 *
 * @param  array<string, string>  $variables
 * @return array<string, mixed>
 */
function seoOrganizationWith(array $variables): array
{
    $keys = ['SEO_ORG_LINKEDIN', 'SEO_ORG_INSTAGRAM', 'SEO_ORG_TWITTER', 'SEO_ORG_SAME_AS', 'SEO_ORG_SUBJECT_OF'];
    $previous = [$_ENV, $_SERVER, array_map(getenv(...), array_combine($keys, $keys))];

    foreach ($keys as $key) {
        unset($_ENV[$key], $_SERVER[$key]);
        putenv($key);
    }

    foreach ($variables as $key => $value) {
        $_ENV[$key] = $_SERVER[$key] = $value;
        putenv($key.'='.$value);
    }

    try {
        return (require config_path('seo.php'))['organization'];
    } finally {
        [$_ENV, $_SERVER] = $previous;

        foreach ($previous[2] as $key => $value) {
            putenv($value === false ? $key : $key.'='.$value);
        }
    }
}
