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

});
