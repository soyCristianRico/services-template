<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

describe('Admin\\Categories\\Edit', function () {
    describe('create', function () {
        it('should auto-fill slug from name', function () {
            Livewire::test('pages::admin.categories.edit')
                ->set('form.name', 'Alquiler de generadores')
                ->assertSet('form.slug', 'alquiler-de-generadores');
        });

        it('should persist a new category with valid input', function () {
            Livewire::test('pages::admin.categories.edit')
                ->set('form.name', 'Alquiler de generadores')
                ->set('form.slug', 'alquiler-generadores')
                ->call('save')
                ->assertRedirect();

            expect(Category::where('slug', 'alquiler-generadores')->exists())->toBeTrue();
        });

        it('should default position to 0 and persist an explicit order', function () {
            Livewire::test('pages::admin.categories.edit')
                ->assertSet('form.position', 0)
                ->set('form.name', 'Andamios')
                ->set('form.slug', 'andamios')
                ->set('form.position', 3)
                ->call('save')
                ->assertHasNoErrors();

            expect(Category::where('slug', 'andamios')->first()->position)->toBe(3);
        });

        it('should reject a negative position', function () {
            Livewire::test('pages::admin.categories.edit')
                ->set('form.name', 'Andamios')
                ->set('form.slug', 'andamios')
                ->set('form.position', -1)
                ->call('save')
                ->assertHasErrors(['form.position']);
        });

        it('should reject a duplicate slug', function () {
            Category::factory()->create(['slug' => 'alquiler']);

            Livewire::test('pages::admin.categories.edit')
                ->set('form.name', 'Otro')
                ->set('form.slug', 'alquiler')
                ->call('save')
                ->assertHasErrors(['form.slug']);

            expect(Category::count())->toBe(1);
        });
    });

    describe('edit', function () {
        it('should preload form when mounted with a category', function () {
            $parent = Category::factory()->create(['name' => 'Alquiler']);
            $service = Category::factory()->childOf($parent)->create([
                'name' => 'Alquiler de generadores',
                'slug' => 'alquiler-generadores',
                'icon' => 'bolt',
                'position' => 4,
            ]);

            Livewire::test('pages::admin.categories.edit', ['category' => $service])
                ->assertSet('form.id', $service->id)
                ->assertSet('form.name', 'Alquiler de generadores')
                ->assertSet('form.slug', 'alquiler-generadores')
                ->assertSet('form.parent_id', $parent->id)
                ->assertSet('form.icon', 'bolt')
                ->assertSet('form.position', 4);
        });

        it('should update an existing category', function () {
            $service = Category::factory()->create(['name' => 'Alquiler', 'slug' => 'alquiler']);

            Livewire::test('pages::admin.categories.edit', ['category' => $service])
                ->set('form.name', 'Alquiler renombrado')
                ->call('save')
                ->assertHasNoErrors();

            expect($service->refresh()->name)->toBe('Alquiler renombrado');
        });
    });

    describe('parent options', function () {
        it('should exclude self from parent options', function () {
            $service = Category::factory()->create();
            Category::factory()->count(2)->create();

            $options = Livewire::test('pages::admin.categories.edit', ['category' => $service])
                ->get('parentOptions');

            expect($options->pluck('id')->all())->not->toContain($service->id);
            expect($options)->toHaveCount(2);
        });
    });
});
