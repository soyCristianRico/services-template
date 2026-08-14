<?php

declare(strict_types=1);

use App\Enums\LeadChannel;
use App\Models\User;
use App\Services\Lead\LeadAttribution;

/**
 * Lo que quedó anotado de la visita en curso.
 *
 * @return array<string, string|null>|null
 */
function storedAttribution(): ?array
{
    $stored = session()->get(LeadAttribution::SESSION_KEY);

    return is_array($stored) ? $stored : null;
}

describe('CaptureLeadAttribution', function () {
    describe('capture', function () {
        /**
         * Tiene que ser en la primera página y no al enviar el formulario: el
         * formulario es Livewire, y en ese POST el referrer es la propia web y
         * las UTMs se quedaron en una URL que ya nadie mira.
         */
        it('should note where the visit came from on the page it landed on', function () {
            $this->get('/?utm_source=newsletter&utm_medium=email&utm_campaign=agosto')
                ->assertSuccessful();

            expect(storedAttribution())->toMatchArray([
                'channel' => LeadChannel::Email->value,
                'utm_source' => 'newsletter',
                'utm_medium' => 'email',
                'utm_campaign' => 'agosto',
            ]);
        });

        it('should note an organic arrival from its referrer', function () {
            $this->get('/', ['referer' => 'https://www.google.es/search?q=algo'])
                ->assertSuccessful();

            expect(storedAttribution()['channel'] ?? null)->toBe(LeadChannel::Organic->value);
        });

        /**
         * El primer toque se escribe UNA vez. Lo que trajo a alguien es la
         * página por la que entró, no la tercera que miró.
         */
        it('should not let a later page overwrite the first one', function () {
            $this->get('/?utm_source=newsletter&utm_medium=email')->assertSuccessful();
            $this->get('/?utm_source=otra&utm_medium=cpc')->assertSuccessful();

            expect(storedAttribution())->toMatchArray([
                'channel' => LeadChannel::Email->value,
                'utm_source' => 'newsletter',
            ]);
        });

        it('should note a visit that arrived with nothing as direct', function () {
            $this->get('/')->assertSuccessful();

            expect(storedAttribution()['channel'] ?? null)->toBe(LeadChannel::Direct->value);
        });
    });

    describe('what_is_not_a_first_touch', function () {
        /**
         * Una descarga, un sitemap o un 404 no son la página por la que entra
         * nadie, y el primer toque sólo se escribe una vez: gastarlo en una de
         * ellas deja la visita entera sin origen.
         */
        it('should ignore a response that is not a page', function (string $url) {
            $this->get($url);

            expect(storedAttribution())->toBeNull();
        })->with([
            'sitemap' => '/sitemap.xml',
            'a 404' => '/esta-direccion-no-existe',
        ]);

        /**
         * Quien mira el panel no es una visita que vaya a convertir, y su
         * primera pantalla no dice nada de dónde vino nadie.
         */
        it('should ignore the panel', function () {
            $this->actingAs(User::factory()->create())
                ->get('/admin')
                ->assertSuccessful();

            expect(storedAttribution())->toBeNull();
        });
    });
});
