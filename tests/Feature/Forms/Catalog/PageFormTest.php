<?php

declare(strict_types=1);

use App\Models\Page;
use Livewire\Livewire;
use Tests\Support\Livewire\PageFormTestComponent;

describe('PageForm', function (): void {
    describe('setPage', function (): void {
        it('should load the page', function (): void {
            $page = Page::factory()->create(['title' => 'Aviso legal', 'slug' => 'aviso-legal']);

            Livewire::test(PageFormTestComponent::class, ['page' => $page])
                ->assertSet('form.id', $page->id)
                ->assertSet('form.title', 'Aviso legal')
                ->assertSet('form.slug', 'aviso-legal');
        });
    });

    describe('save', function (): void {
        it('should create an active page', function (): void {
            Livewire::test(PageFormTestComponent::class)
                ->set('form.title', 'Aviso legal')
                ->set('form.slug', 'aviso-legal')
                ->call('save')
                ->assertHasNoErrors();

            expect(Page::firstWhere('slug', 'aviso-legal')->is_active)->toBeTrue();
        });

        it('should refuse a duplicate slug', function (): void {
            Page::factory()->create(['slug' => 'aviso-legal']);

            Livewire::test(PageFormTestComponent::class)
                ->set('form.title', 'Otro')
                ->set('form.slug', 'aviso-legal')
                ->call('save')
                ->assertHasErrors(['form.slug']);
        });
    });

    describe('rename', function (): void {
        it('should save only the title', function (): void {
            $page = Page::factory()->create(['title' => 'Viejo', 'meta_title' => 'Antes']);

            Livewire::test(PageFormTestComponent::class, ['page' => $page])
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

            Livewire::test(PageFormTestComponent::class, ['page' => $page])
                ->set('form.title', '')
                ->call('rename')
                ->assertHasErrors(['form.title']);

            expect($page->fresh()->title)->toBe('Viejo');
        });
    });
});
