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
