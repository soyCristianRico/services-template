<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Service;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

describe('Category', function () {
    describe('tree relationships', function () {
        it('should expose parent and children across the hierarchy', function () {
            $rental = Category::factory()->create(['name' => 'Alquiler']);
            $generators = Category::factory()->childOf($rental)->create(['name' => 'Alquiler de generadores']);
            $diesel = Category::factory()->childOf($generators)->create(['name' => 'Alquiler de generadores diésel']);

            expect($diesel->parent->is($generators))->toBeTrue();
            expect($generators->children->pluck('id')->all())->toBe([$diesel->id]);
            expect($rental->children->pluck('id')->all())->toBe([$generators->id]);
        });

        it('should collect descendants recursively', function () {
            $root = Category::factory()->create();
            $mid = Category::factory()->childOf($root)->create();
            $leaf = Category::factory()->childOf($mid)->create();

            expect($root->descendants()->pluck('id')->all())
                ->toEqualCanonicalizing([$mid->id, $leaf->id]);
        });

        it('should return ancestor chain ordered from leaf to root', function () {
            $root = Category::factory()->create(['name' => 'Alquiler']);
            $mid = Category::factory()->childOf($root)->create(['name' => 'Alquiler de generadores']);
            $leaf = Category::factory()->childOf($mid)->create(['name' => 'Alquiler de generadores diésel']);

            expect($leaf->ancestors()->pluck('name')->all())
                ->toBe(['Alquiler de generadores', 'Alquiler']);
        });
    });

    describe('scopes', function () {
        it('should scope to roots only', function () {
            $root = Category::factory()->create();
            Category::factory()->childOf($root)->count(3)->create();

            expect(Category::roots()->count())->toBe(1);
            expect(Category::roots()->first()->id)->toBe($root->id);
        });

        it('should order by position first and name as tie-breaker', function () {
            Category::factory()->create(['name' => 'Zeta', 'position' => 1]);
            Category::factory()->create(['name' => 'Andamios', 'position' => 2]);
            Category::factory()->create(['name' => 'Accesorios', 'position' => 2]);

            expect(Category::ordered()->pluck('name')->all())
                ->toBe(['Zeta', 'Accesorios', 'Andamios']);
        });

        it('should stay alphabetical while every position is the default 0', function () {
            Category::factory()->create(['name' => 'Andamios']);
            Category::factory()->create(['name' => 'Accesorios']);

            expect(Category::ordered()->pluck('name')->all())
                ->toBe(['Accesorios', 'Andamios']);
        });
    });

    describe('deleting', function () {
        /**
         * Que la fila del servicio desaparezca lo garantiza la clave ajena, con
         * gancho o sin él, así que esto NO prueba que el borrado pase por Eloquent
         * —para eso está el test de la foto, que es el único que distingue los dos
         * casos—. Se queda porque el invariante sigue mereciendo un guardián: un
         * servicio nunca sobrevive a su categoría, se llegue por donde se llegue.
         */
        it('should not leave its services behind', function () {
            $category = Category::factory()->create();
            $service = Service::factory()->for($category)->create();

            $category->delete();

            expect(Service::find($service->id))->toBeNull();
        });

        it('should take the photos of its services with it', function () {
            Storage::fake('public');

            $category = Category::factory()->create();
            $service = Service::factory()->for($category)->create();
            $media = $service->addMedia(UploadedFile::fake()->image('foto.jpg'))
                ->toMediaCollection('gallery');

            $path = $media->getPathRelativeToRoot();

            expect(Storage::disk('public')->exists($path))->toBeTrue();

            $category->delete();

            // Se comprueban los BYTES, no la fila. La fila se la lleva la clave
            // ajena de todos modos, así que contar filas no distingue el caso
            // bueno del malo: en el malo Spatie no se entera de nada y la foto se
            // queda en el disco para siempre, que es justo lo que hay que evitar.
            expect(Storage::disk('public')->exists($path))->toBeFalse()
                ->and(Media::count())->toBe(0);
        });

        it('should leave the services of another category alone', function () {
            $category = Category::factory()->create();
            $other = Category::factory()->create();
            $survivor = Service::factory()->for($other)->create();

            $category->delete();

            expect(Service::find($survivor->id))->not->toBeNull();
        });
    });
});
