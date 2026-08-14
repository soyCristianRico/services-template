<?php

declare(strict_types=1);

use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Landing;
use App\Models\Page;
use App\Models\Service;
use App\Models\User;
use App\Services\Admin\AdminBar;
use Illuminate\Support\Str;

describe('PublicAdminBar', function (): void {
    describe('visibility', function (): void {
        /**
         * La barra es para quien entra al panel y para nadie más. No escondida
         * con CSS para un visitante: no impresa, así que el HTML público no
         * lleva ni una etiqueta de más ni menciona que hay un panel.
         */
        it('should print nothing at all for a visitor', function (): void {
            $this->get('/')
                ->assertSuccessful()
                ->assertDontSee('data-admin-bar', false)
                ->assertDontSee(url('/admin'), false);
        });

        it('should show up on the public site once you are in', function (): void {
            $this->actingAs(User::factory()->create())
                ->get('/')
                ->assertSuccessful()
                ->assertSee('data-admin-bar', false)
                ->assertSee(url('/admin'), false);
        });
    });

    describe('registry', function (): void {
        /**
         * Cada tipo de contenido con pantalla en el panel llega desde la barra.
         * Se prueba contra el servicio y no contra cada página pública porque
         * lo que se está fijando es el registro: que ninguno se quede sin
         * puerta y que ninguno apunte a una pantalla que no existe.
         */
        it('should reach the screen of every kind of record', function (string $model, string $screen): void {
            $this->actingAs(User::factory()->create());

            $bar = app(AdminBar::class);
            $bar->editing($model::factory()->create());

            expect($bar->editUrl())->toContain(url($screen))
                ->and($bar->editLabel())->toStartWith('Editar ');
        })->with([
            'servicio' => [Service::class, 'admin/services'],
            'categoría' => [Category::class, 'admin/categories'],
            'artículo' => [BlogPost::class, 'admin/blog'],
            'página' => [Page::class, 'admin/pages'],
            'landing' => [Landing::class, 'admin/landings'],
        ]);

        it('should offer nothing to edit when the page is showing no record', function (): void {
            $this->actingAs(User::factory()->create());

            expect(app(AdminBar::class)->editUrl())->toBeNull()
                ->and(app(AdminBar::class)->editLabel())->toBeNull();
        });
    });

    describe('edit_link', function (): void {
        /**
         * El movimiento entero, sobre una página de verdad: estás mirando algo
         * y su pantalla del panel está a un clic, sin ir a buscarla al listado.
         */
        it('should reach the screen of whatever the page is showing', function (): void {
            $post = BlogPost::factory()->create();

            $this->actingAs(User::factory()->create())
                ->get('/blog/'.$post->slug)
                ->assertSuccessful()
                ->assertSee('Editar artículo')
                ->assertSee(url('admin/blog'), false);
        });

        /**
         * Un listado no es un registro. La barra sigue ahí —«Panel» y «Crear»
         * siguen significando algo— pero no ofrece nada que editar.
         */
        it('should offer nothing to edit where there is no record', function (): void {
            $html = (string) $this->actingAs(User::factory()->create())
                ->get('/blog')
                ->assertSuccessful()
                ->getContent();

            expect($html)->toContain('data-admin-bar')
                ->not->toContain('Editar artículo');
        });
    });

    describe('create_menu', function (): void {
        /**
         * El mismo registro que el enlace de editar, así que un tipo de
         * contenido no puede estar accesible por un lado y faltar por el otro.
         */
        it('should offer every kind of record it can also edit', function (): void {
            $this->actingAs(User::factory()->create());

            $urls = array_column(app(AdminBar::class)->creatable(), 'url');

            expect($urls)->toContain(url('admin/blog/create'))
                ->and($urls)->toContain(url('admin/pages/create'));
        });

        /**
         * `ucfirst` cuenta bytes, y hay nombres que abren con tilde.
         */
        it('should capitalise every label it prints', function (): void {
            $this->actingAs(User::factory()->create());

            foreach (app(AdminBar::class)->creatable() as $item) {
                expect($item['label'])->toBe(Str::ucfirst($item['label']));
            }
        });
    });
});
