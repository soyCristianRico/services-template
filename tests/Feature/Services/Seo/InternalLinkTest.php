<?php

declare(strict_types=1);

use App\Services\Seo\InternalLink;

beforeEach(function (): void {
    config()->set('app.url', 'https://ejemplo.com');
});

describe('InternalLink', function (): void {
    describe('normalize', function (): void {
        it('should reduce every way of writing an address of this site to its canonical path', function (string $input): void {
            expect(InternalLink::forSite()->normalize($input))->toBe('/servicios/reformas');
        })->with([
            'trailing slash' => 'https://ejemplo.com/servicios/reformas/',
            'no trailing slash' => 'https://ejemplo.com/servicios/reformas',
            'www' => 'https://www.ejemplo.com/servicios/reformas/',
            'http' => 'http://ejemplo.com/servicios/reformas/',
            'protocol relative' => '//ejemplo.com/servicios/reformas/',
            'already relative' => '/servicios/reformas/',
            'upper case host' => 'https://EJEMPLO.COM/servicios/reformas/',
            'surrounding blanks' => '  /servicios/reformas/  ',
        ]);

        it('should keep the query and the fragment on the far side of the slash', function (): void {
            $links = InternalLink::forSite();

            expect($links->normalize('/servicios/?zona=centro'))->toBe('/servicios?zona=centro')
                ->and($links->normalize('https://ejemplo.com/faqs/#plazos'))->toBe('/faqs#plazos')
                ->and($links->normalize('/blog/?a=1&amp;b=2'))->toBe('/blog?a=1&amp;b=2');
        });

        it('should leave the home page as it is', function (): void {
            $links = InternalLink::forSite();

            expect($links->normalize('/'))->toBe('/')
                ->and($links->normalize('https://ejemplo.com/'))->toBe('/')
                ->and($links->normalize('https://ejemplo.com'))->toBe('/');
        });

        it('should not touch an address that belongs to somebody else', function (string $input): void {
            expect(InternalLink::forSite()->normalize($input))->toBe($input);
        })->with([
            // A subdomain is another system entirely. Turned into a relative path
            // it would point at a page of this site that does not exist.
            'own subdomain' => 'https://tienda.ejemplo.com/',
            'another subdomain' => 'https://campus.ejemplo.com/acceso.php',
            'foreign host' => 'https://boe.es/boletines/',
            'host that merely ends the same' => 'https://notejemplo.com/faqs/',
            'protocol relative foreign' => '//boe.es/boletines/',
            'mailto' => 'mailto:hola@ejemplo.com',
            'tel' => 'tel:900000000',
            'anchor' => '#plazos',
            'empty' => '',
            // Resolved against whatever page it sits on, so rewriting it is guesswork.
            'bare relative segment' => 'contacto/',
        ]);

        it('should be safe to run twice', function (): void {
            $links = InternalLink::forSite();
            $once = $links->normalize('https://ejemplo.com/blog/una-entrada/');

            expect($links->normalize($once))->toBe($once);
        });

        it('should treat a host given by hand as this site too', function (): void {
            config()->set('app.url', 'http://localhost');

            $links = InternalLink::forSite(['https://ejemplo.com']);

            expect($links->normalize('https://ejemplo.com/faqs/'))->toBe('/faqs')
                ->and($links->normalize('https://www.ejemplo.com/faqs/'))->toBe('/faqs');
        });
    });

    describe('inHtml', function (): void {
        it('should rewrite the links of a body and leave the rest alone', function (): void {
            $body = <<<'HTML'
            <p>Mira <a href="https://ejemplo.com/servicios/reformas/">el servicio</a>
            y el <a href='/blog/una-entrada/'>artículo</a>.</p>
            <p>Normativa en el <a href="https://boe.es/boletines/" rel="nofollow">BOE</a>.</p>
            <img src="/storage/media/foto.jpg" alt="">
            HTML;

            $result = InternalLink::forSite()->inHtml($body);

            expect($result)
                ->toContain('<a href="/servicios/reformas">')
                ->toContain("<a href='/blog/una-entrada'>")
                ->toContain('<a href="https://boe.es/boletines/" rel="nofollow">')
                ->toContain('<img src="/storage/media/foto.jpg" alt="">');
        });

        it('should keep every other attribute of the anchor', function (): void {
            $html = '<a class="btn" href="https://ejemplo.com/contacto/" target="_blank" rel="noopener">Contacto</a>';

            expect(InternalLink::forSite()->inHtml($html))
                ->toBe('<a class="btn" href="/contacto" target="_blank" rel="noopener">Contacto</a>');
        });

        it('should reach an href that is not the first attribute or is oddly spaced', function (): void {
            $html = '<a title="Ver" HREF = "https://ejemplo.com/faqs/">FAQs</a>';

            expect(InternalLink::forSite()->inHtml($html))->toContain('"/faqs"');
        });

        it('should leave markup without links untouched', function (): void {
            $html = '<p>Un párrafo cualquiera.</p>';

            expect(InternalLink::forSite()->inHtml($html))->toBe($html);
        });
    });

    describe('changes', function (): void {
        it('should tell an address that needs rewriting from one that does not', function (): void {
            $links = InternalLink::forSite();

            expect($links->changes('/faqs/'))->toBeTrue()
                ->and($links->changes('/faqs'))->toBeFalse()
                ->and($links->changes('https://boe.es/'))->toBeFalse();
        });
    });
});
