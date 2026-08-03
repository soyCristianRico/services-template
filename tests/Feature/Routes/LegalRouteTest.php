<?php

declare(strict_types=1);

use App\Models\Page;

describe('LegalRoute', function (): void {
    describe('show', function (): void {
        it('should serve the legal page with the four anchors', function (): void {
            $this->get('/legal')
                ->assertOk()
                ->assertSee('id="aviso-legal"', false)
                ->assertSee('id="privacidad"', false)
                ->assertSee('id="cookies"', false)
                ->assertSee('id="terminos"', false);
        });

        it('should render the responsible entity from the config', function (): void {
            config()->set('legal.entity.name', 'ACME, S.L.');
            config()->set('legal.entity.address', 'Calle Falsa 123, Springfield');
            config()->set('legal.entity.email', 'legal@acme.test');

            $this->get('/legal')
                ->assertSee('ACME, S.L.')
                ->assertSee('Calle Falsa 123, Springfield')
                ->assertSee('legal@acme.test');
        });

        it('should list every configured processor', function (): void {
            config()->set('legal.processors', [
                [
                    'purpose' => 'Alojamiento',
                    'provider' => 'Proveedor de prueba',
                    'detail' => 'Detalle del encargo.',
                    'url' => 'https://example.test/privacy',
                ],
            ]);

            $this->get('/legal')
                ->assertSee('Proveedor de prueba')
                ->assertSee('Detalle del encargo.')
                ->assertSee('https://example.test/privacy', false);
        });

        // The "we disclose nothing" sentence is only true while `recipients` is
        // empty. A site that wires up a lead webhook has to declare it, and the
        // page must stop making the claim.
        it('should state that nothing is disclosed when there are no recipients', function (): void {
            config()->set('legal.recipients', []);

            $this->get('/legal')
                ->assertSee('no cedemos tus datos a ningún tercero, salvo obligación legal')
                ->assertDontSee('Destinatarios de tus datos');
        });

        it('should list the recipients and drop the claim when there are any', function (): void {
            config()->set('legal.recipients', [
                ['name' => 'CRM de prueba', 'detail' => 'Recibe los formularios enviados.'],
            ]);

            $this->get('/legal')
                ->assertSee('Destinatarios de tus datos')
                ->assertSee('CRM de prueba')
                ->assertSee('Recibe los formularios enviados.')
                ->assertDontSee('Más allá de esos encargados');
        });

        // The cookie banner renders only when GTM is configured, so the cookie
        // policy must not promise analytics cookies a visitor will never get.
        it('should describe the analytics cookies only when GTM is configured', function (): void {
            config()->set('services.google_tag_manager.id', 'GTM-TEST');

            $this->get('/legal')
                ->assertSee('Google Analytics')
                ->assertDontSee('Este sitio no instala ninguna');
        });

        it('should say no third-party cookies are set when GTM is absent', function (): void {
            config()->set('services.google_tag_manager.id', null);

            $this->get('/legal')
                ->assertSee('Este sitio no instala ninguna')
                ->assertDontSee('Google Analytics');
        });

        it('should submit to the configured jurisdiction', function (): void {
            config()->set('legal.jurisdiction', 'Tarragona');

            $this->get('/legal')->assertSee('tribunales de Tarragona');
        });

        // The route is declared before the catch-all, so a CMS Page slugged
        // "legal" must never shadow the real legal text.
        it('should win over a CMS page with the same slug', function (): void {
            Page::factory()->create([
                'slug' => 'legal',
                'title' => 'Página del CMS',
                'body' => '<p>No debería verse.</p>',
            ]);

            $this->get('/legal')
                ->assertOk()
                ->assertSee('Aviso legal')
                ->assertDontSee('No debería verse.');
        });
    });
});
