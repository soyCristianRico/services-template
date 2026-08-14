<?php

declare(strict_types=1);

use App\Enums\LeadChannel;
use App\Models\Landing;
use App\Models\Lead;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
    Bus::fake();
});

describe('LeadForm', function () {
    describe('rendering', function () {
        it('should render the form with empty fields by default', function () {
            Livewire::test('lead-form')
                ->assertSee('Pide presupuesto sin compromiso')
                ->assertSet('form.name', '')
                ->assertSet('form.email', '')
                ->assertSet('submitted', false);
        });
    });

    describe('validation', function () {
        it('should require name and email', function () {
            Livewire::test('lead-form')
                ->call('save')
                ->assertHasErrors(['form.name' => 'required', 'form.email' => 'required'])
                ->assertSet('submitted', false);

            expect(Lead::count())->toBe(0);
        });

        it('should reject invalid emails', function () {
            Livewire::test('lead-form')
                ->set('form.name', 'Cristian')
                ->set('form.email', 'not-an-email')
                ->call('save')
                ->assertHasErrors(['form.email']);

            expect(Lead::count())->toBe(0);
        });
    });

    describe('happy path', function () {
        it('should capture a Lead and switch to the success state', function () {
            Livewire::test('lead-form')
                ->set('form.name', 'Cristian')
                ->set('form.email', 'cristian@example.com')
                ->set('form.phone', '600000000')
                ->set('form.message', 'Necesito un generador.')
                ->call('save')
                ->assertHasNoErrors()
                ->assertSet('submitted', true)
                ->assertSee('Te llamamos en 15 minutos');

            expect(Lead::count())->toBe(1);
            expect(Lead::first()->name)->toBe('Cristian');
        });

        it('should attach the Landing context when mounted with one', function () {
            $landing = Landing::factory()->create();

            Livewire::test('lead-form', ['landing' => $landing])
                ->set('form.name', 'Cristian')
                ->set('form.email', 'cristian@example.com')
                ->call('save')
                ->assertHasNoErrors();

            $lead = Lead::first();
            expect($lead->landing_id)->toBe($landing->id);
            expect($lead->source_url)->toBe(url('/'.$landing->slug));
        });

        it('should leave landing_id null when mounted without a Landing', function () {
            Livewire::test('lead-form')
                ->set('form.name', 'Cristian')
                ->set('form.email', 'cristian@example.com')
                ->call('save');

            expect(Lead::first()->landing_id)->toBeNull();
        });
    });

    describe('attribution', function () {
        /**
         * La cadena entera, que es donde se rompería sin que nadie lo notara:
         * la visita llega con UTMs, navega, escribe, y el lead guardado sabe de
         * dónde vino. El formulario no toca nada de esto —lo trae la sesión que
         * anotó la primera página—, así que si un día deja de funcionar no será
         * porque alguien se olvidara de pasarlo.
         */
        it('should save the lead knowing how that visit reached the site', function () {
            $this->get('/?utm_source=instagram&utm_medium=cpc&utm_campaign=verano')
                ->assertSuccessful();

            Livewire::test('lead-form')
                ->set('form.name', 'Cristian')
                ->set('form.email', 'cristian@example.com')
                ->call('save')
                ->assertHasNoErrors();

            $lead = Lead::first();

            expect($lead->channel)->toBe(LeadChannel::Ads)
                ->and($lead->utm_source)->toBe('instagram')
                ->and($lead->utm_campaign)->toBe('verano');
        });
    });

    describe('honeypot', function () {
        it('should pretend success without creating a Lead when the honeypot is filled', function () {
            Livewire::test('lead-form')
                ->set('form.name', 'Cristian')
                ->set('form.email', 'cristian@example.com')
                ->set('form.website', 'http://spammer.example')
                ->call('save')
                ->assertHasNoErrors()
                ->assertSet('submitted', true);

            expect(Lead::count())->toBe(0);
        });
    });
});
