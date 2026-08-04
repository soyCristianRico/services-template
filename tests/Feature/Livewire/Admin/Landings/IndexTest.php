<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Landing;
use App\Models\Location;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

describe('Admin\\Landings\\Index', function (): void {
    describe('rendering', function (): void {
        it('should render an empty state when no landings match the filters', function (): void {
            Livewire::test('pages::admin.landings.index')
                ->assertOk()
                ->assertSee('No hay landings con esos filtros');
        });

        it('should list landings ordered by most recently updated', function (): void {
            $serviceA = Category::factory()->create();
            $serviceB = Category::factory()->create();

            $older = Landing::factory()->forCategory($serviceA)->create(['slug' => 'older']);
            $older->timestamps = false;
            $older->updated_at = now()->subDay();
            $older->save();

            Landing::factory()->forCategory($serviceB)->create(['slug' => 'newer']);

            $component = Livewire::test('pages::admin.landings.index')->assertOk();
            $first = $component->get('landings')->first();

            expect($first->slug)->toBe('newer');
        });
    });

    describe('filters', function (): void {
        it('should filter by category_id', function (): void {
            $serviceA = Category::factory()->create();
            $serviceB = Category::factory()->create();
            $locationX = Location::factory()->create();
            $locationY = Location::factory()->create();

            Landing::factory()->forCategory($serviceA)->inLocation($locationX)->create();
            Landing::factory()->forCategory($serviceB)->inLocation($locationX)->create();
            Landing::factory()->forCategory($serviceB)->inLocation($locationY)->create();

            $landings = Livewire::test('pages::admin.landings.index')
                ->set('categoryId', $serviceA->id)
                ->get('landings');

            expect($landings)->toHaveCount(1);
            expect($landings->first()->category_id)->toBe($serviceA->id);
        });

        it('should filter by location_id', function (): void {
            $service = Category::factory()->create();
            $location = Location::factory()->create();
            Landing::factory()->forCategory($service)->inLocation($location)->create();
            Landing::factory()->forCategory($service)->create(); // no city

            $landings = Livewire::test('pages::admin.landings.index')
                ->set('locationId', $location->id)
                ->get('landings');

            expect($landings)->toHaveCount(1);
            expect($landings->first()->location_id)->toBe($location->id);
        });

        it('should filter to only published', function (): void {
            $service = Category::factory()->create();
            $locations = Location::factory()->count(5)->create();

            Landing::factory()->forCategory($service)->inLocation($locations[0])->create();
            Landing::factory()->forCategory($service)->inLocation($locations[1])->create();
            Landing::factory()->forCategory($service)->inLocation($locations[2])->draft()->create();
            Landing::factory()->forCategory($service)->inLocation($locations[3])->draft()->create();
            Landing::factory()->forCategory($service)->inLocation($locations[4])->scheduled()->create();

            $landings = Livewire::test('pages::admin.landings.index')
                ->set('status', 'published')
                ->get('landings');

            expect($landings)->toHaveCount(2);
        });

        it('should filter to only scheduled', function (): void {
            $service = Category::factory()->create();
            $locations = Location::factory()->count(3)->create();

            Landing::factory()->forCategory($service)->inLocation($locations[0])->scheduled()->create();
            Landing::factory()->forCategory($service)->inLocation($locations[1])->create();
            Landing::factory()->forCategory($service)->inLocation($locations[2])->draft()->create();

            $landings = Livewire::test('pages::admin.landings.index')
                ->set('status', 'scheduled')
                ->get('landings');

            expect($landings)->toHaveCount(1);
        });

        it('should search by slug substring', function (): void {
            $service = Category::factory()->create();
            Landing::factory()->forCategory($service)->create(['slug' => 'alquiler-generadores-madrid']);
            Landing::factory()->forCategory($service)->create(['slug' => 'alquiler-generadores-en-barcelona']);

            $landings = Livewire::test('pages::admin.landings.index')
                ->set('search', 'madrid')
                ->get('landings');

            expect($landings)->toHaveCount(1);
            expect($landings->first()->slug)->toContain('madrid');
        });
    });

});
