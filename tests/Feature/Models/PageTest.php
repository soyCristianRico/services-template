<?php

declare(strict_types=1);

use App\Models\Page;
use Illuminate\Database\UniqueConstraintViolationException;

describe('Page', function () {
    describe('casts', function () {
        it('should cast is_active to boolean', function () {
            $page = Page::factory()->inactive()->create();

            expect($page->is_active)->toBeFalse();
        });
    });

    describe('scopes', function () {
        it('should scope to active only', function () {
            Page::factory()->count(3)->create();
            Page::factory()->inactive()->count(2)->create();

            expect(Page::active()->count())->toBe(3);
        });
    });

    describe('persistence', function () {
        it('should enforce unique slugs', function () {
            Page::factory()->create(['slug' => 'aviso-legal']);

            expect(fn () => Page::factory()->create(['slug' => 'aviso-legal']))
                ->toThrow(UniqueConstraintViolationException::class);
        });
    });
});
