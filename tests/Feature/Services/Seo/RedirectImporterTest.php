<?php

declare(strict_types=1);

use App\Enums\RedirectMatchType;
use App\Models\Redirect;
use App\Services\Seo\RedirectImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('RedirectImporter', function (): void {
    describe('parse', function (): void {
        it('should read a plain two-column list', function (): void {
            $rows = app(RedirectImporter::class)->parse("/vieja;/nueva\n/otra;/otra-nueva");

            expect($rows)->toHaveCount(2)
                ->and($rows[0]['source'])->toBe('/vieja')
                ->and($rows[0]['destination'])->toBe('/nueva')
                ->and($rows[0]['status_code'])->toBe(301)
                ->and($rows[0]['outcome'])->toBe(RedirectImporter::OUTCOME_NEW);
        });

        it('should accept whatever separator the list happens to use', function (string $line): void {
            $rows = app(RedirectImporter::class)->parse($line);

            expect($rows[0]['source'])->toBe('/vieja')
                ->and($rows[0]['destination'])->toBe('/nueva');
        })->with([
            'semicolon' => '/vieja;/nueva',
            'comma' => '/vieja,/nueva',
            'tab' => "/vieja\t/nueva",
            'spaces' => '/vieja  /nueva',
        ]);

        it('should skip blank lines, comments and a header row', function (): void {
            $rows = app(RedirectImporter::class)->parse("source;destination\n\n# un comentario\n/vieja;/nueva");

            expect($rows)->toHaveCount(1)
                ->and($rows[0]['source'])->toBe('/vieja');
        });

        it('should take the code from the third column', function (): void {
            $rows = app(RedirectImporter::class)->parse('/promo;/cursos;302');

            expect($rows[0]['status_code'])->toBe(302);
        });

        it('should take the match type from the fourth column', function (): void {
            $rows = app(RedirectImporter::class)->parse('^/blog/(.+)$;/legal/$1;301;regex');

            expect($rows[0]['match_type'])->toBe(RedirectMatchType::Regex->value)
                ->and($rows[0]['outcome'])->toBe(RedirectImporter::OUTCOME_NEW);
        });

        it('should accept a retired page with no destination', function (): void {
            $rows = app(RedirectImporter::class)->parse('/retirada;;410');

            expect($rows[0]['destination'])->toBeNull()
                ->and($rows[0]['outcome'])->toBe(RedirectImporter::OUTCOME_NEW);
        });

        it('should tidy the old addresses into the form that will be compared', function (): void {
            $rows = app(RedirectImporter::class)->parse('https://example.test/Curso-Antiguo/;/blog');

            expect($rows[0]['source'])->toBe('/curso-antiguo');
        });

        it('should bring a destination on our own domain back to a plain path', function (): void {
            // A CMS export writes them absolute. Left alone, every redirect would
            // bounce visitors through the full domain for nothing.
            config()->set('app.url', 'https://example.test');

            $rows = app(RedirectImporter::class)->parse('/vieja;https://example.test/blog/');

            expect($rows[0]['destination'])->toBe('/blog');
        });

        it('should leave a destination on someone else‘s domain as it is', function (): void {
            config()->set('app.url', 'https://example.test');

            $rows = app(RedirectImporter::class)->parse('/webinar;https://zoom.us/j/123');

            expect($rows[0]['destination'])->toBe('https://zoom.us/j/123');
        });

        it('should flag a line with no destination and no 410', function (): void {
            $rows = app(RedirectImporter::class)->parse('/vieja');

            expect($rows[0]['outcome'])->toBe(RedirectImporter::OUTCOME_ERROR)
                ->and($rows[0]['message'])->toContain('destino');
        });

        it('should flag a code nobody can serve', function (): void {
            $rows = app(RedirectImporter::class)->parse('/vieja;/nueva;418');

            expect($rows[0]['outcome'])->toBe(RedirectImporter::OUTCOME_ERROR);
        });

        it('should flag a pattern that does not compile', function (): void {
            $rows = app(RedirectImporter::class)->parse('^/blog/(unclosed;/blog;301;regex');

            expect($rows[0]['outcome'])->toBe(RedirectImporter::OUTCOME_ERROR);
        });

        it('should flag a line pointing an address at itself', function (): void {
            $rows = app(RedirectImporter::class)->parse('/vieja;/vieja/');

            expect($rows[0]['outcome'])->toBe(RedirectImporter::OUTCOME_ERROR);
        });

        it('should flag a line aimed at the admin', function (): void {
            $rows = app(RedirectImporter::class)->parse('/admin/redirects;/');

            expect($rows[0]['outcome'])->toBe(RedirectImporter::OUTCOME_ERROR);
        });

        it('should flag the second appearance of the same address', function (): void {
            $rows = app(RedirectImporter::class)->parse("/vieja;/nueva\n/Vieja/;/otra");

            expect($rows[0]['outcome'])->toBe(RedirectImporter::OUTCOME_NEW)
                ->and($rows[1]['outcome'])->toBe(RedirectImporter::OUTCOME_DUPLICATE);
        });

        it('should say when an address already has a redirect', function (): void {
            Redirect::factory()->create(['source' => '/vieja']);

            $rows = app(RedirectImporter::class)->parse('/vieja;/nueva');

            expect($rows[0]['outcome'])->toBe(RedirectImporter::OUTCOME_REPLACES);
        });
    });

    describe('import', function (): void {
        it('should create the redirects the list describes', function (): void {
            $importer = app(RedirectImporter::class);

            $result = $importer->import($importer->parse("/vieja;/nueva\n/otra;/otra-nueva"), false);

            expect($result['created'])->toBe(2)
                ->and(Redirect::query()->count())->toBe(2);
        });

        it('should leave existing redirects alone unless told otherwise', function (): void {
            Redirect::factory()->create(['source' => '/vieja', 'destination' => '/original']);
            $importer = app(RedirectImporter::class);

            $result = $importer->import($importer->parse('/vieja;/nueva'), false);

            expect($result['skipped'])->toBe(1)
                ->and(Redirect::query()->first()->destination)->toBe('/original');
        });

        it('should replace them when asked to', function (): void {
            Redirect::factory()->create(['source' => '/vieja', 'destination' => '/original']);
            $importer = app(RedirectImporter::class);

            $result = $importer->import($importer->parse('/vieja;/nueva'), true);

            expect($result['updated'])->toBe(1)
                ->and(Redirect::query()->first()->destination)->toBe('/nueva');
        });

        it('should import the good lines and drop the broken ones', function (): void {
            $importer = app(RedirectImporter::class);

            $result = $importer->import($importer->parse("/vieja;/nueva\n/rota\n/otra;/otra-nueva"), false);

            expect($result['created'])->toBe(2)
                ->and($result['skipped'])->toBe(1)
                ->and(Redirect::query()->count())->toBe(2);
        });

        it('should leave the imported redirects working right away', function (): void {
            $importer = app(RedirectImporter::class);
            $importer->import($importer->parse('/vieja;/blog'), false);

            $this->get('/vieja')->assertRedirect(url('/blog'));
        });
    });
});
