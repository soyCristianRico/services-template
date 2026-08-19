<?php

declare(strict_types=1);

use App\Enums\RedirectMatchType;
use App\Enums\RedirectStatusCode;
use App\Mcp\Servers\ServicesServer;
use App\Mcp\Tools\Seo\CreateRedirectTool;
use App\Mcp\Tools\Seo\ListRedirectsTool;
use App\Mcp\Tools\Seo\UpdateRedirectTool;
use App\Models\Redirect;

describe('ServicesServer', function (): void {
    describe('Redirects', function (): void {
        describe('list-redirects', function (): void {
            it('should list redirects with what they do and how much they are used', function (): void {
                Redirect::factory()->create([
                    'source' => '/pagina-antigua',
                    'destination' => '/pagina-nueva',
                    'hits' => 42,
                ]);

                ServicesServer::tool(ListRedirectsTool::class, [])
                    ->assertOk()
                    ->assertSee('/pagina-antigua')
                    ->assertSee('/pagina-nueva')
                    ->assertSee('"hits":42')
                    ->assertSee('"count":1');
            });

            it('should find a redirect from a full URL pasted as the search', function (): void {
                Redirect::factory()->create(['source' => '/direccion-vieja', 'destination' => '/destino-final']);
                Redirect::factory()->create(['source' => '/otra-direccion', 'destination' => '/otro-destino']);

                ServicesServer::tool(ListRedirectsTool::class, [
                    'search' => 'https://example.com/Direccion-Vieja/',
                ])
                    ->assertOk()
                    ->assertSee('/direccion-vieja')
                    ->assertSee('"count":1');
            });

            it('should filter by active state', function (): void {
                Redirect::factory()->create(['source' => '/viva']);
                Redirect::factory()->inactive()->create(['source' => '/retirada']);

                ServicesServer::tool(ListRedirectsTool::class, ['is_active' => false])
                    ->assertOk()
                    ->assertSee('/retirada')
                    ->assertSee('"count":1');
            });
        });

        describe('create-redirect', function (): void {
            it('should create a redirect with the usual defaults', function (): void {
                ServicesServer::tool(CreateRedirectTool::class, [
                    'source' => '/pagina-antigua',
                    'destination' => '/pagina-nueva',
                ])
                    ->assertOk()
                    ->assertSee('/pagina-nueva');

                $redirect = Redirect::query()->where('source', '/pagina-antigua')->first();

                expect($redirect)->not->toBeNull()
                    ->and($redirect->destination)->toBe('/pagina-nueva')
                    ->and($redirect->match_type)->toBe(RedirectMatchType::Exact)
                    ->and($redirect->status_code)->toBe(RedirectStatusCode::MovedPermanently)
                    ->and($redirect->is_active)->toBeTrue()
                    ->and($redirect->preserve_query)->toBeTrue();
            });

            it('should reduce a pasted full URL to the address it will compare', function (): void {
                ServicesServer::tool(CreateRedirectTool::class, [
                    'source' => 'https://example.com/Direccion-Vieja/',
                    'destination' => '/destino-final',
                ])->assertOk();

                expect(Redirect::query()->where('source', '/direccion-vieja')->exists())->toBeTrue();
            });

            it('should drop the destination of a page retired without a replacement', function (): void {
                ServicesServer::tool(CreateRedirectTool::class, [
                    'source' => '/seccion-cerrada',
                    'destination' => '/algo',
                    'status_code' => RedirectStatusCode::Gone->value,
                ])->assertOk();

                expect(Redirect::query()->where('source', '/seccion-cerrada')->first()->destination)->toBeNull();
            });

            it('should refuse a redirect that has no destination and is not a 410', function (): void {
                ServicesServer::tool(CreateRedirectTool::class, ['source' => '/sin-destino'])
                    ->assertHasErrors();

                expect(Redirect::query()->count())->toBe(0);
            });

            it('should refuse to chain onto an address that already redirects', function (): void {
                Redirect::factory()->create(['source' => '/pagina-nueva', 'destination' => '/destino-final']);

                ServicesServer::tool(CreateRedirectTool::class, [
                    'source' => '/pagina-antigua',
                    'destination' => '/pagina-nueva',
                ])->assertHasErrors();

                expect(Redirect::query()->where('source', '/pagina-antigua')->exists())->toBeFalse();
            });

            it('should refuse a redirect that points at itself', function (): void {
                ServicesServer::tool(CreateRedirectTool::class, [
                    'source' => '/pagina-nueva',
                    'destination' => '/pagina-nueva/',
                ])->assertHasErrors();

                expect(Redirect::query()->count())->toBe(0);
            });

            it('should refuse to redirect the panel', function (): void {
                ServicesServer::tool(CreateRedirectTool::class, [
                    'source' => '/admin/redirects',
                    'destination' => '/pagina-nueva',
                ])->assertHasErrors();

                expect(Redirect::query()->count())->toBe(0);
            });

            it('should refuse a prefix that would swallow the whole site', function (): void {
                ServicesServer::tool(CreateRedirectTool::class, [
                    'source' => '/',
                    'destination' => '/pagina-nueva',
                    'match_type' => RedirectMatchType::Prefix->value,
                ])->assertHasErrors();

                expect(Redirect::query()->count())->toBe(0);
            });

            it('should refuse a source that is already redirected', function (): void {
                Redirect::factory()->create(['source' => '/pagina-antigua', 'destination' => '/pagina-nueva']);

                ServicesServer::tool(CreateRedirectTool::class, [
                    'source' => '/pagina-antigua',
                    'destination' => '/otro-destino',
                ])->assertHasErrors();

                expect(Redirect::query()->where('source', '/pagina-antigua')->count())->toBe(1);
            });

            it('should keep a regex pattern raw instead of normalising it', function (): void {
                ServicesServer::tool(CreateRedirectTool::class, [
                    'source' => '^/curso/(.+)$',
                    'destination' => '/aprende/$1',
                    'match_type' => RedirectMatchType::Regex->value,
                ])->assertOk();

                expect(Redirect::query()->first()->source)->toBe('^/curso/(.+)$');
            });

            it('should refuse a regular expression that does not compile', function (): void {
                ServicesServer::tool(CreateRedirectTool::class, [
                    'source' => '^/curso/((.+$',
                    'destination' => '/otro-destino',
                    'match_type' => RedirectMatchType::Regex->value,
                ])->assertHasErrors();

                expect(Redirect::query()->count())->toBe(0);
            });
        });

        describe('update-redirect', function (): void {
            it('should change only what was sent', function (): void {
                $redirect = Redirect::factory()->create([
                    'source' => '/pagina-antigua',
                    'destination' => '/pagina-nueva',
                    'notes' => 'Migración de 2019.',
                ]);

                ServicesServer::tool(UpdateRedirectTool::class, [
                    'id' => $redirect->id,
                    'destination' => '/otro-destino',
                ])->assertOk();

                $redirect->refresh();

                expect($redirect->destination)->toBe('/otro-destino')
                    ->and($redirect->source)->toBe('/pagina-antigua')
                    ->and($redirect->notes)->toBe('Migración de 2019.');
            });

            it('should find the redirect by its source when no id is given', function (): void {
                Redirect::factory()->create(['source' => '/pagina-antigua', 'destination' => '/pagina-nueva']);

                ServicesServer::tool(UpdateRedirectTool::class, [
                    'source_lookup' => 'https://example.com/Pagina-Antigua/',
                    'is_active' => false,
                ])->assertOk();

                expect(Redirect::query()->where('source', '/pagina-antigua')->first()->is_active)->toBeFalse();
            });

            it('should report a redirect it cannot find', function (): void {
                ServicesServer::tool(UpdateRedirectTool::class, ['source_lookup' => '/no-existe'])
                    ->assertHasErrors();
            });

            it('should not trip over its own source when revalidating', function (): void {
                $redirect = Redirect::factory()->create(['source' => '/pagina-antigua', 'destination' => '/pagina-nueva']);

                ServicesServer::tool(UpdateRedirectTool::class, [
                    'id' => $redirect->id,
                    'notes' => 'Sigue viva.',
                ])->assertOk();

                expect($redirect->refresh()->notes)->toBe('Sigue viva.');
            });

            it('should drop the destination when the page is retired', function (): void {
                $redirect = Redirect::factory()->create(['source' => '/pagina-antigua', 'destination' => '/pagina-nueva']);

                ServicesServer::tool(UpdateRedirectTool::class, [
                    'id' => $redirect->id,
                    'status_code' => RedirectStatusCode::Gone->value,
                ])->assertOk();

                expect($redirect->refresh()->destination)->toBeNull();
            });

            it('should refuse a change that would chain onto another redirect', function (): void {
                Redirect::factory()->create(['source' => '/pagina-nueva', 'destination' => '/destino-final']);
                $redirect = Redirect::factory()->create(['source' => '/pagina-antigua', 'destination' => '/otro-destino']);

                ServicesServer::tool(UpdateRedirectTool::class, [
                    'id' => $redirect->id,
                    'destination' => '/pagina-nueva',
                ])->assertHasErrors();

                expect($redirect->refresh()->destination)->toBe('/otro-destino');
            });
        });
    });
});
