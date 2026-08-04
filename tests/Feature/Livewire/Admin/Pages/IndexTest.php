<?php

declare(strict_types=1);

use App\Models\Page;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

describe('Admin\\Pages\\Index', function () {
    describe('rendering', function () {
        it('should render an empty state when no pages exist', function () {
            Livewire::test('pages::admin.pages.index')
                ->assertOk()
                ->assertSee('Crea la primera');
        });

        it('should list pages ordered by title', function () {
            Page::factory()->create(['title' => 'Zaragoza politicas']);
            Page::factory()->create(['title' => 'Aviso legal']);

            $pages = Livewire::test('pages::admin.pages.index')->get('pages');

            expect($pages->first()->title)->toBe('Aviso legal');
        });

        it('should search by title or slug', function () {
            Page::factory()->create(['title' => 'Aviso legal', 'slug' => 'aviso-legal']);
            Page::factory()->create(['title' => 'Política de privacidad', 'slug' => 'privacidad']);

            $pages = Livewire::test('pages::admin.pages.index')
                ->set('search', 'aviso')
                ->get('pages');

            expect($pages)->toHaveCount(1);
        });
    });

    describe('actions', function () {
        it('should toggle is_active', function () {
            $page = Page::factory()->create(['is_active' => true]);

            Livewire::test('pages::admin.pages.index')
                ->call('toggleActive', $page->id);

            expect($page->refresh()->is_active)->toBeFalse();
        });

    });
});
