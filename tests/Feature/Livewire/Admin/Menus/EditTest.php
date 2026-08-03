<?php

declare(strict_types=1);

use App\Enums\MenuItemType;
use App\Enums\MenuLocation;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

describe('Admin\\Menus\\Edit', function (): void {
    describe('create', function (): void {
        it('should open on the header menu by default', function (): void {
            Livewire::test('pages::admin.menus.edit')
                ->assertSet('form.location', MenuLocation::Header->value);
        });

        it('should open on the menu the index came from', function (): void {
            Livewire::withQueryParams(['location' => MenuLocation::Footer->value])
                ->test('pages::admin.menus.edit')
                ->assertSet('form.location', MenuLocation::Footer->value);
        });

        it('should ignore a menu that does not exist', function (): void {
            Livewire::withQueryParams(['location' => 'inventado'])
                ->test('pages::admin.menus.edit')
                ->assertSet('form.location', MenuLocation::Header->value);
        });

        it('should persist a new item with valid input', function (): void {
            Livewire::test('pages::admin.menus.edit')
                ->set('form.location', MenuLocation::Legal->value)
                ->set('form.label', 'Aviso legal')
                ->set('form.url', '/aviso-legal/')
                ->call('save')
                ->assertHasNoErrors()
                ->assertRedirect();

            expect(MenuItem::where('label', 'Aviso legal')->exists())->toBeTrue();
        });
    });

    describe('edit', function (): void {
        it('should open the item behind its own address', function (): void {
            $item = MenuItem::factory()->create(['label' => 'Catálogo']);

            $this->get(route('admin.menus.edit', $item))
                ->assertSuccessful()
                ->assertSee('Editar ítem: Catálogo');
        });

        it('should preload the form when mounted with an item', function (): void {
            $item = MenuItem::factory()->in(MenuLocation::Footer)->create([
                'label' => 'Contacto',
                'url' => '/contacto/',
                'position' => 4,
            ]);

            Livewire::test('pages::admin.menus.edit', ['menuItem' => $item])
                ->assertSet('form.id', $item->id)
                ->assertSet('form.location', 'footer')
                ->assertSet('form.label', 'Contacto')
                ->assertSet('form.url', '/contacto/')
                ->assertSet('form.position', 4)
                ->assertSee('Editar ítem: Contacto');
        });

        it('should save changes onto the same item', function (): void {
            $item = MenuItem::factory()->create(['label' => 'Catálogo']);

            Livewire::test('pages::admin.menus.edit', ['menuItem' => $item])
                ->set('form.label', 'Servicios')
                ->call('save')
                ->assertHasNoErrors();

            expect(MenuItem::count())->toBe(1)
                ->and($item->fresh()->label)->toBe('Servicios');
        });
    });

    describe('parentOptions', function (): void {
        it('should offer only first-level items of the chosen menu', function (): void {
            $headerRoot = MenuItem::factory()->in(MenuLocation::Header)->create(['label' => 'Servicios']);
            MenuItem::factory()->childOf($headerRoot)->create(['label' => 'Por categoría']);
            MenuItem::factory()->in(MenuLocation::Footer)->create(['label' => 'Explora']);

            $component = Livewire::test('pages::admin.menus.edit')
                ->set('form.location', MenuLocation::Header->value);

            expect($component->instance()->parentOptions->pluck('label')->all())
                ->toBe(['Servicios']);
        });

        it('should follow the menu the editor switches to', function (): void {
            MenuItem::factory()->in(MenuLocation::Header)->create(['label' => 'Servicios']);
            MenuItem::factory()->in(MenuLocation::Footer)->create(['label' => 'Explora']);

            $component = Livewire::test('pages::admin.menus.edit')
                ->set('form.location', MenuLocation::Footer->value);

            expect($component->instance()->parentOptions->pluck('label')->all())
                ->toBe(['Explora']);
        });

        it('should not offer the item being edited as its own parent', function (): void {
            $item = MenuItem::factory()->in(MenuLocation::Header)->create(['label' => 'Servicios']);

            $component = Livewire::test('pages::admin.menus.edit', ['menuItem' => $item]);

            expect($component->instance()->parentOptions->pluck('label')->all())->toBe([]);
        });

        it('should forget the parent when the menu changes under it', function (): void {
            $parent = MenuItem::factory()->in(MenuLocation::Header)->create();

            Livewire::test('pages::admin.menus.edit')
                ->set('form.parent_id', $parent->id)
                ->set('form.location', MenuLocation::Footer->value)
                ->assertSet('form.parent_id', null);
        });
    });

    describe('type', function (): void {
        it('should ask for a block instead of an address on a dynamic block', function (): void {
            config()->set('site.menu_blocks', ['services_by_category' => 'Servicios por categoría']);

            Livewire::test('pages::admin.menus.edit')
                ->set('form.type', MenuItemType::DynamicBlock->value)
                ->assertSee('Servicios por categoría')
                ->assertDontSee('/servicios/');
        });

        it('should not offer the dynamic block type on a site that declares no blocks', function (): void {
            Livewire::test('pages::admin.menus.edit')
                ->assertSee('Enlace')
                ->assertDontSee('Bloque dinámico');
        });

        it('should not offer a parent to an item that already has a submenu', function (): void {
            $parent = MenuItem::factory()->create();
            MenuItem::factory()->childOf($parent)->create();

            $component = Livewire::test('pages::admin.menus.edit', ['menuItem' => $parent]);

            expect($component->instance()->canHaveParent)->toBeFalse();
        });
    });
});
