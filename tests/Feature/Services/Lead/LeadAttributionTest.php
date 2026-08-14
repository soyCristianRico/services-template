<?php

declare(strict_types=1);

use App\Enums\LeadChannel;
use App\Services\Lead\LeadAttribution;
use Illuminate\Http\Request;

/**
 * Una visita que llega con estos parámetros y este referrer.
 *
 * @param  array<string, string>  $query
 */
function arriving(array $query = [], ?string $referrer = null): LeadAttribution
{
    $request = Request::create('https://example.test/oposiciones/celador', 'GET', $query);

    if ($referrer !== null) {
        $request->headers->set('referer', $referrer);
    }

    return LeadAttribution::fromRequest($request);
}

describe('LeadAttribution', function (): void {
    describe('fromRequest', function (): void {
        it('should read the channel from whatever the visit brought', function (array $query, ?string $referrer, LeadChannel $expected): void {
            expect(arriving($query, $referrer)->channel)->toBe($expected);
        })->with([
            // Auto-tagging: Google Ads NO pone UTMs, sólo el gclid. Sin esta
            // regla toda la campaña de pago se contaría como directa.
            'gclid alone' => [['gclid' => 'Cj0KCQ'], null, LeadChannel::Ads],
            'msclkid alone' => [['msclkid' => 'abc123'], null, LeadChannel::Ads],
            'utm cpc' => [['utm_source' => 'google', 'utm_medium' => 'cpc'], null, LeadChannel::Ads],
            'utm CPC uppercase' => [['utm_medium' => 'CPC'], null, LeadChannel::Ads],

            // Un anuncio en Instagram trae fbclid Y referrer de instagram.com.
            // Si mandara el referrer, el gasto en redes desaparecería del mapa.
            'paid social beats its own referrer' => [['fbclid' => 'IwAR'], 'https://l.instagram.com/', LeadChannel::Ads],

            'newsletter' => [['utm_source' => 'newsletter', 'utm_medium' => 'email'], null, LeadChannel::Email],
            'utm social' => [['utm_medium' => 'social'], null, LeadChannel::Social],

            'google referrer' => [[], 'https://www.google.es/search?q=oposiciones', LeadChannel::Organic],
            'google.co.uk referrer' => [[], 'https://www.google.co.uk/', LeadChannel::Organic],
            'bing referrer' => [[], 'https://www.bing.com/', LeadChannel::Organic],
            'facebook referrer' => [[], 'https://m.facebook.com/', LeadChannel::Social],
            'the t.co of a shared link' => [[], 'https://t.co/abc', LeadChannel::Social],

            'another site linking here' => [[], 'https://elperiodicodearagon.com/nota', LeadChannel::Referral],
            'tagged with nothing recognisable' => [['utm_source' => 'flyer'], null, LeadChannel::Referral],

            'nothing at all' => [[], null, LeadChannel::Direct],
        ]);

        /**
         * Un enlace interno no es un origen. Aparece cuando la cookie de sesión
         * se cae a mitad de visita, y contarlo convertiría a la propia web en
         * su mayor fuente de referencias.
         */
        it('should not treat itself as a source', function (): void {
            $attribution = arriving([], 'https://example.test/blog/algo');

            expect($attribution->channel)->toBe(LeadChannel::Direct)
                ->and($attribution->referrer)->toBeNull();
        });

        /**
         * La URL de entrada CON su query string, que es lo que hoy no se sabe:
         * `source_url` guarda el formulario, no por dónde entró.
         */
        it('should keep the landing page with its query string', function (): void {
            $attribution = arriving(['utm_source' => 'newsletter']);

            expect($attribution->landingUrl)
                ->toBe('https://example.test/oposiciones/celador?utm_source=newsletter');
        });

        it('should keep the tags it was given', function (): void {
            $attribution = arriving([
                'utm_source' => 'instagram',
                'utm_medium' => 'social',
                'utm_campaign' => 'celador-2026',
                'utm_term' => 'oposiciones celador',
                'utm_content' => 'story-1',
            ]);

            expect($attribution->toLeadAttributes())->toMatchArray([
                'channel' => 'social',
                'utm_source' => 'instagram',
                'utm_medium' => 'social',
                'utm_campaign' => 'celador-2026',
                'utm_term' => 'oposiciones celador',
                'utm_content' => 'story-1',
            ]);
        });

        /**
         * Las columnas tienen tamaño y una URL no. Un valor que no cabe se
         * corta aquí, y no en un error de inserción con el lead ya perdido.
         */
        it('should cut what does not fit in its column', function (): void {
            $attribution = arriving(['utm_source' => str_repeat('a', 400)]);

            expect(mb_strlen((string) $attribution->utmSource))->toBe(120);
        });

        it('should ignore a parameter that came empty', function (): void {
            expect(arriving(['utm_source' => '', 'utm_medium' => '   '])->channel)
                ->toBe(LeadChannel::Direct);
        });
    });

    describe('remember', function (): void {
        /**
         * Gana la primera y no la última: lo que trajo a alguien es la página
         * por la que entró, no la última que miró antes de escribir.
         */
        it('should keep the first touch of the visit', function (): void {
            arriving(['utm_source' => 'newsletter', 'utm_medium' => 'email'])->remember();
            arriving([], 'https://www.google.es/')->remember();

            expect(LeadAttribution::fromSession()?->channel)->toBe(LeadChannel::Email);
        });

        it('should read back everything it stored', function (): void {
            arriving(['gclid' => 'Cj0KCQ', 'utm_campaign' => 'celador'])->remember();

            $restored = LeadAttribution::fromSession();

            expect($restored?->channel)->toBe(LeadChannel::Ads)
                ->and($restored?->clickId)->toBe('Cj0KCQ')
                ->and($restored?->utmCampaign)->toBe('celador');
        });

        it('should return nothing when the visit was never seen', function (): void {
            expect(LeadAttribution::fromSession())->toBeNull();
        });
    });
});
