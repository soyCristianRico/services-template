<?php

declare(strict_types=1);

use App\Enums\MenuLocation;
use App\Models\MenuItem;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

describe('Admin\\Menus\\Index', function (): void {
    describe('render', function (): void {
        it('should be reachable from the admin', function (): void {
            MenuItem::factory()->create(['label' => 'Servicios']);

            $this->get(route('admin.menus.index'))
                ->assertSuccessful()
                ->assertSee('Servicios');
        });

        it('should list every menu with its items', function (): void {
            $parent = MenuItem::factory()->in(MenuLocation::Header)->create(['label' => 'Servicios']);
            MenuItem::factory()->childOf($parent)->create(['label' => 'Servicios por categoría']);
            MenuItem::factory()->in(MenuLocation::Footer)->create(['label' => 'Explora']);

            Livewire::test('pages::admin.menus.index')
                ->assertSee('Cabecera')
                ->assertSee('Pie de página')
                ->assertSee('Servicios')
                ->assertSee('Servicios por categoría')
                ->assertSee('Explora');
        });

        it('should show hidden items too, so they can be brought back', function (): void {
            MenuItem::factory()->create(['label' => 'Empresas', 'is_active' => false]);

            Livewire::test('pages::admin.menus.index')
                ->assertSee('Empresas')
                ->assertSee('Oculto');
        });
    });

    describe('toggleActive', function (): void {
        it('should hide a visible item and show it again', function (): void {
            $item = MenuItem::factory()->create(['is_active' => true]);

            Livewire::test('pages::admin.menus.index')
                ->call('toggleActive', $item->id);

            expect($item->fresh()->is_active)->toBeFalse();

            Livewire::test('pages::admin.menus.index')
                ->call('toggleActive', $item->id);

            expect($item->fresh()->is_active)->toBeTrue();
        });
    });

    describe('moveUp', function (): void {
        it('should swap an item with the one above it', function (): void {
            $first = MenuItem::factory()->create(['label' => 'Uno', 'position' => 1]);
            $second = MenuItem::factory()->create(['label' => 'Dos', 'position' => 2]);

            Livewire::test('pages::admin.menus.index')
                ->call('moveUp', $second->id);

            expect($second->fresh()->position)->toBe(1)
                ->and($first->fresh()->position)->toBe(2);
        });

        it('should leave the first item where it is', function (): void {
            $first = MenuItem::factory()->create(['position' => 1]);
            MenuItem::factory()->create(['position' => 2]);

            Livewire::test('pages::admin.menus.index')
                ->call('moveUp', $first->id);

            expect($first->fresh()->position)->toBe(1);
        });

        it('should renumber siblings that arrived sharing a slot', function (): void {
            $first = MenuItem::factory()->create(['label' => 'Uno', 'position' => 0]);
            $second = MenuItem::factory()->create(['label' => 'Dos', 'position' => 0]);
            $third = MenuItem::factory()->create(['label' => 'Tres', 'position' => 0]);

            Livewire::test('pages::admin.menus.index')
                ->call('moveUp', $third->id);

            expect(MenuItem::orderBy('position')->pluck('label')->all())
                ->toBe(['Uno', 'Tres', 'Dos'])
                ->and([$first->fresh()->position, $third->fresh()->position, $second->fresh()->position])
                ->toBe([1, 2, 3]);
        });

        it('should not reach across menus', function (): void {
            $header = MenuItem::factory()->in(MenuLocation::Header)->create(['position' => 1]);
            $footer = MenuItem::factory()->in(MenuLocation::Footer)->create(['position' => 2]);

            Livewire::test('pages::admin.menus.index')
                ->call('moveUp', $footer->id);

            expect($footer->fresh()->position)->toBe(2)
                ->and($header->fresh()->position)->toBe(1);
        });

        it('should not reach across submenus', function (): void {
            $parent = MenuItem::factory()->create(['position' => 1]);
            $child = MenuItem::factory()->childOf($parent)->create(['position' => 2]);

            Livewire::test('pages::admin.menus.index')
                ->call('moveUp', $child->id);

            expect($child->fresh()->position)->toBe(2)
                ->and($parent->fresh()->position)->toBe(1);
        });
    });

    describe('moveDown', function (): void {
        it('should swap an item with the one below it', function (): void {
            $first = MenuItem::factory()->create(['position' => 1]);
            $second = MenuItem::factory()->create(['position' => 2]);

            Livewire::test('pages::admin.menus.index')
                ->call('moveDown', $first->id);

            expect($first->fresh()->position)->toBe(2)
                ->and($second->fresh()->position)->toBe(1);
        });

        it('should leave the last item where it is', function (): void {
            MenuItem::factory()->create(['position' => 1]);
            $last = MenuItem::factory()->create(['position' => 2]);

            Livewire::test('pages::admin.menus.index')
                ->call('moveDown', $last->id);

            expect($last->fresh()->position)->toBe(2);
        });
    });

});
