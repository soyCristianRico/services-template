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
    describe('save', function (): void {
        it('should stay on the post after saving changes', function (): void {
            $post = BlogPost::factory()->create(['excerpt' => 'Antes']);

            Livewire::test('pages::admin.blog.edit', ['post' => $post])
                ->set('form.excerpt', 'Después')
                ->call('save')
                ->assertHasNoErrors()
                ->assertNoRedirect();

            expect($post->fresh()->excerpt)->toBe('Después');
        });

        it('should open the new post after creating it', function (): void {
            Livewire::test('pages::admin.blog.edit')
                ->set('form.title', 'Nuevo')
                ->set('form.slug', 'nuevo')
                ->call('save')
                ->assertHasNoErrors()
                ->assertRedirect(route('admin.blog.edit', BlogPost::firstWhere('slug', 'nuevo')));
        });

        it('should jump to the tab of the first field with an error', function (string $field, mixed $value, string $tab): void {
            $post = BlogPost::factory()->create();

            Livewire::test('pages::admin.blog.edit', ['post' => $post])
                ->set('tab', 'image')
                ->set($field, $value)
                ->call('save')
                ->assertHasErrors([$field])
                ->assertSet('tab', $tab);
        })->with([
            'content' => ['form.slug', 'Con Espacios', 'content'],
            'publication' => ['form.published_at', 'no es fecha', 'publication'],
            'seo' => ['form.meta_title', str_repeat('a', 201), 'seo'],
        ]);
    });

    describe('rename', function (): void {
        it('should save only the title edited in the heading', function (): void {
            $post = BlogPost::factory()->create(['title' => 'Viejo', 'excerpt' => 'Antes']);

            Livewire::test('pages::admin.blog.edit', ['post' => $post])
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

            Livewire::test('pages::admin.blog.edit', ['post' => $post])
                ->set('form.title', '')
                ->call('rename')
                ->assertHasErrors(['form.title']);

            expect($post->fresh()->title)->toBe('Viejo');
        });
    });

    describe('record_actions', function (): void {
        it('should delete a post', function () {
            $post = BlogPost::factory()->create();

            Livewire::test('pages::admin.blog.edit', ['post' => $post])
                ->call('delete')
                ->assertRedirect(route('admin.blog.index'));

            expect(BlogPost::find($post->id))->toBeNull();
        });
    });
});
