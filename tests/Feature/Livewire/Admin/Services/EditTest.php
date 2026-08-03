<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

describe('Admin\\Services\\Edit', function () {
    describe('create', function () {
        it('should auto-fill slug from name until user edits the slug', function () {
            $category = Category::factory()->create();

            Livewire::test('pages::admin.services.edit')
                ->set('form.category_id', $category->id)
                ->set('form.name', 'SDMO 100 kVA')
                ->assertSet('form.slug', 'sdmo-100-kva')
                ->set('form.slug', 'sdmo-100')
                ->set('form.name', 'Otro nombre')
                ->assertSet('form.slug', 'sdmo-100');
        });

        it('should persist a new service with valid input', function () {
            $category = Category::factory()->create();

            Livewire::test('pages::admin.services.edit')
                ->set('form.category_id', $category->id)
                ->set('form.name', 'SDMO 100 kVA')
                ->set('form.slug', 'sdmo-100-kva')
                ->call('save')
                ->assertRedirect();

            $service = Service::where('slug', 'sdmo-100-kva')->first();
            expect($service)->not->toBeNull();
            expect($service->category_id)->toBe($category->id);
        });

        it('should reject duplicate slugs', function () {
            $category = Category::factory()->create();
            Service::factory()->inCategory($category)->create(['slug' => 'sdmo']);

            Livewire::test('pages::admin.services.edit')
                ->set('form.category_id', $category->id)
                ->set('form.name', 'Otro SDMO')
                ->set('form.slug', 'sdmo')
                ->call('save')
                ->assertHasErrors(['form.slug']);

            expect(Service::count())->toBe(1);
        });

        it('should require a category', function () {
            Livewire::test('pages::admin.services.edit')
                ->set('form.name', 'Algo')
                ->set('form.slug', 'algo')
                ->call('save')
                ->assertHasErrors(['form.category_id']);
        });

        it('should accept and store valid JSON custom_fields', function () {
            $category = Category::factory()->create();

            Livewire::test('pages::admin.services.edit')
                ->set('form.category_id', $category->id)
                ->set('form.name', 'SDMO 100 kVA')
                ->set('form.slug', 'sdmo-100-kva')
                ->set('form.customFieldsJson', '{"power_kva": 100, "fuel": "diesel"}')
                ->call('save')
                ->assertHasNoErrors();

            $service = Service::where('slug', 'sdmo-100-kva')->first();
            expect($service->custom_fields)->toBe(['power_kva' => 100, 'fuel' => 'diesel']);
        });

        it('should reject invalid JSON in custom_fields', function () {
            $category = Category::factory()->create();

            Livewire::test('pages::admin.services.edit')
                ->set('form.category_id', $category->id)
                ->set('form.name', 'SDMO 100 kVA')
                ->set('form.slug', 'sdmo-100-kva')
                ->set('form.customFieldsJson', 'not-json {')
                ->call('save')
                ->assertHasErrors(['form.customFieldsJson']);

            expect(Service::count())->toBe(0);
        });

        it('should reject scalar JSON in custom_fields (must be an object)', function () {
            $category = Category::factory()->create();

            Livewire::test('pages::admin.services.edit')
                ->set('form.category_id', $category->id)
                ->set('form.name', 'SDMO 100 kVA')
                ->set('form.slug', 'sdmo-100-kva')
                ->set('form.customFieldsJson', '42')
                ->call('save')
                ->assertHasErrors(['form.customFieldsJson']);
        });
    });

    describe('edit', function () {
        it('should preload form including pretty-printed customFieldsJson', function () {
            $category = Category::factory()->create();
            $service = Service::factory()->inCategory($category)->create([
                'name' => 'SDMO 100 kVA',
                'slug' => 'sdmo-100-kva',
                'short_description' => 'Generador diésel insonorizado',
                'custom_fields' => ['power_kva' => 100],
            ]);

            $component = Livewire::test('pages::admin.services.edit', ['service' => $service])
                ->assertSet('form.id', $service->id)
                ->assertSet('form.name', 'SDMO 100 kVA')
                ->assertSet('form.slug', 'sdmo-100-kva')
                ->assertSet('form.short_description', 'Generador diésel insonorizado');

            expect($component->get('form.customFieldsJson'))->toContain('"power_kva"');
        });

        it('should update an existing service', function () {
            $service = Service::factory()->create();

            Livewire::test('pages::admin.services.edit', ['service' => $service])
                ->set('form.name', 'Renombrado')
                ->call('save')
                ->assertHasNoErrors();

            expect($service->refresh()->name)->toBe('Renombrado');
        });
    });

    describe('media gallery', function () {
        it('should attach uploaded images to the gallery collection', function () {
            Storage::fake('public');
            $service = Service::factory()->create();
            $file = UploadedFile::fake()->image('sdmo-100.jpg', 800, 600);

            Livewire::test('pages::admin.services.edit', ['service' => $service])
                ->set('newImages', [$file]);

            expect($service->fresh()->getMedia('gallery'))->toHaveCount(1);
        });

        it('should reject non-image files via validation', function () {
            Storage::fake('public');
            $service = Service::factory()->create();
            $file = UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf');

            Livewire::test('pages::admin.services.edit', ['service' => $service])
                ->set('newImages', [$file])
                ->assertHasErrors(['newImages.*']);

            expect($service->fresh()->getMedia('gallery'))->toHaveCount(0);
        });

        it('should delete an image from the gallery', function () {
            Storage::fake('public');
            $service = Service::factory()->create();
            $service->addMedia(UploadedFile::fake()->image('a.jpg'))
                ->usingFileName('a.jpg')
                ->toMediaCollection('gallery');

            $media = $service->getFirstMedia('gallery');

            Livewire::test('pages::admin.services.edit', ['service' => $service])
                ->call('deleteImage', $media->id);

            expect($service->fresh()->getMedia('gallery'))->toHaveCount(0);
        });

        it('should show the no-images-yet callout on a brand-new service (after first save)', function () {
            $service = Service::factory()->create();

            Livewire::test('pages::admin.services.edit', ['service' => $service])
                ->assertSee('Aún no hay imágenes');
        });

        it('should NOT show the uploader before the service is saved', function () {
            Livewire::test('pages::admin.services.edit')
                ->assertSee('Guarda el servicio antes para poder subir imágenes');
        });
    });
});
