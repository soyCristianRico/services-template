<?php

declare(strict_types=1);

use App\Models\BlogPost;
use Livewire\Livewire;
use Tests\Support\Livewire\BlogPostFormTestComponent;

describe('BlogPostForm', function (): void {
    describe('setPost', function (): void {
        it('should load the post with its tags as CSV', function (): void {
            $post = BlogPost::factory()->create(['title' => 'Guía', 'tags' => ['seo', 'guía']]);

            Livewire::test(BlogPostFormTestComponent::class, ['post' => $post])
                ->assertSet('form.id', $post->id)
                ->assertSet('form.title', 'Guía')
                ->assertSet('form.tagsCsv', 'seo, guía');
        });
    });

    describe('save', function (): void {
        it('should create a post with tags parsed from CSV', function (): void {
            Livewire::test(BlogPostFormTestComponent::class)
                ->set('form.title', 'Guía')
                ->set('form.slug', 'guia')
                ->set('form.tagsCsv', 'seo, , guía')
                ->call('save')
                ->assertHasNoErrors();

            expect(BlogPost::firstWhere('slug', 'guia')->tags)->toBe(['seo', 'guía']);
        });

        it('should refuse a slug with spaces', function (): void {
            Livewire::test(BlogPostFormTestComponent::class)
                ->set('form.title', 'Guía')
                ->set('form.slug', 'Con Espacios')
                ->call('save')
                ->assertHasErrors(['form.slug']);
        });
    });

    describe('rename', function (): void {
        it('should save only the title', function (): void {
            $post = BlogPost::factory()->create(['title' => 'Viejo', 'excerpt' => 'Antes']);

            Livewire::test(BlogPostFormTestComponent::class, ['post' => $post])
                ->set('form.title', 'Nuevo')
                ->set('form.excerpt', 'Sin guardar')
                ->call('rename')
                ->assertHasNoErrors();

            expect($post->fresh())
                ->title->toBe('Nuevo')
                ->excerpt->toBe('Antes');
        });

        it('should refuse an empty title', function (): void {
            $post = BlogPost::factory()->create(['title' => 'Viejo']);

            Livewire::test(BlogPostFormTestComponent::class, ['post' => $post])
                ->set('form.title', '')
                ->call('rename')
                ->assertHasErrors(['form.title']);

            expect($post->fresh()->title)->toBe('Viejo');
        });
    });
});
