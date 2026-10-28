<?php

declare(strict_types=1);

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\Service;
use App\Models\User;
use Livewire\Livewire;

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

    describe('unansweredLeads', function (): void {
        it('should list the contacts nobody has answered, the oldest first', function (): void {
            Lead::factory()->create(['name' => 'Reciente']);
            Lead::factory()->create(['name' => 'La que espera', 'created_at' => now()->subDays(5)]);
            Lead::factory()->ofStatus(LeadStatus::Contacted)->create(['name' => 'Respondida']);

            $names = Livewire::test('pages::admin.dashboard')->get('unansweredLeads')->pluck('name')->all();

            expect($names)->toBe(['La que espera', 'Reciente']);
        });
    });

    describe('leadsPerWeek', function (): void {
        it('should chart the contacts of the last twelve weeks', function (): void {
            Lead::factory()->count(2)->create();

            $this->get('/admin')->assertSee('Contactos que entran, por semana');

            expect(Livewire::test('pages::admin.dashboard')->get('leadsPerWeek'))->toHaveCount(12);
        });
    });
});
