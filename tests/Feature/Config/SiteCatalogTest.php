<?php

declare(strict_types=1);

use App\Mcp\Servers\ServicesServer;
use App\Models\User;
use Illuminate\Routing\RouteCollection;

/**
 * The catalog is a switch, like the geography: a site without one keeps every
 * file of the template and just stops offering the catalog to the client.
 */
describe('site.catalog', function () {
    describe('enabled', function () {
        it('should register the catalog admin routes', function () {
            expect(catalogRouteNames(true))
                ->toContain('admin.categories.index')
                ->toContain('admin.services.index');
        });

        it('should offer the catalog MCP tools', function () {
            expect(catalogToolNames(true))->toContain('list-services-tool')->toContain('list-categories-tool');
        });
    });

    describe('disabled', function () {
        it('should not register the catalog admin routes', function () {
            $names = catalogRouteNames(false);

            expect($names)->not->toContain('admin.categories.index')
                ->and($names)->not->toContain('admin.services.edit')
                ->and($names)->toContain('admin.blog.index');
        });

        it('should hide the catalog from the admin menu', function () {
            config()->set('site.catalog', false);

            $this->withoutVite()
                ->actingAs(User::factory()->create())
                ->get('/admin')
                ->assertOk()
                ->assertDontSee('Catálogo')
                ->assertDontSee('>Servicios<', false);
        });

        it('should not offer the catalog MCP tools, and keep the rest', function () {
            expect(catalogToolNames(false))
                ->not->toContain('list-services-tool')
                ->not->toContain('list-categories-tool')
                ->toContain('list-blog-posts-tool')
                ->toContain('list-pages-tool');
        });
    });
});

/**
 * Route files load once per process; re-run the file under the flag instead.
 *
 * @return list<string>
 */
function catalogRouteNames(bool $catalog): array
{
    config()->set('site.catalog', $catalog);

    $router = app('router');
    $original = $router->getRoutes();

    $router->setRoutes(new RouteCollection);
    require base_path('routes/web.php');
    $router->getRoutes()->refreshNameLookups();
    $names = array_keys($router->getRoutes()->getRoutesByName());

    $router->setRoutes($original);

    return $names;
}

/**
 * @return list<string>
 */
function catalogToolNames(bool $catalog): array
{
    config()->set('site.catalog', $catalog);

    $reflection = new ReflectionClass(ServicesServer::class);
    $server = $reflection->newInstanceWithoutConstructor();
    $reflection->getMethod('boot')->invoke($server);

    return array_map(
        fn (string $tool): string => app($tool)->name(),
        $reflection->getProperty('tools')->getValue($server),
    );
}
