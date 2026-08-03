<?php

declare(strict_types=1);

use App\Enums\RedirectMatchType;
use App\Enums\RedirectStatusCode;
use App\Models\Redirect;
use App\Services\Seo\RedirectResolver;

function resolver(): RedirectResolver
{
    $resolver = new RedirectResolver;
    $resolver->forget();

    return $resolver;
}

describe('RedirectResolver', function (): void {
    describe('resolve', function (): void {
        it('should send an exact match to its destination', function (): void {
            Redirect::factory()->create(['source' => '/curso-antiguo', 'destination' => '/cursos/nuevo']);

            $target = resolver()->resolve('/curso-antiguo');

            expect($target?->url)->toBe(url('/cursos/nuevo'))
                ->and($target?->statusCode)->toBe(301);
        });

        it('should match however the address was written', function (string $incoming): void {
            Redirect::factory()->create(['source' => '/curso-antiguo', 'destination' => '/cursos/nuevo']);

            expect(resolver()->resolve($incoming)?->url)->toBe(url('/cursos/nuevo'));
        })->with([
            'trailing slash' => '/curso-antiguo/',
            'no leading slash' => 'curso-antiguo',
            'upper case' => '/Curso-Antiguo',
        ]);

        it('should leave an address with no rule alone', function (): void {
            Redirect::factory()->create(['source' => '/curso-antiguo']);

            expect(resolver()->resolve('/otra-cosa'))->toBeNull();
        });

        it('should ignore a deactivated redirect', function (): void {
            Redirect::factory()->inactive()->create(['source' => '/curso-antiguo', 'destination' => '/cursos']);

            expect(resolver()->resolve('/curso-antiguo'))->toBeNull();
        });

        it('should answer a retired page with 410 and no destination', function (): void {
            Redirect::factory()->gone()->create(['source' => '/seccion-retirada']);

            $target = resolver()->resolve('/seccion-retirada');

            expect($target?->statusCode)->toBe(410)
                ->and($target?->isGone())->toBeTrue()
                ->and($target?->url)->toBeNull();
        });

        it('should hand an external destination over untouched', function (): void {
            Redirect::factory()->create([
                'source' => '/webinar',
                'destination' => 'https://zoom.us/j/123',
            ]);

            expect(resolver()->resolve('/webinar')?->url)->toBe('https://zoom.us/j/123');
        });
    });

    describe('query string', function (): void {
        it('should carry campaign parameters over to the new address', function (): void {
            Redirect::factory()->create(['source' => '/promo', 'destination' => '/cursos']);

            expect(resolver()->resolve('/promo', 'utm_source=mail&utm_campaign=verano')?->url)
                ->toBe(url('/cursos').'?utm_source=mail&utm_campaign=verano');
        });

        it('should drop them when the redirect says not to keep them', function (): void {
            Redirect::factory()->create([
                'source' => '/promo',
                'destination' => '/cursos',
                'preserve_query' => false,
            ]);

            expect(resolver()->resolve('/promo', 'utm_source=mail')?->url)->toBe(url('/cursos'));
        });

        it('should keep the parameters the destination already had', function (): void {
            Redirect::factory()->create(['source' => '/promo', 'destination' => '/cursos?tipo=online']);

            expect(resolver()->resolve('/promo', 'utm_source=mail')?->url)
                ->toBe(url('/cursos?tipo=online').'&utm_source=mail');
        });
    });

    describe('prefix matching', function (): void {
        it('should move a whole folder and keep what hangs off it', function (): void {
            Redirect::factory()->prefix()->create([
                'source' => '/formacion',
                'destination' => '/legal',
            ]);

            expect(resolver()->resolve('/formacion/curso-a/tema-1')?->url)->toBe(url('/legal/curso-a/tema-1'));
        });

        it('should also match the folder itself', function (): void {
            Redirect::factory()->prefix()->create(['source' => '/formacion', 'destination' => '/legal']);

            expect(resolver()->resolve('/formacion')?->url)->toBe(url('/legal'));
        });

        it('should not match an address that merely starts with the same letters', function (): void {
            Redirect::factory()->prefix()->create(['source' => '/formacion', 'destination' => '/legal']);

            expect(resolver()->resolve('/formacion-continua'))->toBeNull();
        });

        it('should let the more specific prefix win regardless of creation order', function (): void {
            Redirect::factory()->prefix()->create(['source' => '/blog', 'destination' => '/legal']);
            Redirect::factory()->prefix()->create(['source' => '/blog/2019', 'destination' => '/archivo']);

            expect(resolver()->resolve('/blog/2019/enero')?->url)->toBe(url('/archivo/enero'));
        });

        it('should append the leftover to the path, never after the parameters', function (): void {
            Redirect::factory()->prefix()->create([
                'source' => '/formacion',
                'destination' => '/legal?origen=migracion',
            ]);

            expect(resolver()->resolve('/formacion/curso-a')?->url)
                ->toBe(url('/legal/curso-a').'?origen=migracion');
        });
    });

    describe('pattern matching', function (): void {
        it('should place the captured pieces into the destination', function (): void {
            Redirect::factory()->regex()->create([
                'source' => '^/blog/\d{4}/(.+)$',
                'destination' => '/blog/$1',
            ]);

            expect(resolver()->resolve('/blog/2019/como-estudiar')?->url)->toBe(url('/blog/como-estudiar'));
        });

        it('should skip a pattern that stopped compiling instead of breaking the site', function (): void {
            Redirect::factory()->regex()->create(['source' => '^/blog/(unclosed', 'destination' => '/blog']);
            Redirect::factory()->create(['source' => '/curso-antiguo', 'destination' => '/cursos']);

            expect(resolver()->resolve('/curso-antiguo')?->url)->toBe(url('/cursos'));
        });

        it('should try patterns only after exact and prefix rules', function (): void {
            Redirect::factory()->regex()->create(['source' => '^/curso-(.+)$', 'destination' => '/generico']);
            Redirect::factory()->create(['source' => '/curso-antiguo', 'destination' => '/especifico']);

            expect(resolver()->resolve('/curso-antiguo')?->url)->toBe(url('/especifico'));
        });
    });

    describe('loop protection', function (): void {
        it('should refuse to send an address to itself', function (): void {
            // Saved straight to the table on purpose: the form blocks this, but a
            // pattern or an import can still produce it.
            Redirect::factory()->create(['source' => '/cursos', 'destination' => '/cursos/']);

            expect(resolver()->resolve('/cursos'))->toBeNull();
        });

        it('should refuse when a pattern rewrites an address into itself', function (): void {
            Redirect::factory()->regex()->create([
                'source' => '^/blog/(.+)$',
                'destination' => '/blog/$1',
            ]);

            expect(resolver()->resolve('/blog/una-entrada'))->toBeNull();
        });
    });

    describe('caching', function (): void {
        it('should stop reading the table once the map is built', function (): void {
            Redirect::factory()->create(['source' => '/curso-antiguo', 'destination' => '/cursos']);

            $resolver = resolver();
            $resolver->resolve('/curso-antiguo');

            $queries = 0;
            DB::listen(function () use (&$queries): void {
                $queries++;
            });

            $resolver->resolve('/curso-antiguo');
            $resolver->resolve('/otra-cosa');

            expect($queries)->toBe(0);
        });

        it('should pick up a change made from the panel', function (): void {
            $redirect = Redirect::factory()->create(['source' => '/curso-antiguo', 'destination' => '/cursos']);

            resolver()->resolve('/curso-antiguo');

            $redirect->update(['destination' => '/otro-destino']);

            expect((new RedirectResolver)->resolve('/curso-antiguo')?->url)->toBe(url('/otro-destino'));
        });
    });

    describe('status codes', function (): void {
        it('should answer with the code the redirect was given', function (): void {
            Redirect::factory()->create([
                'source' => '/promo',
                'destination' => '/cursos',
                'status_code' => RedirectStatusCode::Found->value,
            ]);

            expect(resolver()->resolve('/promo')?->statusCode)->toBe(302);
        });
    });

    describe('match type coverage', function (): void {
        it('should have a branch for every match type', function (): void {
            foreach (RedirectMatchType::cases() as $case) {
                Redirect::factory()->create([
                    'source' => $case === RedirectMatchType::Regex ? '^/x-'.$case->value.'$' : '/x-'.$case->value,
                    'destination' => '/destino',
                    'match_type' => $case->value,
                ]);
            }

            expect(resolver()->resolve('/x-exact')?->url)->toBe(url('/destino'))
                ->and(resolver()->resolve('/x-prefix')?->url)->toBe(url('/destino'))
                ->and(resolver()->resolve('/x-regex')?->url)->toBe(url('/destino'));
        });
    });
});
