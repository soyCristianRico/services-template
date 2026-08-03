<?php

declare(strict_types=1);

use App\Models\BlogPost;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;

describe('BlogPost', function () {
    describe('casts', function () {
        it('should cast published_at to datetime', function () {
            $post = BlogPost::factory()->create(['published_at' => '2026-01-15 10:00:00']);

            expect($post->published_at)->toBeInstanceOf(Carbon::class);
        });

        it('should cast tags to array', function () {
            $post = BlogPost::factory()->create([
                'tags' => ['seo', 'long-tail', 'generadores'],
            ]);

            expect($post->tags)->toBe(['seo', 'long-tail', 'generadores']);
        });

        it('should cast is_active to boolean', function () {
            $post = BlogPost::factory()->inactive()->create();

            expect($post->is_active)->toBeFalse();
        });
    });

    describe('published scope', function () {
        it('should include active posts with published_at in the past', function () {
            BlogPost::factory()->create();

            expect(BlogPost::published()->count())->toBe(1);
        });

        it('should exclude drafts (published_at null)', function () {
            BlogPost::factory()->draft()->create();

            expect(BlogPost::published()->count())->toBe(0);
        });

        it('should exclude scheduled posts (published_at in the future)', function () {
            BlogPost::factory()->scheduled()->create();

            expect(BlogPost::published()->count())->toBe(0);
        });

        it('should exclude inactive posts even if published in the past', function () {
            BlogPost::factory()->inactive()->create();

            expect(BlogPost::published()->count())->toBe(0);
        });
    });

    describe('persistence', function () {
        it('should enforce unique slugs', function () {
            BlogPost::factory()->create(['slug' => 'generadores-vs-bombillas']);

            expect(fn () => BlogPost::factory()->create(['slug' => 'generadores-vs-bombillas']))
                ->toThrow(UniqueConstraintViolationException::class);
        });
    });
});
