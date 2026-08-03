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
    app()->instance('request', Request::create($path));

    return (string) view('layouts.admin', ['slot' => ''])->render();
}

/**
 * How many nav groups the sidebar renders open, counted on the markup Flux
 * emits: `<ui-disclosure ... open data-flux-navlist-group>`.
 */
function openNavGroups(string $html): int
{
    return preg_match_all('/open\s+data-flux-navlist-group/', $html);
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

    describe('nav_groups', function (): void {
        it('should keep every group collapsed outside its section', function (): void {
            expect(openNavGroups(renderAdminLayout('/admin')))->toBe(0);
        });

        it('should expand only the group holding the current page', function (string $url): void {
            expect(openNavGroups(renderAdminLayout($url)))->toBe(1);
        })->with([
            'catálogo' => '/admin/categories',
            'seo' => '/admin/redirects',
            'contenido' => '/admin/blog',
        ]);
    });
});
