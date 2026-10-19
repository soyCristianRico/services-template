<?php

declare(strict_types=1);

use Illuminate\Http\Request;

/**
 * The layout, rendered as the given admin URL would render it. It goes through
 * the view and not through HTTP so it runs anywhere, with no database and no
 * built assets behind it.
 */
function renderAdminLayout(string $path): string
{
    $request = Request::create($path);
    $request->setRouteResolver(fn () => rescue(fn () => app('router')->getRoutes()->match($request), report: false));
    app()->instance('request', $request);

    return (string) view('layouts.admin', ['slot' => ''])->render();
}

/**
 * The labels of the sidebar entries marked as the current one, read from the
 * `data-current` Flux puts on them.
 *
 * @return list<string>
 */
function currentNavEntries(string $html): array
{
    // The attribute, not the `data-current:` variants every entry has in its class.
    preg_match_all('/<a\b(?=[^>]*\sdata-current(?:=|\s|>))[^>]*data-flux-navlist-item[^>]*>(.*?)<\/a>/s', $html, $matches);

    return array_map(fn (string $inner): string => trim(preg_replace('/\s+/', ' ', strip_tags($inner))), $matches[1]);
}

describe('AdminLayout', function (): void {
    beforeEach(function (): void {
        $this->withoutVite();
    });

    describe('flux_scripts', function (): void {
        /**
         * The layout mounts Flux components that only work with its JS: the
         * expandable nav groups (`ui-disclosure`), the profile menu
         * (`ui-dropdown`), the modals and the toasts. Without `@fluxScripts`
         * the markup still paints, but none of it answers to a click.
         */
        it('should load the Flux JS bundle', function (): void {
            expect(renderAdminLayout('/admin'))->toContain('/flux/flux.js');
        });
    });

    describe('public_site_link', function (): void {
        it('should offer a way out to the public site', function (): void {
            $html = renderAdminLayout('/admin');

            expect($html)->toMatch('/<a href="'.preg_quote(url('/'), '/').'"[^>]*target="_blank"[^>]*>(?:(?!<\/a>).)*Ver la web/s');
        });
    });

    describe('scale', function (): void {
        /**
         * The brand scale in app.css is scoped to `[data-public-site]`, so this
         * attribute is what keeps it from reaching the panel. Nothing hangs off
         * it today; it stays as the one hook a future root-level size dial
         * would need (`html:has(body[data-admin-site])`).
         */
        it('should mark the body as the admin', function (): void {
            expect(renderAdminLayout('/admin'))->toContain('data-admin-site');
        });

        /**
         * The panel reads at Flux's own scale, which every one of its controls
         * is built around. Pushing single components off it —text at 16px over
         * a 14px filter bar, or a `text-xs` label flattened up to the size of
         * the value under it— is the bug this guards against, and an unlayered
         * `font-size` on the Flux hooks is the only way to cause it.
         */
        it('should leave the Flux type scale alone', function (): void {
            $css = (string) file_get_contents(resource_path('css/app.css'));

            expect($css)
                ->not->toContain('[data-admin-site] [data-flux-text]')
                ->not->toContain('[data-admin-site] [data-flux-cell]')
                ->not->toContain('[data-admin-site] [data-flux-column]');
        });
    });

    describe('sidebar', function (): void {
        it('should mark the entry of the current section, and only that one', function (string $url, string $entry): void {
            expect(currentNavEntries(renderAdminLayout($url)))->toBe([$entry]);
        })->with([
            'summary' => ['/admin', 'Resumen'],
            'a record' => ['/admin/blog/create', 'Blog'],
            'redirects' => ['/admin/redirects', 'Redirecciones'],
            'broken addresses, not redirects' => ['/admin/redirects/404', 'Direcciones rotas'],
            'a catalog record' => ['/admin/services/create', 'Servicios'],
        ]);

        it('should print the group headings in the client\'s words', function (): void {
            expect(renderAdminLayout('/admin'))
                ->toContain('Web')
                ->toContain('Posicionamiento')
                ->not->toContain('>Contenido<');
        });
    });

    describe('title', function (): void {
        it('should name the current section in the browser tab', function (): void {
            expect(renderAdminLayout('/admin/redirects/404'))->toContain('<title>Direcciones rotas · ');
        });

        it('should fall back to Admin outside any section', function (): void {
            expect(renderAdminLayout('/admin/does-not-exist'))->toContain('<title>Admin · ');
        });
    });

    describe('favicon', function (): void {
        /**
         * The public layout links the icon; the admin used to link nothing, so
         * the browser fell back to `/favicon.ico` — the 0-byte placeholder the
         * Laravel skeleton ships — and painted a blank tab.
         */
        it('should link the same icon as the public site', function (): void {
            expect(renderAdminLayout('/admin'))->toContain('href="'.asset('favicon.png').'"');
        });
    });
});
