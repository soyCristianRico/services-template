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

    describe('actions', function () {
        it('should delete a location and demote its children to roots', function () {
            $parent = Location::factory()->create();
            $child = Location::factory()->childOf($parent)->create();

            Livewire::test('pages::admin.locations.index')
                ->call('deleteLocation', $parent->id);

            expect(Location::find($parent->id))->toBeNull();
            expect(Location::find($child->id)->parent_id)->toBeNull();
        });
    });
});
