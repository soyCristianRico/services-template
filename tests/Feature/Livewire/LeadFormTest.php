<?php

declare(strict_types=1);

use App\Enums\LeadChannel;
use App\Models\Landing;
use App\Models\Lead;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function (): void {
    Mail::fake();
    Bus::fake();
});

describe('LeadForm', function (): void {
    describe('rendering', function (): void {
        it('should render the form with empty fields by default', function (): void {
            Livewire::test('lead-form')
                ->assertSee('Cuéntanos qué necesitas')
                ->assertSet('form.name', '')
                ->assertSet('form.email', '')
                ->assertSet('submitted', false);
        });
    });

    describe('validation', function (): void {
        it('should require name and email', function (): void {
            Livewire::test('lead-form')
                ->call('save')
                ->assertHasErrors(['form.name' => 'required', 'form.email' => 'required'])
                ->assertSet('submitted', false);

            expect(Lead::count())->toBe(0);
        });

        it('should reject invalid emails', function (): void {
            Livewire::test('lead-form')
                ->set('form.name', 'Cristian')
                ->set('form.email', 'not-an-email')
                ->call('save')
                ->assertHasErrors(['form.email']);

            expect(Lead::count())->toBe(0);
        });
    });

    describe('happy path', function (): void {
        it('should capture a Lead and switch to the success state', function (): void {
            Livewire::test('lead-form')
                ->set('form.name', 'Cristian')
                ->set('form.email', 'cristian@example.com')
                ->set('form.phone', '600000000')
                ->set('form.message', 'Necesito un generador.')
                ->call('save')
                ->assertHasNoErrors()
                ->assertSet('submitted', true)
                ->assertSee('Hemos recibido tu consulta');

            expect(Lead::count())->toBe(1);
            expect(Lead::first()->name)->toBe('Cristian');
        });

        it('should attach the Landing context when mounted with one', function (): void {
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

        it('should leave landing_id null when mounted without a Landing', function (): void {
            Livewire::test('lead-form')
                ->set('form.name', 'Cristian')
                ->set('form.email', 'cristian@example.com')
                ->call('save');

            expect(Lead::first()->landing_id)->toBeNull();
        });

        it('should record the page it sits on, not the site root, when mounted without a Landing', function (): void {
            Livewire::test('lead-form')
                ->assertSet('landing', null)
                ->assertSet('sourceUrl', request()->url());
        });

        it('should not let a request rewrite the source URL', function (): void {
            Livewire::test('lead-form')->set('sourceUrl', 'https://example.com');
        })->throws(CannotUpdateLockedPropertyException::class);
    });

    describe('attribution', function (): void {
        /**
         * La cadena entera, que es donde se rompería sin que nadie lo notara:
         * la visita llega con UTMs, navega, escribe, y el lead guardado sabe de
         * dónde vino. El formulario no toca nada de esto —lo trae la sesión que
         * anotó la primera página—, así que si un día deja de funcionar no será
         * porque alguien se olvidara de pasarlo.
         */
        it('should save the lead knowing how that visit reached the site', function (): void {
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

    describe('honeypot', function (): void {
        it('should pretend success without creating a Lead when the honeypot is filled', function (): void {
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

    describe('dataLayer', function (): void {
        it('should push lead_form_submitted with the source URL once the lead is saved', function (): void {
            $component = Livewire::test('lead-form')
                ->set('form.name', 'Cristian')
                ->set('form.email', 'cristian@example.com')
                ->call('save')
                ->assertHasNoErrors();

            $pushed = collect($component->effects['xjs'] ?? [])->pluck('expression')->implode("\n");

            expect($pushed)
                ->toContain('window.dataLayer.push(')
                ->toContain('"event":"lead_form_submitted"')
                ->toContain('"lead_source_url":'.json_encode($component->get('sourceUrl')))
                ->not->toContain('cristian@example.com')
                ->not->toContain('Cristian');
        });

        it('should not push anything when validation fails', function (): void {
            Livewire::test('lead-form')
                ->set('form.name', 'Cristian')
                ->set('form.email', 'not-an-email')
                ->call('save')
                ->assertHasErrors(['form.email'])
                ->assertNoJs();
        });

        it('should not push anything when the honeypot is filled', function (): void {
            Livewire::test('lead-form')
                ->set('form.name', 'Cristian')
                ->set('form.email', 'cristian@example.com')
                ->set('form.website', 'http://spammer.example')
                ->call('save')
                ->assertHasNoErrors()
                ->assertNoJs();
        });
    });
});
