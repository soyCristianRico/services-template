<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileCannotBeAdded;

uses(RefreshDatabase::class);

describe('Service', function () {
    describe('relations', function () {
        it('should belong to a category', function () {
            $category = Category::factory()->create();
            $service = Service::factory()->inCategory($category)->create();

            expect($service->category->id)->toBe($category->id);
        });

        it('should be deleted when its category is deleted (cascade)', function () {
            $category = Category::factory()->create();
            $service = Service::factory()->inCategory($category)->create();

            $category->delete();

            expect(Service::find($service->id))->toBeNull();
        });

        it('should belong to many additional categories', function () {
            $service = Service::factory()->create();
            $extra = Category::factory()->count(2)->create();

            $service->additionalCategories()->sync($extra->pluck('id'));

            expect($service->additionalCategories()->pluck('categories.id')->all())
                ->toEqualCanonicalizing($extra->pluck('id')->all());
        });

        it('should detach an additional category when that category is deleted', function () {
            $service = Service::factory()->create();
            $extra = Category::factory()->create();
            $service->additionalCategories()->attach($extra);

            $extra->delete();

            expect($service->additionalCategories()->count())->toBe(0)
                ->and(Service::find($service->id))->not->toBeNull();
        });
    });

    describe('seo meta', function () {
        it('should store its own meta title and description', function () {
            $service = Service::factory()->create([
                'meta_title' => 'Oposición Administrativo DGA 2026 | Cierzo',
                'meta_description' => 'Prepárate con temario propio y seguimiento semanal.',
            ]);

            $service->refresh();

            expect($service->meta_title)->toBe('Oposición Administrativo DGA 2026 | Cierzo')
                ->and($service->meta_description)->toBe('Prepárate con temario propio y seguimiento semanal.');
        });

        it('should allow a service with no meta of its own', function () {
            $service = Service::factory()->create();

            expect($service->meta_title)->toBeNull()
                ->and($service->meta_description)->toBeNull();
        });
    });

    describe('casts', function () {
        it('should cast custom_fields to array', function () {
            $service = Service::factory()->create([
                'custom_fields' => ['power_kva' => 100, 'fuel' => 'diesel'],
            ]);

            expect($service->custom_fields)->toBeArray()
                ->and($service->custom_fields['power_kva'])->toBe(100);
        });

        it('should cast is_active to boolean', function () {
            $service = Service::factory()->inactive()->create();

            expect($service->is_active)->toBeFalse();
        });
    });

    describe('scopes', function () {
        it('should scope to active only', function () {
            Service::factory()->count(3)->create();
            Service::factory()->inactive()->count(2)->create();

            expect(Service::active()->count())->toBe(3);
        });

        it('should order by position then name', function () {
            $category = Category::factory()->create();
            Service::factory()->inCategory($category)->create(['name' => 'B', 'position' => 1]);
            Service::factory()->inCategory($category)->create(['name' => 'A', 'position' => 2]);
            Service::factory()->inCategory($category)->create(['name' => 'C', 'position' => 0]);

            $names = Service::ordered()->pluck('name')->all();

            expect($names)->toBe(['C', 'B', 'A']);
        });
    });

    describe('customField helper', function () {
        it('should read a nested custom field with default fallback', function () {
            $service = Service::factory()->create([
                'custom_fields' => ['specs' => ['power_kva' => 250]],
            ]);

            expect($service->customField('specs.power_kva'))->toBe(250);
            expect($service->customField('specs.missing', 'fallback'))->toBe('fallback');
        });

        it('should return null when custom_fields is empty', function () {
            $service = Service::factory()->create(['custom_fields' => null]);

            expect($service->customField('whatever'))->toBeNull();
        });
    });

    describe('media gallery', function () {
        it('should attach an image to the gallery collection', function () {
            Storage::fake('public');

            $service = Service::factory()->create();
            $file = UploadedFile::fake()->image('sdmo-100.jpg', 800, 600);

            $service->addMedia($file)
                ->usingFileName('sdmo-100.jpg')
                ->toMediaCollection('gallery');

            expect($service->getMedia('gallery'))->toHaveCount(1);
            expect($service->getFirstMedia('gallery')->file_name)->toBe('sdmo-100.jpg');
        });

        it('should expose a getFirstMediaUrl helper for the gallery', function () {
            Storage::fake('public');

            $service = Service::factory()->create();
            $service->addMedia(UploadedFile::fake()->image('a.jpg'))
                ->toMediaCollection('gallery');

            expect($service->getFirstMediaUrl('gallery'))->not->toBeEmpty();
        });

        it('should reject non-image mime types', function () {
            Storage::fake('public');

            $service = Service::factory()->create();

            expect(fn () => $service->addMedia(UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf'))
                ->toMediaCollection('gallery')
            )->toThrow(FileCannotBeAdded::class);
        });
    });
});
