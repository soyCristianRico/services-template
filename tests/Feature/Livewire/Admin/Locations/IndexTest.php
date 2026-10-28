<?php

declare(strict_types=1);

use App\Enums\LocationType;
use App\Models\Location;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

describe('Admin\\Locations\\Index', function () {
    describe('rendering', function () {
        it('should render an empty state when no locations exist', function () {
            Livewire::test('pages::admin.locations.index')
                ->assertOk()
                ->assertSee('Crea la primera');
        });

        it('should render locations in depth-first order with depth tracking', function () {
            $country = Location::factory()->ofType(LocationType::Country)->create(['name' => 'España']);
            $region = Location::factory()->childOf($country)->ofType(LocationType::Region)->create(['name' => 'Madrid CCAA']);
            Location::factory()->childOf($region)->ofType(LocationType::City)->create(['name' => 'Madrid']);

            $rows = Livewire::test('pages::admin.locations.index')
                ->assertOk()
                ->get('tree');

            $names = collect($rows)->map(fn (array $row): string => $row['location']->name)->all();
            $depths = collect($rows)->map(fn (array $row): int => $row['depth'])->all();

            expect($names)->toBe(['España', 'Madrid CCAA', 'Madrid']);
            expect($depths)->toBe([0, 1, 2]);
        });
    });

    describe('search', function () {
        it('should flatten results when searching', function () {
            $parent = Location::factory()->create(['name' => 'España']);
            Location::factory()->childOf($parent)->create(['name' => 'Madrid']);
            Location::factory()->childOf($parent)->create(['name' => 'Barcelona']);

            $rows = Livewire::test('pages::admin.locations.index')
                ->set('search', 'madrid')
                ->get('tree');

            expect($rows)->toHaveCount(1);
            expect($rows[0]['location']->name)->toBe('Madrid');
        });
    });

    describe('sort', function (): void {
        it('should flatten the tree and sort by population', function (): void {
            $country = Location::factory()->ofType(LocationType::Country)->create(['name' => 'España', 'population' => 48000000]);
            Location::factory()->childOf($country)->create(['name' => 'Madrid', 'population' => 3300000]);
            Location::factory()->childOf($country)->create(['name' => 'Zaragoza', 'population' => 680000]);

            $component = Livewire::test('pages::admin.locations.index')
                ->call('sort', 'population')
                ->call('sort', 'population')
                ->assertSet('sortDirection', 'desc');

            expect(collect($component->get('tree'))->map(fn (array $row): string => $row['location']->name)->all())
                ->toBe(['España', 'Madrid', 'Zaragoza']);
        });

        it('should ignore a column that cannot be sorted', function (): void {
            Livewire::test('pages::admin.locations.index')
                ->call('sort', 'type')
                ->assertSet('sortBy', '');
        });
    });

    describe('clearFilter', function (): void {
        it('should filter by type, show it as a chip and clear it', function (): void {
            Location::factory()->ofType(LocationType::Province)->create(['name' => 'Huesca']);
            Location::factory()->ofType(LocationType::City)->create(['name' => 'Jaca']);

            $component = Livewire::test('pages::admin.locations.index')
                ->set('type', LocationType::Province->value)
                ->assertSee('Provincia');

            expect($component->get('tree'))->toHaveCount(1);
            expect($component->get('chips')->pluck('key')->all())->toBe(['type']);

            $component->call('clearFilter', 'type')->assertSet('type', '');

            expect($component->get('tree'))->toHaveCount(2);
        });

        it('should reset search, filter and sort with clearAll', function (): void {
            Livewire::test('pages::admin.locations.index')
                ->set('search', 'mad')
                ->set('type', LocationType::City->value)
                ->call('sort', 'name')
                ->call('clearAll')
                ->assertSet('search', '')
                ->assertSet('type', '')
                ->assertSet('sortBy', '');
        });
    });
});
