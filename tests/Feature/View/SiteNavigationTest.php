<?php

declare(strict_types=1);

use App\Enums\MenuLocation;
use App\Models\MenuItem;
use App\View\Components\SiteNavigation;

describe('SiteNavigation', function () {
    describe('items', function () {
        it('returns the entries of its location in order', function () {
            MenuItem::factory()->create(['location' => MenuLocation::Header, 'label' => 'Segundo', 'position' => 2]);
            MenuItem::factory()->create(['location' => MenuLocation::Header, 'label' => 'Primero', 'position' => 1]);
            MenuItem::factory()->create(['location' => MenuLocation::Footer, 'label' => 'Del pie', 'position' => 1]);

            $items = (new SiteNavigation(MenuLocation::Header))->items();

            expect($items->pluck('label')->all())->toBe(['Primero', 'Segundo']);
        });

        it('leaves out what is not active', function () {
            MenuItem::factory()->create(['location' => MenuLocation::Header, 'label' => 'Visible']);
            MenuItem::factory()->create(['location' => MenuLocation::Header, 'label' => 'Oculto', 'is_active' => false]);

            expect((new SiteNavigation(MenuLocation::Header))->items()->pluck('label')->all())
                ->toBe(['Visible']);
        });

        it('nests the children under their parent instead of listing them alongside', function () {
            $parent = MenuItem::factory()->create(['location' => MenuLocation::Header, 'label' => 'Servicios']);
            MenuItem::factory()->create([
                'location' => MenuLocation::Header,
                'parent_id' => $parent->id,
                'label' => 'Consultoría',
                'position' => 1,
            ]);

            $items = (new SiteNavigation(MenuLocation::Header))->items();

            expect($items)->toHaveCount(1);
            expect($items->first()->children->pluck('label')->all())->toBe(['Consultoría']);
        });

        it('hides the children of a parent that is switched off', function () {
            // Hiding a section has to hide what hangs from it, or the submenu
            // survives its own heading.
            $parent = MenuItem::factory()->create(['location' => MenuLocation::Header, 'is_active' => false]);
            MenuItem::factory()->create(['location' => MenuLocation::Header, 'parent_id' => $parent->id]);

            expect((new SiteNavigation(MenuLocation::Header))->items())->toBeEmpty();
        });

        it('leaves out a child that is switched off on its own', function () {
            $parent = MenuItem::factory()->create(['location' => MenuLocation::Header]);
            MenuItem::factory()->create(['location' => MenuLocation::Header, 'parent_id' => $parent->id, 'label' => 'Vivo']);
            MenuItem::factory()->create(['location' => MenuLocation::Header, 'parent_id' => $parent->id, 'is_active' => false]);

            $items = (new SiteNavigation(MenuLocation::Header))->items();

            expect($items->first()->children->pluck('label')->all())->toBe(['Vivo']);
        });

        it('reads the menu in a fixed number of queries, whatever its size', function () {
            $parent = MenuItem::factory()->create(['location' => MenuLocation::Header]);
            MenuItem::factory()->count(8)->create(['location' => MenuLocation::Header, 'parent_id' => $parent->id]);
            MenuItem::factory()->count(5)->create(['location' => MenuLocation::Header]);

            DB::enableQueryLog();

            (new SiteNavigation(MenuLocation::Header))->items()->each(fn ($item) => $item->children->all());

            expect(DB::getQueryLog())->toHaveCount(2);
        });
    });

    describe('rendering', function () {
        it('renders nothing at all when the menu is empty', function () {
            // A site that has not filled its menu in yet must not get an empty
            // nav element sitting in its header.
            $this->blade('<x-site-navigation />')->assertDontSee('<nav', false);
        });

        it('renders a plain link for an entry with no children', function () {
            MenuItem::factory()->create([
                'location' => MenuLocation::Header,
                'label' => 'Contacto',
                'url' => '/contacto',
            ]);

            $this->blade('<x-site-navigation />')
                ->assertSee('Contacto')
                ->assertSee('href="/contacto"', false);
        });

        it('renders the children of an entry that has them', function () {
            $parent = MenuItem::factory()->create(['location' => MenuLocation::Header, 'label' => 'Servicios']);
            MenuItem::factory()->create([
                'location' => MenuLocation::Header,
                'parent_id' => $parent->id,
                'label' => 'Consultoría',
                'url' => '/servicios/consultoria',
            ]);

            $this->blade('<x-site-navigation />')
                ->assertSee('Servicios')
                ->assertSee('Consultoría')
                ->assertSee('/servicios/consultoria', false);
        });

        it('renders the footer menu when asked for that location', function () {
            MenuItem::factory()->create([
                'location' => MenuLocation::Footer,
                'label' => 'Trabaja con nosotros',
                'url' => '/empleo',
            ]);
            MenuItem::factory()->create(['location' => MenuLocation::Header, 'label' => 'De la cabecera']);

            $this->blade('<x-site-navigation :location="$location" />', ['location' => MenuLocation::Footer])
                ->assertSee('Trabaja con nosotros')
                ->assertDontSee('De la cabecera');
        });
    });
});
