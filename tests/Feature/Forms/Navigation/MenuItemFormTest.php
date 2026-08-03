<?php

declare(strict_types=1);

use App\Enums\MenuItemType;
use App\Enums\MenuLocation;
use App\Models\MenuItem;
use Livewire\Livewire;
use Tests\Support\Livewire\MenuItemFormTestComponent;

describe('MenuItemForm', function (): void {
    describe('setMenuItem', function (): void {
        it('should fill the form from an existing item', function (): void {
            $parent = MenuItem::factory()->create(['label' => 'Servicios']);
            $child = MenuItem::factory()->childOf($parent)->create([
                'label' => 'Servicios por categoría',
                'type' => MenuItemType::DynamicBlock,
                'url' => null,
                'source' => 'services_by_category',
                'position' => 3,
                'is_active' => false,
            ]);

            Livewire::test(MenuItemFormTestComponent::class, ['menuItem' => $child])
                ->assertSet('form.id', $child->id)
                ->assertSet('form.parent_id', $parent->id)
                ->assertSet('form.location', 'header')
                ->assertSet('form.type', 'dynamic_block')
                ->assertSet('form.label', 'Servicios por categoría')
                ->assertSet('form.url', null)
                ->assertSet('form.source', 'services_by_category')
                ->assertSet('form.position', 3)
                ->assertSet('form.is_active', false);
        });
    });

    describe('save', function (): void {
        it('should store an item from what was typed', function (): void {
            Livewire::test(MenuItemFormTestComponent::class)
                ->set('form.location', MenuLocation::Footer->value)
                ->set('form.label', 'Contacto')
                ->set('form.url', '/contacto/')
                ->call('save')
                ->assertHasNoErrors();

            $item = MenuItem::sole();

            expect($item->label)->toBe('Contacto')
                ->and($item->location)->toBe(MenuLocation::Footer)
                ->and($item->type)->toBe(MenuItemType::Link)
                ->and($item->is_active)->toBeTrue();
        });

        it('should send a new item to the end of its own menu', function (): void {
            MenuItem::factory()->in(MenuLocation::Footer)->create(['position' => 7]);
            MenuItem::factory()->in(MenuLocation::Header)->create(['position' => 99]);

            Livewire::test(MenuItemFormTestComponent::class)
                ->set('form.location', MenuLocation::Footer->value)
                ->set('form.label', 'Contacto')
                ->set('form.url', '/contacto/')
                ->call('save')
                ->assertHasNoErrors()
                ->assertSet('form.position', 8);
        });

        it('should respect an explicit order', function (): void {
            Livewire::test(MenuItemFormTestComponent::class)
                ->set('form.location', MenuLocation::Header->value)
                ->set('form.label', 'Contacto')
                ->set('form.url', '/contacto/')
                ->set('form.position', 2)
                ->call('save')
                ->assertHasNoErrors();

            expect(MenuItem::sole()->position)->toBe(2);
        });

        it('should drop the address when the item becomes a dynamic block', function (): void {
            // The template ships no blocks: a site declares its own, and the
            // admin only offers the type once one exists.
            config()->set('site.menu_blocks', ['services_by_category' => 'Servicios por categoría']);

            $item = MenuItem::factory()->create(['url' => '/servicios/']);

            Livewire::test(MenuItemFormTestComponent::class, ['menuItem' => $item])
                ->set('form.type', MenuItemType::DynamicBlock->value)
                ->set('form.source', 'services_by_category')
                ->call('save')
                ->assertHasNoErrors();

            expect($item->fresh()->url)->toBeNull();
        });

        it('should drop the block when the item becomes a plain link', function (): void {
            config()->set('site.menu_blocks', ['services_by_category' => 'Servicios por categoría']);

            $item = MenuItem::factory()->create([
                'type' => MenuItemType::DynamicBlock,
                'url' => null,
                'source' => 'services_by_category',
            ]);

            Livewire::test(MenuItemFormTestComponent::class, ['menuItem' => $item])
                ->set('form.type', MenuItemType::Link->value)
                ->set('form.url', '/servicios/')
                ->call('save')
                ->assertHasNoErrors();

            expect($item->fresh()->source)->toBeNull();
        });

        it('should let a link save while carrying a block the site no longer paints', function (): void {
            $item = MenuItem::factory()->create([
                'type' => MenuItemType::DynamicBlock,
                'url' => null,
                'source' => 'bloque_retirado',
            ]);

            Livewire::test(MenuItemFormTestComponent::class, ['menuItem' => $item])
                ->set('form.type', MenuItemType::Link->value)
                ->set('form.url', '/servicios/')
                ->call('save')
                ->assertHasNoErrors();

            expect($item->fresh()->source)->toBeNull();
        });

        it('should let an item keep only a label, for a footer column heading', function (): void {
            Livewire::test(MenuItemFormTestComponent::class)
                ->set('form.location', MenuLocation::Footer->value)
                ->set('form.label', 'Explora')
                ->call('save')
                ->assertHasNoErrors();

            expect(MenuItem::sole()->url)->toBeNull();
        });

        it('should take its submenu along when the item changes menu', function (): void {
            $parent = MenuItem::factory()->in(MenuLocation::Header)->create();
            $child = MenuItem::factory()->childOf($parent)->create();

            Livewire::test(MenuItemFormTestComponent::class, ['menuItem' => $parent])
                ->set('form.location', MenuLocation::Footer->value)
                ->call('save')
                ->assertHasNoErrors();

            expect($child->fresh()->location)->toBe(MenuLocation::Footer);
        });

        it('should update the item it was opened with instead of creating another', function (): void {
            $item = MenuItem::factory()->create(['label' => 'Cursos']);

            Livewire::test(MenuItemFormTestComponent::class, ['menuItem' => $item])
                ->set('form.label', 'Formación')
                ->call('save')
                ->assertHasNoErrors();

            expect(MenuItem::count())->toBe(1)
                ->and($item->fresh()->label)->toBe('Formación');
        });
    });

    describe('validation', function (): void {
        it('should require a label', function (): void {
            Livewire::test(MenuItemFormTestComponent::class)
                ->set('form.location', MenuLocation::Header->value)
                ->set('form.url', '/contacto/')
                ->call('save')
                ->assertHasErrors(['form.label' => 'required']);
        });

        it('should require a menu', function (): void {
            Livewire::test(MenuItemFormTestComponent::class)
                ->set('form.label', 'Contacto')
                ->call('save')
                ->assertHasErrors(['form.location' => 'required']);
        });

        it('should refuse an address that is neither a path nor a full one', function (): void {
            Livewire::test(MenuItemFormTestComponent::class)
                ->set('form.location', MenuLocation::Header->value)
                ->set('form.label', 'Contacto')
                ->set('form.url', 'contacto')
                ->call('save')
                ->assertHasErrors(['form.url']);
        });

        it('should accept an address on another site', function (): void {
            Livewire::test(MenuItemFormTestComponent::class)
                ->set('form.location', MenuLocation::Header->value)
                ->set('form.label', 'Zona de clientes')
                ->set('form.url', 'https://acceso.example.com/login')
                ->call('save')
                ->assertHasNoErrors();
        });

        it('should require a block on a dynamic block item', function (): void {
            config()->set('site.menu_blocks', ['services_by_category' => 'Servicios por categoría']);

            Livewire::test(MenuItemFormTestComponent::class)
                ->set('form.location', MenuLocation::Header->value)
                ->set('form.label', 'Servicios por categoría')
                ->set('form.type', MenuItemType::DynamicBlock->value)
                ->call('save')
                ->assertHasErrors(['form.source' => 'required']);
        });

        it('should refuse a block this site cannot paint', function (): void {
            config()->set('site.menu_blocks', ['services_by_category' => 'Servicios por categoría']);

            Livewire::test(MenuItemFormTestComponent::class)
                ->set('form.location', MenuLocation::Header->value)
                ->set('form.label', 'Inventado')
                ->set('form.type', MenuItemType::DynamicBlock->value)
                ->set('form.source', 'no_existe')
                ->call('save')
                ->assertHasErrors(['form.source']);
        });

        it('should refuse any block on a site that declares none', function (): void {
            Livewire::test(MenuItemFormTestComponent::class)
                ->set('form.location', MenuLocation::Header->value)
                ->set('form.label', 'Servicios por categoría')
                ->set('form.type', MenuItemType::DynamicBlock->value)
                ->set('form.source', 'services_by_category')
                ->call('save')
                ->assertHasErrors(['form.source']);
        });

        it('should refuse a parent from another menu', function (): void {
            $parent = MenuItem::factory()->in(MenuLocation::Header)->create();

            Livewire::test(MenuItemFormTestComponent::class)
                ->set('form.location', MenuLocation::Footer->value)
                ->set('form.label', 'Contacto')
                ->set('form.url', '/contacto/')
                ->set('form.parent_id', $parent->id)
                ->call('save')
                ->assertHasErrors(['form.parent_id']);
        });

        it('should refuse a third level', function (): void {
            $parent = MenuItem::factory()->create();
            $child = MenuItem::factory()->childOf($parent)->create();

            Livewire::test(MenuItemFormTestComponent::class)
                ->set('form.location', MenuLocation::Header->value)
                ->set('form.label', 'Nieto')
                ->set('form.url', '/nieto/')
                ->set('form.parent_id', $child->id)
                ->call('save')
                ->assertHasErrors(['form.parent_id']);
        });

        it('should refuse to hang an item that already has a submenu', function (): void {
            $parent = MenuItem::factory()->create();
            MenuItem::factory()->childOf($parent)->create();
            $other = MenuItem::factory()->create();

            Livewire::test(MenuItemFormTestComponent::class, ['menuItem' => $parent])
                ->set('form.parent_id', $other->id)
                ->call('save')
                ->assertHasErrors(['form.parent_id']);
        });

        it('should refuse an item that hangs from itself', function (): void {
            $item = MenuItem::factory()->create();

            Livewire::test(MenuItemFormTestComponent::class, ['menuItem' => $item])
                ->set('form.parent_id', $item->id)
                ->call('save')
                ->assertHasErrors(['form.parent_id']);
        });

        it('should refuse a negative order', function (): void {
            Livewire::test(MenuItemFormTestComponent::class)
                ->set('form.location', MenuLocation::Header->value)
                ->set('form.label', 'Contacto')
                ->set('form.url', '/contacto/')
                ->set('form.position', -1)
                ->call('save')
                ->assertHasErrors(['form.position']);
        });
    });
});
