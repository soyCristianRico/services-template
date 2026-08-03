<?php

declare(strict_types=1);

use App\Enums\LocationType;
use App\Models\Location;

describe('City', function () {
    describe('tree relationships', function () {
        it('should expose parent and children belonging to the same tree', function () {
            $country = Location::factory()->ofType(LocationType::Country)->create(['name' => 'España']);
            $region = Location::factory()->childOf($country)->ofType(LocationType::Region)->create(['name' => 'Madrid CCAA']);
            $location = Location::factory()->childOf($region)->ofType(LocationType::City)->create(['name' => 'Madrid']);

            expect($location->parent->is($region))->toBeTrue();
            expect($region->children->pluck('id')->all())->toBe([$location->id]);
            expect($country->children->pluck('id')->all())->toBe([$region->id]);
        });

        it('should expose every descendant recursively', function () {
            $country = Location::factory()->ofType(LocationType::Country)->create();
            $region = Location::factory()->childOf($country)->ofType(LocationType::Region)->create();
            $location = Location::factory()->childOf($region)->ofType(LocationType::City)->create();
            $district = Location::factory()->childOf($location)->ofType(LocationType::District)->create();

            $descendantIds = $country->descendants()->pluck('id')->all();

            expect($descendantIds)->toEqualCanonicalizing([$region->id, $location->id, $district->id]);
        });

        it('should expose ancestors from leaf up to root', function () {
            $country = Location::factory()->ofType(LocationType::Country)->create(['name' => 'España']);
            $region = Location::factory()->childOf($country)->ofType(LocationType::Region)->create(['name' => 'Madrid CCAA']);
            $location = Location::factory()->childOf($region)->ofType(LocationType::City)->create(['name' => 'Madrid']);

            $ancestorNames = $location->ancestors()->pluck('name')->all();

            expect($ancestorNames)->toBe(['Madrid CCAA', 'España']);
        });
    });

    describe('scopes', function () {
        it('should scope to a given type', function () {
            Location::factory()->ofType(LocationType::Country)->count(2)->create();
            Location::factory()->ofType(LocationType::City)->count(3)->create();

            expect(Location::ofType(LocationType::Country)->count())->toBe(2);
            expect(Location::ofType(LocationType::City)->count())->toBe(3);
        });

        it('should scope to roots when no parent', function () {
            $parent = Location::factory()->create();
            Location::factory()->childOf($parent)->count(2)->create();

            expect(Location::roots()->count())->toBe(1);
            expect(Location::roots()->first()->id)->toBe($parent->id);
        });
    });

    describe('casts', function () {
        it('should cast type to LocationType enum', function () {
            $location = Location::factory()->ofType(LocationType::Region)->create();

            expect($location->type)->toBeInstanceOf(LocationType::class);
            expect($location->type)->toBe(LocationType::Region);
        });
    });
});
