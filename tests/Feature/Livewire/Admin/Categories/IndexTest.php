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

    describe('search', function (): void {
        it('should flatten results and match the slug too', function (): void {
            $parent = Category::factory()->create(['name' => 'Seguridad']);
            Category::factory()->childOf($parent)->create(['name' => 'Policía Nacional', 'slug' => 'cnp']);
            Category::factory()->childOf($parent)->create(['name' => 'Guardia Civil', 'slug' => 'guardia-civil']);

            $rows = Livewire::test('pages::admin.categories.index')
                ->set('search', 'cnp')
                ->get('tree');

            expect($rows)->toHaveCount(1);
            expect($rows[0]['category']->name)->toBe('Policía Nacional');
            expect($rows[0]['depth'])->toBe(0);
        });
    });

    describe('sort', function (): void {
        it('should flatten the tree and sort by name', function (): void {
            $parent = Category::factory()->create(['name' => 'Seguridad']);
            Category::factory()->childOf($parent)->create(['name' => 'Agente de policía']);

            $component = Livewire::test('pages::admin.categories.index')
                ->call('sort', 'name')
                ->assertSet('sortBy', 'name')
                ->assertSet('sortDirection', 'asc');

            expect(collect($component->get('tree'))->map(fn (array $row): string => $row['category']->name)->all())
                ->toBe(['Agente de policía', 'Seguridad']);

            $component->call('sort', 'name')->assertSet('sortDirection', 'desc');

            expect(collect($component->get('tree'))->map(fn (array $row): string => $row['category']->name)->all())
                ->toBe(['Seguridad', 'Agente de policía']);
        });

        it('should ignore a column that cannot be sorted', function (): void {
            Livewire::test('pages::admin.categories.index')
                ->call('sort', 'icon')
                ->assertSet('sortBy', '');
        });
    });
});
