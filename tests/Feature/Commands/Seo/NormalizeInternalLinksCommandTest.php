<?php

declare(strict_types=1);

use App\Console\Commands\Seo\NormalizeInternalLinksCommand;
use App\Models\BlogPost;
use App\Models\Landing;
use App\Models\Lead;
use App\Models\MenuItem;
use App\Models\Page;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    config()->set('app.url', 'https://ejemplo.com');
});

describe('NormalizeInternalLinksCommand', function (): void {
    describe('handle', function (): void {
        it('should rewrite the links inside a saved body', function (): void {
            $post = BlogPost::factory()->create([
                'body' => '<p><a href="https://ejemplo.com/servicios/reformas/">El servicio</a> '
                    .'y el <a href="/blog/otra-entrada/">artículo</a>.</p>',
            ]);

            $this->artisan('seo:normalize-links')->assertSuccessful();

            expect($post->fresh()->body)
                ->toContain('href="/servicios/reformas"')
                ->toContain('href="/blog/otra-entrada"')
                ->not->toContain('ejemplo.com');
        });

        it('should rewrite a menu item that holds nothing but an address', function (): void {
            $item = MenuItem::factory()->create(['url' => '/quienes-somos/']);

            $this->artisan('seo:normalize-links')->assertSuccessful();

            expect($item->fresh()->url)->toBe('/quienes-somos');
        });

        it('should reach a link buried inside a json column and leave its shape intact', function (): void {
            $landing = Landing::factory()->create([
                'content' => [
                    'bloques' => [
                        ['titulo' => 'Bloque 1', 'cuerpo' => '<p><a href="/contacto/">Escríbenos</a></p>'],
                    ],
                ],
            ]);

            $this->artisan('seo:normalize-links')->assertSuccessful();

            $content = $landing->fresh()->content;

            expect($content['bloques'][0]['cuerpo'])->toContain('href="/contacto"')
                ->and($content['bloques'][0]['titulo'])->toBe('Bloque 1');
        });

        it('should leave a link to another site exactly as it was', function (): void {
            $post = BlogPost::factory()->create([
                'body' => '<a href="https://boe.es/boletines/">BOE</a>'
                    .'<a href="https://tienda.ejemplo.com/catalogo/">Tienda</a>',
            ]);
            $original = $post->body;

            $this->artisan('seo:normalize-links')->assertSuccessful();

            expect($post->fresh()->body)->toBe($original);
        });

        it('should not rewrite what a visitor wrote', function (): void {
            // Un mensaje de contacto es constancia de lo que alguien escribió, no
            // copia que esta web publique. Reescribirlo le cambia las palabras.
            $lead = Lead::factory()->create(['message' => 'Lo vi en https://ejemplo.com/servicios/reformas/']);

            $this->artisan('seo:normalize-links')->assertSuccessful();

            expect($lead->fresh()->message)->toBe('Lo vi en https://ejemplo.com/servicios/reformas/');
        });

        it('should write nothing on a dry run', function (): void {
            $page = Page::factory()->create(['body' => '<a href="/faqs/">FAQs</a>']);

            $this->artisan('seo:normalize-links', ['--dry-run' => true])
                ->expectsOutputToContain('Would rewrite')
                ->assertSuccessful();

            expect($page->fresh()->body)->toContain('href="/faqs/"');
        });

        it('should report nothing to do when every link is already canonical', function (): void {
            BlogPost::factory()->create(['body' => '<a href="/faqs">FAQs</a>']);

            $this->artisan('seo:normalize-links')
                ->expectsOutputToContain('already points at a canonical address')
                ->assertSuccessful();
        });

        it('should change nothing on a second run', function (): void {
            BlogPost::factory()->create(['body' => '<a href="/faqs/">FAQs</a>']);

            $this->artisan('seo:normalize-links')->assertSuccessful();

            $this->artisan('seo:normalize-links')
                ->expectsOutputToContain('already points at a canonical address')
                ->assertSuccessful();
        });

        it('should not push the record dates forward', function (): void {
            // Arreglar un enlace no debe mover el `lastmod` de todo el sitemap a hoy.
            $post = BlogPost::factory()->create(['body' => '<a href="/faqs/">FAQs</a>']);
            $updatedAt = $post->updated_at;

            $this->travel(1)->days();

            $this->artisan('seo:normalize-links')->assertSuccessful();

            expect($post->fresh()->updated_at->timestamp)->toBe($updatedAt->timestamp);
        });

        it('should name classes and columns that exist in its declared lists', function (): void {
            // Un `Foo::class` sin su `use` no da error de nada: produce el nombre de
            // una clase que no existe y la lista deja de excluir —o de reescribir—
            // lo que dice, en silencio. Lo mismo con una columna renombrada.
            $command = new ReflectionClass(NormalizeInternalLinksCommand::class);

            /** @var list<class-string> $notContent */
            $notContent = $command->getConstant('NOT_CONTENT');

            foreach ($notContent as $class) {
                expect(class_exists($class))->toBeTrue("NOT_CONTENT nombra una clase que no existe: {$class}");
            }

            /** @var array<class-string, list<string>> $urlColumns */
            $urlColumns = $command->getConstant('URL_COLUMNS');

            foreach ($urlColumns as $class => $columns) {
                expect(class_exists($class))->toBeTrue("URL_COLUMNS nombra una clase que no existe: {$class}");

                foreach ($columns as $column) {
                    expect(Schema::hasColumn((new $class)->getTable(), $column))
                        ->toBeTrue("URL_COLUMNS nombra una columna que no existe: {$class}.{$column}");
                }
            }
        });

        it('should accept a host given by hand when the app url is not the public one', function (): void {
            config()->set('app.url', 'http://localhost');

            $post = BlogPost::factory()->create([
                'body' => '<a href="https://ejemplo.com/faqs/">FAQs</a>',
            ]);

            $this->artisan('seo:normalize-links', ['--host' => ['ejemplo.com']])->assertSuccessful();

            expect($post->fresh()->body)->toContain('href="/faqs"');
        });
    });
});
