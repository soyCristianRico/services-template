<?php

declare(strict_types=1);

use App\Enums\LeadChannel;
use App\Enums\LeadStatus;
use App\Models\Landing;
use App\Models\Lead;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

describe('Admin\\Leads\\Index', function () {
    describe('rendering', function () {
        it('should render an empty state when no leads exist', function () {
            Livewire::test('pages::admin.leads.index')
                ->assertOk()
                ->assertSee('No hay contactos con esos filtros');
        });

        it('should list leads ordered by most recent first', function () {
            $older = Lead::factory()->create(['email' => 'older@example.com']);
            $older->timestamps = false;
            $older->created_at = now()->subDay();
            $older->save();

            Lead::factory()->create(['email' => 'newer@example.com']);

            $component = Livewire::test('pages::admin.leads.index');
            $first = $component->get('leads')->first();

            expect($first->email)->toBe('newer@example.com');
        });
    });

    describe('filters', function () {
        it('should filter by status', function () {
            Lead::factory()->count(3)->create();
            Lead::factory()->ofStatus(LeadStatus::Contacted)->count(2)->create();

            $leads = Livewire::test('pages::admin.leads.index')
                ->set('statusFilter', LeadStatus::Contacted->value)
                ->get('leads');

            expect($leads)->toHaveCount(2);
        });

        it('should filter by channel', function () {
            Lead::factory()->count(3)->create(['channel' => LeadChannel::Organic]);
            Lead::factory()->count(2)->create(['channel' => LeadChannel::Ads]);

            $leads = Livewire::test('pages::admin.leads.index')
                ->set('channelFilter', LeadChannel::Ads->value)
                ->get('leads');

            expect($leads)->toHaveCount(2);
        });

        /**
         * Sin canal no es un canal más: son los que nacieron fuera de una
         * visita, y son un grupo que se quiere poder mirar aparte.
         */
        it('should filter the ones that never had a visit behind them', function () {
            Lead::factory()->count(3)->create(['channel' => LeadChannel::Direct]);
            Lead::factory()->count(2)->create(['channel' => null]);

            $leads = Livewire::test('pages::admin.leads.index')
                ->set('channelFilter', 'none')
                ->get('leads');

            expect($leads)->toHaveCount(2);
        });

        it('should filter by landing_id', function () {
            $landing = Landing::factory()->create();
            Lead::factory()->fromLanding($landing)->count(2)->create();
            Lead::factory()->count(3)->create();

            $leads = Livewire::test('pages::admin.leads.index')
                ->set('landingId', $landing->id)
                ->get('leads');

            expect($leads)->toHaveCount(2);
        });

        it('should search by email substring', function () {
            Lead::factory()->create(['email' => 'cristian@example.com']);
            Lead::factory()->create(['email' => 'andrea@example.com']);

            $leads = Livewire::test('pages::admin.leads.index')
                ->set('search', 'cristian')
                ->get('leads');

            expect($leads)->toHaveCount(1);
        });

        it('should search by name substring', function () {
            Lead::factory()->create(['name' => 'Cristian Rico']);
            Lead::factory()->create(['name' => 'Andrea Pérez']);

            $leads = Livewire::test('pages::admin.leads.index')
                ->set('search', 'Andrea')
                ->get('leads');

            expect($leads)->toHaveCount(1);
        });
    });

    describe('sort', function (): void {
        it('should sort the status in the order a lead walks through it', function (): void {
            Lead::factory()->ofStatus(LeadStatus::Lost)->create(['name' => 'Perdida']);
            Lead::factory()->create(['name' => 'Nueva']);
            Lead::factory()->ofStatus(LeadStatus::Contacted)->create(['name' => 'Contactada']);

            $names = Livewire::test('pages::admin.leads.index')
                ->call('sort', 'status')
                ->get('leads')->pluck('name')->all();

            expect($names)->toBe(['Nueva', 'Contactada', 'Perdida']);
        });

        it('should sort by name and flip the direction on a second click', function (): void {
            Lead::factory()->create(['name' => 'Beatriz']);
            Lead::factory()->create(['name' => 'Alberto']);

            $component = Livewire::test('pages::admin.leads.index')->call('sort', 'name');
            expect($component->get('leads')->pluck('name')->all())->toBe(['Alberto', 'Beatriz']);

            $component->call('sort', 'name')->assertSet('sortDirection', 'desc');
            expect($component->get('leads')->pluck('name')->all())->toBe(['Beatriz', 'Alberto']);
        });

        it('should ignore a column that cannot be sorted', function (): void {
            Livewire::test('pages::admin.leads.index')
                ->call('sort', 'email')
                ->assertSet('sortBy', '');
        });
    });

    describe('clearFilter', function (): void {
        it('should show each active filter as a chip and clear it', function (): void {
            $landing = Landing::factory()->create(['slug' => 'alquiler-gruas-toledo']);

            Livewire::test('pages::admin.leads.index')
                ->set('landingId', $landing->id)
                ->set('statusFilter', LeadStatus::Contacted->value)
                ->set('channelFilter', 'none')
                ->assertSee('/alquiler-gruas-toledo')
                ->assertSee('Sin canal')
                ->call('clearFilter', 'landingId')
                ->assertSet('landingId', null)
                ->assertSet('statusFilter', LeadStatus::Contacted->value)
                ->call('clearFilter', 'channelFilter')
                ->assertSet('channelFilter', 'all')
                ->call('clearAll')
                ->assertSet('statusFilter', 'all');
        });
    });

    describe('mount', function (): void {
        it('should toast the message left by the screen that redirected here', function (): void {
            session()->flash('status', 'Contacto eliminado.');

            Livewire::test('pages::admin.leads.index')
                ->assertDispatched('toast-show');
        });
    });
});
