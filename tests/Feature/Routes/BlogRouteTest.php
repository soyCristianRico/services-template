<?php

declare(strict_types=1);

use App\Models\BlogPost;

describe('Blog routes', function () {
    describe('/blog (index)', function () {
        it('should return 200 and render the listing page', function () {
            BlogPost::factory()->create(['title' => 'Generadores diésel vs gas']);
            BlogPost::factory()->draft()->create(['title' => 'Borrador secreto']);

            $response = $this->get('/blog');

            $response->assertOk()->assertSee('Generadores diésel vs gas');
            expect($response->getContent())->not->toContain('Borrador secreto');
        });

        it('should render the empty state when there are no published posts', function () {
            BlogPost::factory()->draft()->count(3)->create();

            $this->get('/blog')
                ->assertOk()
                ->assertSee('Aún no hay artículos publicados');
        });

        it('should be noindex when there are no published posts', function () {
            BlogPost::factory()->draft()->create();

            $this->get('/blog')
                ->assertOk()
                ->assertSee('<meta name="robots" content="noindex', false);
        });

        it('should be indexable when there are published posts', function () {
            BlogPost::factory()->create();

            $this->get('/blog')
                ->assertOk()
                ->assertSee('<meta name="robots" content="index, follow', false);
        });
    });

    describe('/blog/{slug} (show)', function () {
        it('should return 200 for a published post', function () {
            BlogPost::factory()->create([
                'slug' => 'generadores-diesel-vs-gas',
                'title' => 'Generadores diésel vs gas',
                'body' => '<p>Comparativa.</p>',
            ]);

            $this->get('/blog/generadores-diesel-vs-gas')
                ->assertOk()
                ->assertSee('Generadores diésel vs gas')
                ->assertSee('Comparativa.', false);
        });

        it('should 404 for a draft post', function () {
            BlogPost::factory()->draft()->create(['slug' => 'borrador']);

            $this->get('/blog/borrador')->assertNotFound();
        });

        it('should 404 for a scheduled (future) post', function () {
            BlogPost::factory()->scheduled()->create(['slug' => 'futuro']);

            $this->get('/blog/futuro')->assertNotFound();
        });

        it('should 404 for an inactive post', function () {
            BlogPost::factory()->inactive()->create(['slug' => 'archivado']);

            $this->get('/blog/archivado')->assertNotFound();
        });
    });

    describe('SEO + Article meta', function () {
        it('should emit article meta + meta description for a published post', function () {
            BlogPost::factory()->create([
                'slug' => 'guia-kva',
                'title' => 'Cómo calcular los kVA que necesitas',
                'excerpt' => 'Una explicación corta y útil.',
            ]);

            $response = $this->get('/blog/guia-kva');

            $response->assertOk()
                ->assertSee('<meta name="description" content="Una explicación corta y útil.', false)
                ->assertSee('og:type', false);
        });
    });
});
