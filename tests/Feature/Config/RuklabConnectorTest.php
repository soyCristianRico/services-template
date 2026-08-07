<?php

declare(strict_types=1);

use App\Models\BlogPost;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Testing\File;
use Ruklab\Connector\Content\ContentType;

describe('Ruklab connector config', function () {
    describe('types', function () {
        it('declares only models this site actually has', function () {
            foreach (config('ruklab.types') as $name => $type) {
                expect($type)->toBeInstanceOf(ContentType::class);
                expect($type->exists())->toBeTrue("El tipo «{$name}» apunta a un modelo que no existe.");
            }
        });

        it('maps only columns its model will accept writing', function () {
            // A field declared here and missing from the model's $fillable is
            // dropped by fill() without a word: the write answers 200, reports
            // the field as changed, and changes nothing.
            foreach (config('ruklab.types') as $name => $type) {
                $columns = array_values($type->fields);

                if ($type->status !== null) {
                    $columns[] = $type->status;
                }

                $rejected = $type->unfillableColumns(array_flip($columns));

                expect($rejected)->toBe([], "El tipo «{$name}» mapea columnas que su modelo no acepta: ".implode(', ', $rejected));
            }
        });

        it('maps only columns the table really has', function () {
            foreach (config('ruklab.types') as $name => $type) {
                $model = $type->newModel();
                $table = $model->getTable();

                foreach ($type->fields as $field => $column) {
                    expect(Schema::hasColumn($table, $column))
                        ->toBeTrue("El tipo «{$name}» mapea {$field} a {$table}.{$column}, que no existe.");
                }

                if ($type->status !== null) {
                    expect(Schema::hasColumn($table, $type->status))->toBeTrue();
                }
            }
        });

        it('never offers to write a column that holds a structure', function () {
            // A landing's body is a tree of blocks. Ruk Lab sends text, so
            // writing there would encode the string itself and the page would
            // stop rendering.
            foreach (config('ruklab.types') as $name => $type) {
                foreach ($type->structuredFields() as $field) {
                    expect(in_array($field, $type->writable(), true))->toBeFalse(
                        "El tipo «{$name}» ofrece escribir {$field}, que guarda una estructura.",
                    );
                }
            }
        });

        it('keeps people and money out of reach', function () {
            // Not content, and no assistant has any business editing them.
            $models = array_map(fn (ContentType $type): string => $type->model, array_values(config('ruklab.types')));

            expect($models)->not->toContain(Lead::class);
            expect($models)->not->toContain(User::class);
        });
    });

    describe('menus', function () {
        it('maps columns the menu table really has', function () {
            $menus = config('ruklab.menus');

            expect(class_exists($menus['model']))->toBeTrue();

            /** @var Model $model */
            $model = new $menus['model'];

            foreach ([...array_values($menus['fields']), $menus['status']] as $column) {
                expect(Schema::hasColumn($model->getTable(), $column))
                    ->toBeTrue("El menú mapea una columna que no existe: {$column}.");
            }
        });
    });

    describe('credentials', function () {
        it('exposes nothing when no token is configured', function () {
            // A site with no credential is a site nobody connected on purpose.
            config()->set('ruklab.token', null);

            $this->getJson('/ruklab/v1/info')
                ->assertServiceUnavailable()
                ->assertJsonPath('message', 'El conector de Ruk Lab no está configurado en esta web.');
        });

        it('turns away a caller with the wrong token', function () {
            config()->set('ruklab.token', 'el-bueno');

            $this->getJson('/ruklab/v1/info', ['Authorization' => 'Bearer el-malo'])
                ->assertUnauthorized();
        });

        it('starts read-only', function () {
            expect(config('ruklab.writes_enabled'))->toBeFalse();
        });
    });
});

describe('Ruklab connector media', function () {
    beforeEach(function () {
        config()->set('ruklab.token', 'el-bueno');
        config()->set('ruklab.writes_enabled', true);
    });

    it('declares only collections the model actually registers', function () {
        // A collection named here and missing from the model would be created
        // on the fly by medialibrary, with none of the conversions the site
        // expects, and the hero would render at its original weight.
        foreach (config('ruklab.types') as $name => $type) {
            if ($type->media === []) {
                continue;
            }

            $registered = $type->newModel()->getRegisteredMediaCollections()->pluck('name')->all();

            foreach ($type->media as $ourName => $collection) {
                expect(in_array($collection, $registered, true))->toBeTrue(
                    "El tipo «{$name}» declara la imagen «{$ourName}» en la colección «{$collection}», que el modelo no registra.",
                );
            }
        }
    });

    it('stores an uploaded image in the hero collection', function () {
        $post = BlogPost::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer el-bueno')
            ->post("/ruklab/v1/content/post/{$post->id}/media", [
                'name' => 'featured',
                'file' => File::image('portada.jpg', 1200, 630),
            ]);

        $response->assertOk()->assertJsonPath('collection', 'hero');

        expect($post->fresh()->getFirstMedia('hero'))->not->toBeNull();
    });

    it('replaces the image instead of piling a second one on', function () {
        // Publishing the same article twice must not leave the site with two
        // heroes and no way to say which one wins.
        $post = BlogPost::factory()->create();

        foreach (['una.jpg', 'otra.jpg'] as $name) {
            $this->withHeader('Authorization', 'Bearer el-bueno')
                ->post("/ruklab/v1/content/post/{$post->id}/media", [
                    'name' => 'featured',
                    'file' => File::image($name, 1200, 630),
                ])->assertOk();
        }

        expect($post->fresh()->getMedia('hero'))->toHaveCount(1);
    });

    it('refuses an image name this type does not declare', function () {
        $post = BlogPost::factory()->create();

        $this->withHeader('Authorization', 'Bearer el-bueno')
            ->post("/ruklab/v1/content/post/{$post->id}/media", [
                'name' => 'inventada',
                'file' => File::image('portada.jpg'),
            ])->assertForbidden();
    });

    it('refuses something that is not an image', function () {
        $post = BlogPost::factory()->create();

        $this->withHeader('Authorization', 'Bearer el-bueno')
            ->post("/ruklab/v1/content/post/{$post->id}/media", [
                'name' => 'featured',
                'file' => File::create('factura.pdf', 10, 'application/pdf'),
            ])->assertStatus(422);
    });

    it('refuses to touch anything on a read-only site', function () {
        config()->set('ruklab.writes_enabled', false);

        $post = BlogPost::factory()->create();

        $this->withHeader('Authorization', 'Bearer el-bueno')
            ->post("/ruklab/v1/content/post/{$post->id}/media", [
                'name' => 'featured',
                'file' => File::image('portada.jpg'),
            ])->assertForbidden();

        expect($post->fresh()->getFirstMedia('hero'))->toBeNull();
    });

    it('shows the image when reading the article back', function () {
        $post = BlogPost::factory()->create();

        $this->withHeader('Authorization', 'Bearer el-bueno')
            ->post("/ruklab/v1/content/post/{$post->id}/media", [
                'name' => 'featured',
                'file' => File::image('portada.jpg'),
            ])->assertOk();

        $this->withHeader('Authorization', 'Bearer el-bueno')
            ->getJson("/ruklab/v1/content/post/{$post->id}")
            ->assertOk()
            ->assertJsonPath('images.featured', fn (?string $url): bool => is_string($url) && $url !== '');
    });
});
