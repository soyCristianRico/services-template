<?php

declare(strict_types=1);

use App\Models\Page;
use Illuminate\Database\UniqueConstraintViolationException;

describe('Page', function (): void {
    describe('casts', function (): void {
        it('should cast is_active to boolean', function (): void {
            $page = Page::factory()->inactive()->create();

            expect($page->is_active)->toBeFalse();
        });
    });

    describe('scopes', function (): void {
        it('should scope to active only', function (): void {
            Page::factory()->count(3)->create();
            Page::factory()->inactive()->count(2)->create();

            expect(Page::active()->count())->toBe(3);
        });
    });

    describe('persistence', function (): void {
        it('should enforce unique slugs', function (): void {
            Page::factory()->create(['slug' => 'aviso-legal']);

            expect(fn () => Page::factory()->create(['slug' => 'aviso-legal']))
                ->toThrow(UniqueConstraintViolationException::class);
        });
    });
});
