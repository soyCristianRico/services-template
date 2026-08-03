<?php

declare(strict_types=1);

use App\Enums\LocationType;
use App\Models\Location;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

describe('Admin\\Locations\\Edit', function () {
    describe('create', function () {
        it('should auto-fill slug from name while user has not edited it', function () {
            Livewire::test('pages::admin.locations.edit')
                ->set('form.name', 'Comunidad de Madrid')
                ->assertSet('form.slug', 'comunidad-de-madrid');
        });

        it('should stop auto-syncing slug once user edits it manually', function () {
            Livewire::test('pages::admin.locations.edit')
                ->set('form.name', 'Madrid')
                ->assertSet('form.slug', 'madrid')
                ->set('form.slug', 'madrid-capital')
                ->set('form.name', 'Otra cosa')
                ->assertSet('form.slug', 'madrid-capital');
        });

        it('should persist a new city with valid input', function () {
            Livewire::test('pages::admin.locations.edit')
                ->set('form.name', 'Madrid')
                ->set('form.slug', 'madrid')
                ->set('form.type', LocationType::City->value)
                ->set('form.population', 3300000)
                ->call('save')
                ->assertRedirect();

            expect(Location::where('slug', 'madrid')->exists())->toBeTrue();
        });

        it('should reject a duplicate slug', function () {
            Location::factory()->create(['slug' => 'madrid']);

            Livewire::test('pages::admin.locations.edit')
                ->set('form.name', 'Madrid 2')
                ->set('form.slug', 'madrid')
                ->set('form.type', LocationType::City->value)
                ->call('save')
                ->assertHasErrors(['form.slug']);

            expect(Location::count())->toBe(1);
        });

        it('should reject slugs with uppercase or special chars', function () {
            Livewire::test('pages::admin.locations.edit')
                ->set('form.name', 'Madrid')
                ->set('form.slug', 'Madrid_Capital')
                ->call('save')
                ->assertHasErrors(['form.slug']);
        });
    });

    describe('edit', function () {
        it('should preload form fields when mounted with an existing city', function () {
            $parent = Location::factory()->ofType(LocationType::Region)->create(['name' => 'Madrid CCAA']);
            $location = Location::factory()->childOf($parent)->ofType(LocationType::City)->create([
                'name' => 'Madrid',
                'slug' => 'madrid',
                'population' => 3300000,
                'meta_title' => 'Madrid · capital',
            ]);

            $component = Livewire::test('pages::admin.locations.edit', ['location' => $location]);

            $component
                ->assertSet('form.id', $location->id)
                ->assertSet('form.name', 'Madrid')
                ->assertSet('form.slug', 'madrid')
                ->assertSet('form.parent_id', $parent->id)
                ->assertSet('form.population', 3300000)
                ->assertSet('form.meta_title', 'Madrid · capital');
        });

        it('should update an existing city when saving', function () {
            $location = Location::factory()->create(['name' => 'Madrid', 'slug' => 'madrid']);

            Livewire::test('pages::admin.locations.edit', ['location' => $location])
                ->set('form.name', 'Madrid (actualizado)')
                ->call('save')
                ->assertRedirect();

            expect($location->refresh()->name)->toBe('Madrid (actualizado)');
            expect(Location::where('slug', 'madrid')->count())->toBe(1);
        });

        it('should allow keeping the same slug when editing (unique constraint ignores self)', function () {
            $location = Location::factory()->create(['slug' => 'madrid']);

            Livewire::test('pages::admin.locations.edit', ['location' => $location])
                ->set('form.name', 'Madrid renombrado')
                ->call('save')
                ->assertHasNoErrors();
        });
    });

    describe('parent options', function () {
        it('should exclude self from parent options to prevent loops', function () {
            $location = Location::factory()->create();
            Location::factory()->count(3)->create();

            $options = Livewire::test('pages::admin.locations.edit', ['location' => $location])
                ->get('parentOptions');

            expect($options->pluck('id')->all())->not->toContain($location->id);
            expect($options)->toHaveCount(3);
        });
    });
});
