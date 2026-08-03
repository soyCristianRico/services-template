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
});
