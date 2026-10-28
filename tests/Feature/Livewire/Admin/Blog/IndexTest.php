<?php

declare(strict_types=1);

use App\Models\BlogPost;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

describe('Admin\\Blog\\Index', function () {
    describe('rendering', function () {
        it('should render the empty state when there are no posts', function () {
            Livewire::test('pages::admin.blog.index')
                ->assertOk()
                ->assertSee('No hay artículos con esos filtros');
        });

        it('should list posts ordered by most recently updated', function () {
            $older = BlogPost::factory()->create();
            $older->timestamps = false;
            $older->updated_at = now()->subDay();
            $older->save();

            BlogPost::factory()->create(['title' => 'Más reciente']);

            $first = Livewire::test('pages::admin.blog.index')->get('posts')->first();

            expect($first->title)->toBe('Más reciente');
        });
    });

    describe('filters', function () {
        it('should filter by status=published (active + past publication)', function () {
            BlogPost::factory()->create();
            BlogPost::factory()->draft()->count(2)->create();
            BlogPost::factory()->scheduled()->count(3)->create();

            $posts = Livewire::test('pages::admin.blog.index')
                ->set('status', 'published')
                ->get('posts');

            expect($posts)->toHaveCount(1);
        });

        it('should filter by status=draft (null published_at)', function () {
            BlogPost::factory()->count(2)->create();
            BlogPost::factory()->draft()->count(3)->create();

            $posts = Livewire::test('pages::admin.blog.index')
                ->set('status', 'draft')
                ->get('posts');

            expect($posts)->toHaveCount(3);
        });

        it('should filter by status=scheduled (future published_at)', function () {
            BlogPost::factory()->count(2)->create();
            BlogPost::factory()->scheduled()->count(2)->create();

            $posts = Livewire::test('pages::admin.blog.index')
                ->set('status', 'scheduled')
                ->get('posts');

            expect($posts)->toHaveCount(2);
        });

        it('should search by title substring', function () {
            BlogPost::factory()->create(['title' => 'Diésel vs gas']);
            BlogPost::factory()->create(['title' => 'Cómo calcular kVA']);

            $posts = Livewire::test('pages::admin.blog.index')
                ->set('search', 'diésel')
                ->get('posts');

            expect($posts)->toHaveCount(1);
        });
    });

    describe('sort', function (): void {
        it('should sort by title and flip the direction on a second click', function (): void {
            BlogPost::factory()->create(['title' => 'Beta']);
            BlogPost::factory()->create(['title' => 'Alfa']);

            $component = Livewire::test('pages::admin.blog.index')->call('sort', 'title');

            expect($component->get('posts')->pluck('title')->all())->toBe(['Alfa', 'Beta']);

            $component->call('sort', 'title')->assertSet('sortDirection', 'desc');

            expect($component->get('posts')->pluck('title')->all())->toBe(['Beta', 'Alfa']);
        });

        it('should ignore a column that cannot be sorted', function (): void {
            Livewire::test('pages::admin.blog.index')
                ->call('sort', 'is_active')
                ->assertSet('sortBy', '');
        });
    });

    describe('clearFilter', function (): void {
        it('should show the status as a chip and clear it', function (): void {
            Livewire::test('pages::admin.blog.index')
                ->set('status', 'draft')
                ->assertSee('Borradores')
                ->call('clearFilter', 'status')
                ->assertSet('status', 'all')
                ->set('search', 'algo')
                ->call('clearAll')
                ->assertSet('search', '');
        });
    });
});
