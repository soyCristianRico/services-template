<?php

declare(strict_types=1);

use App\Models\Page;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

describe('Admin\\Pages\\Edit', function () {
    describe('create', function () {
        it('should auto-fill slug from title until user edits the slug', function () {
            Livewire::test('pages::admin.pages.edit')
                ->set('form.title', 'Aviso Legal')
                ->assertSet('form.slug', 'aviso-legal')
                ->set('form.slug', 'legal')
                ->set('form.title', 'Otra cosa')
                ->assertSet('form.slug', 'legal');
        });

        it('should persist a new page', function () {
            Livewire::test('pages::admin.pages.edit')
                ->set('form.title', 'Aviso legal')
                ->set('form.slug', 'aviso-legal')
                ->set('form.body', '<p>Texto del aviso legal.</p>')
                ->call('save')
                ->assertRedirect();

            $page = Page::where('slug', 'aviso-legal')->first();
            expect($page)->not->toBeNull();
            expect($page->title)->toBe('Aviso legal');
            expect($page->body)->toBe('<p>Texto del aviso legal.</p>');
            expect($page->is_active)->toBeTrue();
        });

        it('should reject duplicate slugs', function () {
            Page::factory()->create(['slug' => 'aviso-legal']);

            Livewire::test('pages::admin.pages.edit')
                ->set('form.title', 'Otro aviso')
                ->set('form.slug', 'aviso-legal')
                ->call('save')
                ->assertHasErrors(['form.slug']);

            expect(Page::count())->toBe(1);
        });

        it('should require a title', function () {
            Livewire::test('pages::admin.pages.edit')
                ->set('form.slug', 'algo')
                ->call('save')
                ->assertHasErrors(['form.title']);
        });
    });

    describe('edit', function () {
        it('should preload form when mounted with an existing page', function () {
            $page = Page::factory()->create([
                'slug' => 'aviso-legal',
                'title' => 'Aviso legal',
                'body' => '<p>contenido</p>',
                'meta_title' => 'Aviso',
                'meta_description' => 'desc',
            ]);

            Livewire::test('pages::admin.pages.edit', ['page' => $page])
                ->assertSet('form.id', $page->id)
                ->assertSet('form.slug', 'aviso-legal')
                ->assertSet('form.title', 'Aviso legal')
                ->assertSet('form.body', '<p>contenido</p>')
                ->assertSet('form.meta_title', 'Aviso')
                ->assertSet('form.meta_description', 'desc');
        });

        it('should update an existing page', function () {
            $page = Page::factory()->create();

            Livewire::test('pages::admin.pages.edit', ['page' => $page])
                ->set('form.title', 'Título actualizado')
                ->call('save')
                ->assertHasNoErrors();

            expect($page->refresh()->title)->toBe('Título actualizado');
        });
    });
    describe('save', function (): void {
        it('should stay on the page after saving changes', function (): void {
            $page = Page::factory()->create(['meta_title' => 'Antes']);

            Livewire::test('pages::admin.pages.edit', ['page' => $page])
                ->set('form.meta_title', 'Después')
                ->call('save')
                ->assertHasNoErrors()
                ->assertNoRedirect();

            expect($page->fresh()->meta_title)->toBe('Después');
        });

        it('should open the new page after creating it', function (): void {
            Livewire::test('pages::admin.pages.edit')
                ->set('form.title', 'Aviso legal')
                ->set('form.slug', 'aviso-legal')
                ->call('save')
                ->assertRedirect(route('admin.pages.edit', Page::firstWhere('slug', 'aviso-legal')));
        });

        it('should jump to the tab of the first field with an error', function (string $field, mixed $value, string $tab): void {
            $page = Page::factory()->create();

            Livewire::test('pages::admin.pages.edit', ['page' => $page])
                ->set('tab', $tab === 'seo' ? 'content' : 'seo')
                ->set($field, $value)
                ->call('save')
                ->assertHasErrors([$field])
                ->assertSet('tab', $tab);
        })->with([
            'content' => ['form.slug', 'Con Espacios', 'content'],
            'seo' => ['form.meta_description', str_repeat('a', 321), 'seo'],
        ]);
    });

    describe('rename', function (): void {
        it('should save only the title edited in the heading', function (): void {
            $page = Page::factory()->create(['title' => 'Viejo', 'meta_title' => 'Antes']);

            Livewire::test('pages::admin.pages.edit', ['page' => $page])
                ->set('form.title', 'Nuevo')
                ->set('form.meta_title', 'Sin guardar')
                ->call('rename')
                ->assertHasNoErrors();

            expect($page->fresh())
                ->title->toBe('Nuevo')
                ->meta_title->toBe('Antes');
        });

        it('should refuse an empty title', function (): void {
            $page = Page::factory()->create(['title' => 'Viejo']);

            Livewire::test('pages::admin.pages.edit', ['page' => $page])
                ->set('form.title', '')
                ->call('rename')
                ->assertHasErrors(['form.title']);

            expect($page->fresh()->title)->toBe('Viejo');
        });
    });

    describe('record_actions', function (): void {
        it('should delete a page', function () {
            $page = Page::factory()->create();

            Livewire::test('pages::admin.pages.edit', ['page' => $page])
                ->call('delete')
                ->assertRedirect(route('admin.pages.index'));

            expect(Page::find($page->id))->toBeNull();
        });
    });
});
