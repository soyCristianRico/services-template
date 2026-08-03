<?php

declare(strict_types=1);

use App\Models\Category;

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
});
