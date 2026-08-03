<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

describe('Admin\\Categories\\Index', function () {
    describe('rendering', function () {
        it('should render an empty state when no categories exist', function () {
            Livewire::test('pages::admin.categories.index')
                ->assertOk()
                ->assertSee('Crea la primera');
        });

        it('should render categories in depth-first order with depth tracking', function () {
            $rental = Category::factory()->create(['name' => 'Alquiler']);
            $generators = Category::factory()->childOf($rental)->create(['name' => 'Alquiler de generadores']);
            Category::factory()->childOf($generators)->create(['name' => 'Alquiler de generadores diésel']);

            $rows = Livewire::test('pages::admin.categories.index')
                ->assertOk()
                ->get('tree');

            $names = collect($rows)->map(fn (array $row): string => $row['category']->name)->all();
            $depths = collect($rows)->map(fn (array $row): int => $row['depth'])->all();

            expect($names)->toBe(['Alquiler', 'Alquiler de generadores', 'Alquiler de generadores diésel']);
            expect($depths)->toBe([0, 1, 2]);
        });
    });

    describe('actions', function () {
        it('should delete a category and demote its children to roots', function () {
            $parent = Category::factory()->create();
            $child = Category::factory()->childOf($parent)->create();

            Livewire::test('pages::admin.categories.index')
                ->call('deleteCategory', $parent->id);

            expect(Category::find($parent->id))->toBeNull();
            expect(Category::find($child->id)->parent_id)->toBeNull();
        });
    });
});
