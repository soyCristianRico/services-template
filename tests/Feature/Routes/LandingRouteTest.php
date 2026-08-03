<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Landing;
use App\Models\Location;
use App\Models\Page;

describe('Landing route', function (): void {
    describe('resolution', function (): void {
        it('should return 200 for an active service-only landing', function (): void {
            $service = Category::factory()->create(['slug' => 'alquiler-generadores', 'name' => 'Alquiler de generadores']);
            Landing::factory()->forCategory($service)->create();

            $this->get('/alquiler-generadores')->assertOk()->assertSee('Alquiler de generadores');
        });

        it('should return 200 for an active service+city landing', function (): void {
            $service = Category::factory()->create(['slug' => 'alquiler-generadores', 'name' => 'Alquiler de generadores']);
            $location = Location::factory()->create(['slug' => 'madrid', 'name' => 'Madrid']);
            Landing::factory()->forCategory($service)->inLocation($location)->create();

            $this->get('/alquiler-generadores-madrid')->assertOk()->assertSee('Madrid');
        });

        it('should 404 when no landing exists for the slug', function (): void {
            Category::factory()->create(['slug' => 'alquiler-generadores']);
            // No Landing row created for this combination.

            $this->get('/alquiler-generadores-bilbao')->assertNotFound();
        });

        it('should 404 when the landing is a draft', function (): void {
            $service = Category::factory()->create(['slug' => 'alquiler-generadores']);
            Landing::factory()->forCategory($service)->draft()->create();

            $this->get('/alquiler-generadores')->assertNotFound();
        });

        it('should 404 when the landing is scheduled but not yet published', function (): void {
            $service = Category::factory()->create(['slug' => 'alquiler-generadores']);
            Landing::factory()->forCategory($service)->scheduled(now()->subDay())->create();

            $this->get('/alquiler-generadores')->assertNotFound();
        });
    });

    describe('reserved routes', function (): void {
        it('should still serve / as the home page even with a landing named home', function (): void {
            $service = Category::factory()->create(['slug' => 'home']);
            Landing::factory()->forCategory($service)->create();

            $this->get('/')->assertOk()->assertSee('Entrar al admin');
        });

        it('should still serve /login as Fortify even with a landing slug login', function (): void {
            $service = Category::factory()->create(['slug' => 'login']);
            Landing::factory()->forCategory($service)->create();

            $this->get('/login')->assertOk()->assertSee('Iniciar sesión');
        });
    });

    describe('breadcrumbs from the service tree', function (): void {
        it('should render service ancestors as breadcrumbs', function (): void {
            $parent = Category::factory()->create(['name' => 'Alquiler', 'slug' => 'alquiler']);
            $child = Category::factory()->childOf($parent)->create(['name' => 'Alquiler de generadores', 'slug' => 'alquiler-generadores']);
            Landing::factory()->forCategory($child)->create();

            $this->get('/alquiler-generadores')
                ->assertOk()
                ->assertSeeInOrder(['Inicio', 'Alquiler', 'Alquiler de generadores']);
        });
    });

    describe('SEO output', function (): void {
        it('should emit canonical, robots index,follow and JSON-LD Service node', function (): void {
            $service = Category::factory()->create(['slug' => 'alquiler-generadores', 'name' => 'Alquiler de generadores']);
            $location = Location::factory()->create(['slug' => 'madrid', 'name' => 'Madrid']);
            Landing::factory()->forCategory($service)->inLocation($location)->create(['title' => 'Generadores Madrid 24h']);

            $response = $this->get('/alquiler-generadores-madrid')->assertOk();

            $response->assertSee('<link rel="canonical" href="'.url('/alquiler-generadores-madrid').'"', false);
            $response->assertSee('<meta name="robots" content="index, follow', false);
            $response->assertSee('"@type":"Service"', false);
            $response->assertSee('"@type":"City","name":"Madrid"', false);
            $response->assertSee('"@type":"BreadcrumbList"', false);
            $response->assertSee('<title>Generadores Madrid 24h', false);
        });

        it('should fall back to a generated title when the landing has none', function (): void {
            $service = Category::factory()->create(['slug' => 'alquiler-generadores', 'name' => 'Alquiler de generadores']);
            $location = Location::factory()->create(['slug' => 'madrid', 'name' => 'Madrid']);
            Landing::factory()->forCategory($service)->inLocation($location)->create(['title' => null]);

            $this->get('/alquiler-generadores-madrid')
                ->assertOk()
                ->assertSee('<title>Alquiler de generadores en Madrid', false);
        });
    });

    describe('Page resolution', function (): void {
        it('should serve a Page when one matches the slug', function (): void {
            Page::factory()->create([
                'slug' => 'aviso-legal',
                'title' => 'Aviso legal',
                'body' => '<p>Texto del aviso legal.</p>',
            ]);

            $this->get('/aviso-legal')
                ->assertOk()
                ->assertSee('Aviso legal')
                ->assertSee('Texto del aviso legal.', false);
        });

        it('should 404 when the Page is inactive and no Landing matches', function (): void {
            Page::factory()->inactive()->create(['slug' => 'aviso-legal']);

            $this->get('/aviso-legal')->assertNotFound();
        });

        it('should prefer Page over Landing when both share a slug', function (): void {
            $service = Category::factory()->create(['slug' => 'gracias']);
            Landing::factory()->forCategory($service)->create();
            Page::factory()->create([
                'slug' => 'gracias',
                'title' => 'Gracias por tu solicitud',
                'body' => 'Contenido único de la página de gracias.',
            ]);

            $this->get('/gracias')
                ->assertOk()
                ->assertSee('Gracias por tu solicitud')
                ->assertSee('Contenido único de la página de gracias.');
        });
    });
});
