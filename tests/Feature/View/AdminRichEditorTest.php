<?php

declare(strict_types=1);

use App\Models\User;

/**
 * Cada campo del admin que guarda HTML y la pantalla donde se escribe.
 *
 * Son los que la web pública imprime con `{!! !!}`: párrafos y encabezados.
 * Lo que no está en esta lista es texto plano, se imprime escapado, y tiene
 * que seguir siendo un control de texto plano.
 *
 * @return array<string, array{0: string, 1: string}>
 */
function adminHtmlFields(): array
{
    return [
        'blog body' => ['admin.blog.create', 'form.body'],
        'page body' => ['admin.pages.create', 'form.body'],
    ];
}

describe('AdminRichEditor', function (): void {
    beforeEach(function (): void {
        $this->actingAs(User::factory()->create());
    });

    describe('html_fields', function (): void {
        /**
         * Un `<p>` o un `<h2>` guardados y enseñados en crudo dentro de una
         * caja de texto son ilegibles para leerlos y peores para editarlos.
         */
        it('should edit stored HTML with the rich editor', function (string $route, string $model): void {
            $html = (string) $this->get(route($route))->assertSuccessful()->getContent();

            expect($html)
                ->toMatch('/<ui-editor[^>]*wire:model="'.preg_quote($model, '/').'"/')
                ->not->toMatch('/<textarea[^>]*wire:model="'.preg_quote($model, '/').'"/');
        })->with(adminHtmlFields());

        /**
         * La barra flota sobre la selección en vez de estar fija encima del
         * campo, y `floating-toolbar.js` la encuentra por esta clase. Sin ella
         * es una caja `position: fixed` que no coloca nadie.
         */
        it('should hand the toolbar to the floating placer', function (string $route): void {
            $html = (string) $this->get(route($route))->assertSuccessful()->getContent();

            expect($html)->toMatch('/<ui-toolbar[^>]*class="[^"]*floating-toolbar/');
        })->with(adminHtmlFields());
    });

    describe('component_use', function (): void {
        /**
         * La barra flotante sólo existe porque la pinta `<x-admin.rich-editor>`.
         * Un `<flux:editor>` soltado directamente en una pantalla se lleva la
         * barra fija de Flux, y el panel deja de escribir igual en todas partes.
         */
        it('should reach the editor only through the shared component', function (): void {
            $offenders = [];

            foreach ((glob(resource_path('views/pages/admin/*/⚡*.blade.php')) ?: []) as $screen) {
                if (str_contains((string) file_get_contents($screen), '<flux:editor')) {
                    $offenders[] = basename(dirname($screen)).'/'.basename($screen);
                }
            }

            expect($offenders)->toBe([]);
        });
    });

    describe('plain_text_fields', function (): void {
        /**
         * El excerpt y las meta descripciones se imprimen escapados con
         * `{{ }}`. Dale un editor a uno de ellos y el `<p>` con el que envuelve
         * el texto aparece como marcado visible en la tarjeta que alimenta.
         */
        it('should keep the escaped fields as plain controls', function (): void {
            $html = (string) $this->get(route('admin.blog.create'))->assertSuccessful()->getContent();

            expect($html)->not->toMatch('/<ui-editor[^>]*wire:model="form\.(excerpt|meta_description)"/');
        });
    });
});
