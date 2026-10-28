<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Service;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

describe('Admin\\Services\\Index', function () {
    describe('rendering', function () {
        it('should render an empty state when no services exist', function () {
            Livewire::test('pages::admin.services.index')
                ->assertOk()
                ->assertSee('No hay servicios con esos filtros');
        });

        it('should list services ordered by position then name', function () {
            $category = Category::factory()->create();
            Service::factory()->inCategory($category)->create(['name' => 'B', 'position' => 1]);
            Service::factory()->inCategory($category)->create(['name' => 'A', 'position' => 2]);
            Service::factory()->inCategory($category)->create(['name' => 'C', 'position' => 0]);

            $names = Livewire::test('pages::admin.services.index')
                ->get('services')
                ->pluck('name')
                ->all();

            expect($names)->toBe(['C', 'B', 'A']);
        });
    });

    describe('filters', function () {
        it('should filter by category', function () {
            $catA = Category::factory()->create();
            $catB = Category::factory()->create();
            Service::factory()->inCategory($catA)->create();
            Service::factory()->inCategory($catB)->count(2)->create();

            $services = Livewire::test('pages::admin.services.index')
                ->set('categoryId', $catA->id)
                ->get('services');

            expect($services)->toHaveCount(1);
        });

        it('should filter by status', function () {
            Service::factory()->count(3)->create();
            Service::factory()->inactive()->count(2)->create();

            $services = Livewire::test('pages::admin.services.index')
                ->set('status', 'active')
                ->get('services');

            expect($services)->toHaveCount(3);
        });

        it('should search by name substring', function () {
            $category = Category::factory()->create();
            Service::factory()->inCategory($category)->create(['name' => 'SDMO 100 kVA']);
            Service::factory()->inCategory($category)->create(['name' => 'Honda EU22i']);

            $services = Livewire::test('pages::admin.services.index')
                ->set('search', 'SDMO')
                ->get('services');

            expect($services)->toHaveCount(1);
        });
    });

    describe('sort', function () {
        it('should sort by name and flip the direction on a second click', function () {
            $category = Category::factory()->create();
            Service::factory()->inCategory($category)->create(['name' => 'Beta', 'position' => 0]);
            Service::factory()->inCategory($category)->create(['name' => 'Alfa', 'position' => 1]);

            $component = Livewire::test('pages::admin.services.index')
                ->call('sort', 'name')
                ->assertSet('sortBy', 'name')
                ->assertSet('sortDirection', 'asc');

            expect($component->get('services')->pluck('name')->all())->toBe(['Alfa', 'Beta']);

            $component->call('sort', 'name')->assertSet('sortDirection', 'desc');

            expect($component->get('services')->pluck('name')->all())->toBe(['Beta', 'Alfa']);
        });

        it('should ignore a column that is not sortable', function () {
            Livewire::test('pages::admin.services.index')
                ->call('sort', 'category_id')
                ->assertSet('sortBy', '');
        });
    });

    describe('clearFilter', function () {
        it('should show active filters as chips and clear one at a time', function () {
            $category = Category::factory()->create(['name' => 'Generadores']);

            $component = Livewire::test('pages::admin.services.index')
                ->set('categoryId', $category->id)
                ->set('status', 'inactive');

            expect($component->get('chips')->pluck('label')->all())->toBe(['Generadores', 'Inactivos']);

            $component->call('clearFilter', 'categoryId')
                ->assertSet('categoryId', null)
                ->assertSet('status', 'inactive');
        });
    });

    describe('clearAll', function () {
        it('should reset search, filters and sort', function () {
            $category = Category::factory()->create();

            Livewire::test('pages::admin.services.index')
                ->set('search', 'x')
                ->set('categoryId', $category->id)
                ->set('status', 'active')
                ->call('sort', 'position')
                ->call('clearAll')
                ->assertSet('search', '')
                ->assertSet('categoryId', null)
                ->assertSet('status', 'all')
                ->assertSet('sortBy', '');
        });
    });

    describe('actions', function () {
        it('should toggle is_active', function () {
            $service = Service::factory()->create(['is_active' => true]);

            Livewire::test('pages::admin.services.index')
                ->call('toggleActive', $service->id);

            expect($service->refresh()->is_active)->toBeFalse();
        });

    });
});
