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

describe('Admin\\Leads\\Show', function () {
    describe('rendering', function () {
        it('should show where the visit came from', function () {
            $lead = Lead::factory()->create([
                'channel' => LeadChannel::Ads,
                'utm_source' => 'google',
                'utm_medium' => 'cpc',
                'utm_campaign' => 'verano-2026',
                'referrer' => 'https://www.google.es/',
            ]);

            Livewire::test('pages::admin.leads.show', ['lead' => $lead])
                ->assertSee('Atribución')
                ->assertSee('Publicidad')
                ->assertSee('verano-2026');
        });

        /**
         * Un lead nacido fuera de una visita no tiene nada que enseñar aquí, y
         * una tarjeta con todo a «—» sólo estorba.
         */
        it('should leave the card out when there was no visit behind the lead', function () {
            $lead = Lead::factory()->create(['channel' => null]);

            Livewire::test('pages::admin.leads.show', ['lead' => $lead])
                ->assertDontSee('Atribución');
        });

        it('should render the lead details', function () {
            $lead = Lead::factory()->create([
                'name' => 'Cristian',
                'email' => 'cristian@example.com',
                'phone' => '600000000',
                'message' => 'Necesito un generador',
            ]);

            Livewire::test('pages::admin.leads.show', ['lead' => $lead])
                ->assertOk()
                ->assertSee('Cristian')
                ->assertSee('cristian@example.com')
                ->assertSee('600000000')
                ->assertSee('Necesito un generador');
        });

        it('should render the landing card when the lead came from one', function () {
            $landing = Landing::factory()->create();
            $lead = Lead::factory()->fromLanding($landing)->create();

            Livewire::test('pages::admin.leads.show', ['lead' => $lead])
                ->assertSee('Landing de origen');
        });

        it('should NOT render the landing card when the lead has no landing', function () {
            $lead = Lead::factory()->create();

            Livewire::test('pages::admin.leads.show', ['lead' => $lead])
                ->assertDontSee('Landing de origen');
        });

        it('should render the payload when present', function () {
            $lead = Lead::factory()->create([
                'payload' => ['company' => 'Acme'],
            ]);

            Livewire::test('pages::admin.leads.show', ['lead' => $lead])
                ->assertSee('Datos extra')
                ->assertSee('Acme');
        });
    });

    describe('updateStatus', function () {
        it('should update the lead status', function () {
            $lead = Lead::factory()->create();

            Livewire::test('pages::admin.leads.show', ['lead' => $lead])
                ->set('status', LeadStatus::Qualified->value)
                ->call('updateStatus');

            expect($lead->refresh()->status)->toBe(LeadStatus::Qualified);
        });

        it('should reject invalid status values', function () {
            $lead = Lead::factory()->create();

            Livewire::test('pages::admin.leads.show', ['lead' => $lead])
                ->set('status', 'not-a-status')
                ->call('updateStatus')
                ->assertHasErrors(['status']);

            expect($lead->refresh()->status)->toBe(LeadStatus::New);
        });
    });

    describe('deleteLead', function () {
        it('should delete the lead and redirect to the index', function () {
            $lead = Lead::factory()->create();

            Livewire::test('pages::admin.leads.show', ['lead' => $lead])
                ->call('deleteLead')
                ->assertRedirect(route('admin.leads.index'));

            expect(Lead::find($lead->id))->toBeNull();
        });
    });
});
