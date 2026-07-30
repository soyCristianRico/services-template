<?php

declare(strict_types=1);

use App\Mcp\Servers\ServicesServer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/**
 * The geographic dimension is a switch, not a fork of the codebase: every file
 * stays identical to the template so `git cherry-pick` keeps working.
 */
describe('site.locations', function () {
    describe('enabled', function () {
        it('should register the public landing route', function () {
            expect(Route::has('landing'))->toBeTrue();
        });

        it('should register the admin routes for locations and landings', function () {
            expect(Route::has('admin.locations.index'))->toBeTrue()
                ->and(Route::has('admin.landings.index'))->toBeTrue();
        });

        it('should show both entries in the admin menu', function () {
            // The nav is what is under test, not asset compilation: this has to
            // pass in a fresh checkout with no `npm run build`.
            $this->withoutVite()
                ->actingAs(User::factory()->create())
                ->get('/admin')
                ->assertOk()
                ->assertSee('Ubicaciones')
                ->assertSee('Landings');
        });

        it('should offer the geographic MCP tools', function () {
            expect(mcpToolNames())->toContain('list-landings-tool')->toContain('list-locations-tool');
        });
    });

    describe('disabled', function () {
        it('should not register the public landing catch-all', function () {
            expect(routesWithoutLocations())->not->toContain('landing');
        });

        it('should not register the admin routes for locations and landings', function () {
            $names = routesWithoutLocations();

            expect($names)->not->toContain('admin.locations.index')
                ->and($names)->not->toContain('admin.landings.index');
        });

        it('should drop the landings sub-sitemap route', function () {
            // A landing URL in a sitemap would 404: `/{slug}` is not registered.
            expect(routesWithoutLocations())->not->toContain('sitemap.landings');
        });

        it('should not advertise the landings sub-sitemap in the sitemap index', function () {
            config()->set('site.locations', false);

            $response = $this->get('/sitemap.xml')->assertOk();

            expect($response->getContent())->not->toContain('/sitemap-landings.xml');
        });

        it('should hide both entries from the admin menu', function () {
            config()->set('site.locations', false);

            $this->withoutVite()
                ->actingAs(User::factory()->create())
                ->get('/admin')
                ->assertOk()
                ->assertDontSee('Ubicaciones')
                ->assertDontSee('Landings');
        });

        it('should not offer the geographic MCP tools', function () {
            config()->set('site.locations', false);

            expect(mcpToolNames())
                ->not->toContain('list-landings-tool')
                ->not->toContain('list-locations-tool');
        });

        it('should keep every non-geographic tool', function () {
            config()->set('site.locations', false);

            expect(mcpToolNames())
                ->toContain('list-services-tool')
                ->toContain('list-categories-tool')
                ->toContain('list-blog-posts-tool');
        });
    });
});

/**
 * Route files are loaded once per process, so toggling the config afterwards
 * cannot un-register them. Re-run the route file under the flag instead.
 *
 * @return list<string>
 */
function routesWithoutLocations(): array
{
    config()->set('site.locations', false);

    $router = app('router');
    $original = $router->getRoutes();

    $router->setRoutes(new RouteCollection);
    require base_path('routes/web.php');
    $names = array_keys($router->getRoutes()->getRoutesByName());

    $router->setRoutes($original);

    return $names;
}

/**
 * @return list<string>
 */
function mcpToolNames(): array
{
    // The server only needs a Transport to be constructed, and `boot()` — where
    // the filtering lives — is a no-op upstream, so an instance without the
    // constructor is enough to read the registered list.
    $reflection = new ReflectionClass(ServicesServer::class);
    $server = $reflection->newInstanceWithoutConstructor();

    $reflection->getMethod('boot')->invoke($server);

    return array_map(
        fn (string $tool): string => app($tool)->name(),
        $reflection->getProperty('tools')->getValue($server),
    );
}
