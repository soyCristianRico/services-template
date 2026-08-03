<?php

declare(strict_types=1);

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

describe('Admin\\Blog\\Edit', function () {
    describe('create', function () {
        it('should auto-fill slug from title and stop once manually edited', function () {
            Livewire::test('pages::admin.blog.edit')
                ->set('form.title', 'Cómo calcular los kVA')
                ->assertSet('form.slug', 'como-calcular-los-kva')
                ->set('form.slug', 'kva-guia')
                ->set('form.title', 'Otro título')
                ->assertSet('form.slug', 'kva-guia');
        });

        it('should persist a new post with tags parsed from CSV', function () {
            Livewire::test('pages::admin.blog.edit')
                ->set('form.title', 'Diésel vs gas')
                ->set('form.slug', 'diesel-vs-gas')
                ->set('form.excerpt', 'Una entradilla.')
                ->set('form.tagsCsv', 'seo, comparativa, generadores')
                ->call('save')
                ->assertRedirect();

            $post = BlogPost::where('slug', 'diesel-vs-gas')->first();
            expect($post)->not->toBeNull();
            expect($post->tags)->toBe(['seo', 'comparativa', 'generadores']);
        });

        it('should reject duplicate slugs', function () {
            BlogPost::factory()->create(['slug' => 'taken']);

            Livewire::test('pages::admin.blog.edit')
                ->set('form.title', 'Otro')
                ->set('form.slug', 'taken')
                ->call('save')
                ->assertHasErrors(['form.slug']);

            expect(BlogPost::count())->toBe(1);
        });

        it('should require a title', function () {
            Livewire::test('pages::admin.blog.edit')
                ->set('form.slug', 'sin-titulo')
                ->call('save')
                ->assertHasErrors(['form.title']);
        });
    });

    describe('edit', function () {
        it('should preload form including tags as CSV', function () {
            $post = BlogPost::factory()->create([
                'title' => 'Diésel vs gas',
                'slug' => 'diesel-vs-gas',
                'tags' => ['seo', 'comparativa'],
            ]);

            Livewire::test('pages::admin.blog.edit', ['post' => $post])
                ->assertSet('form.title', 'Diésel vs gas')
                ->assertSet('form.tagsCsv', 'seo, comparativa');
        });

        it('should update an existing post', function () {
            $post = BlogPost::factory()->create();

            Livewire::test('pages::admin.blog.edit', ['post' => $post])
                ->set('form.title', 'Renombrado')
                ->call('save')
                ->assertHasNoErrors();

            expect($post->refresh()->title)->toBe('Renombrado');
        });
    });

    describe('hero image', function () {
        it('should attach an uploaded hero image', function () {
            Storage::fake('public');
            $post = BlogPost::factory()->create();
            $file = UploadedFile::fake()->image('hero.jpg', 1600, 900);

            Livewire::test('pages::admin.blog.edit', ['post' => $post])
                ->set('newHero', $file);

            expect($post->fresh()->getFirstMediaUrl('hero'))->not->toBeEmpty();
        });

        it('should replace an existing hero (singleFile collection)', function () {
            Storage::fake('public');
            $post = BlogPost::factory()->create();
            $post->addMedia(UploadedFile::fake()->image('first.jpg'))->toMediaCollection('hero');

            Livewire::test('pages::admin.blog.edit', ['post' => $post])
                ->set('newHero', UploadedFile::fake()->image('second.jpg'));

            expect($post->fresh()->getMedia('hero'))->toHaveCount(1);
        });

        it('should delete the hero image', function () {
            Storage::fake('public');
            $post = BlogPost::factory()->create();
            $post->addMedia(UploadedFile::fake()->image('hero.jpg'))->toMediaCollection('hero');

            Livewire::test('pages::admin.blog.edit', ['post' => $post])
                ->call('deleteHero');

            expect($post->fresh()->getMedia('hero'))->toHaveCount(0);
        });
    });
});
