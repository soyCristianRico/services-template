<?php

declare(strict_types=1);

use App\Services\Seo\RedirectPath;

describe('RedirectPath', function (): void {
    describe('normalize', function (): void {
        it('should reduce the many ways of writing one address to a single form', function (string $input): void {
            expect(RedirectPath::normalize($input))->toBe('/curso-antiguo');
        })->with([
            'bare slug' => 'curso-antiguo',
            'leading slash' => '/curso-antiguo',
            'trailing slash' => '/curso-antiguo/',
            'both slashes' => 'curso-antiguo/',
            'upper case' => '/Curso-Antiguo',
            'absolute url' => 'https://cierzoformacion.es/curso-antiguo/',
            'protocol relative' => '//cierzoformacion.es/curso-antiguo',
            'with query' => '/curso-antiguo/?utm_source=mail',
            'with fragment' => '/curso-antiguo#temario',
            'double slashes' => '//curso-antiguo//',
        ]);

        it('should turn an empty address into the home page', function (): void {
            expect(RedirectPath::normalize(''))->toBe('/')
                ->and(RedirectPath::normalize('/'))->toBe('/');
        });
    });

    describe('isValidPattern', function (): void {
        it('should accept a workable pattern', function (): void {
            expect(RedirectPath::isValidPattern('^/blog/\d{4}/(.+)$'))->toBeTrue();
        });

        it('should reject a pattern that would blow up on every request', function (): void {
            expect(RedirectPath::isValidPattern('^/blog/(unclosed'))->toBeFalse();
        });

        it('should reject an empty pattern', function (): void {
            expect(RedirectPath::isValidPattern(''))->toBeFalse();
        });

        it('should accept a pattern containing the characters usually taken as delimiters', function (): void {
            expect(RedirectPath::isValidPattern('^/tag/#(\w+)~$'))->toBeTrue();
        });
    });

    describe('isExcluded', function (): void {
        it('should shield the admin so a bad rule cannot lock the client out', function (): void {
            expect(RedirectPath::isExcluded('/admin'))->toBeTrue()
                ->and(RedirectPath::isExcluded('/admin/redirects'))->toBeTrue()
                ->and(RedirectPath::isExcluded('admin/redirects/create'))->toBeTrue();
        });

        it('should not shield an address that merely starts with the same letters', function (): void {
            expect(RedirectPath::isExcluded('/administrativo-dga'))->toBeFalse();
        });

        it('should leave public addresses alone', function (): void {
            expect(RedirectPath::isExcluded('/oposiciones/educacion'))->toBeFalse();
        });
    });

    describe('withQuery', function (): void {
        it('should attach the incoming parameters to a clean destination', function (): void {
            expect(RedirectPath::withQuery('/cursos', 'utm_source=mail'))->toBe('/cursos?utm_source=mail');
        });

        it('should keep the parameters the destination already carries', function (): void {
            expect(RedirectPath::withQuery('/cursos?tipo=online', 'utm_source=mail'))
                ->toBe('/cursos?tipo=online&utm_source=mail');
        });

        it('should leave the destination untouched when nothing comes in', function (): void {
            expect(RedirectPath::withQuery('/cursos', ''))->toBe('/cursos');
        });
    });
});
