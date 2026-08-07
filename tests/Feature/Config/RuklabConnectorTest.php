<?php

declare(strict_types=1);

use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
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
                    expect($type->writable())->not->toContain(
                        $field,
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
