<?php

declare(strict_types=1);

use App\Models\Lead;
use App\Models\Service;
use App\Models\User;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create(['name' => 'Ana']));
});

describe('Admin\\Dashboard', function (): void {
    describe('render', function (): void {
        it('should greet and summarise the site', function (): void {
            $this->get('/admin')
                ->assertOk()
                ->assertSee('Hola, Ana')
                ->assertSee('Contactos sin responder')
                ->assertSee('Entradas publicadas')
                ->assertDontSee('services-template');
        });

        it('should count the contacts still waiting for an answer', function (): void {
            Lead::factory()->count(2)->create();

            $this->get('/admin')->assertSee('Esperan respuesta');
        });

        it('should list the services with no photo', function (): void {
            Service::factory()->create(['name' => 'Generador 20 kVA']);

            $this->get('/admin')
                ->assertSee('1 sin foto')
                ->assertSee('Generador 20 kVA');
        });

        it('should leave the catalog out on a site without one', function (): void {
            config()->set('site.catalog', false);

            $this->get('/admin')
                ->assertOk()
                ->assertDontSee('Servicios activos');
        });

        it('should send a guest to the login', function (): void {
            auth()->logout();

            $this->get('/admin')->assertRedirect(route('login'));
        });
    });
});
