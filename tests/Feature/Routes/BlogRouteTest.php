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

    describe('/blog pagination', function () {
        it('should show 10 posts on page 1 and the rest on further pages', function () {
            BlogPost::factory()->count(11)->sequence(
                fn ($sequence) => ['published_at' => now()->subMinutes($sequence->index)],
            )->create();

            $ordered = BlogPost::published()->orderByDesc('published_at')->get();
            $page1Posts = $ordered->slice(0, 10);
            $page2Posts = $ordered->slice(10, 10);

            $page1 = $this->get('/blog')->assertOk();
            foreach ($page1Posts as $post) {
                $page1->assertSee($post->title);
            }
            foreach ($page2Posts as $post) {
                $page1->assertDontSee($post->title);
            }

            $page2 = $this->get('/blog?page=2')->assertOk();
            foreach ($page2Posts as $post) {
                $page2->assertSee($post->title);
            }
            foreach ($page1Posts as $post) {
                $page2->assertDontSee($post->title);
            }
        });

        it('should self-canonicalize a paginated page instead of collapsing to page 1', function () {
            BlogPost::factory()->count(11)->create();

            $this->get('/blog?page=2')
                ->assertOk()
                ->assertSee('<link rel="canonical" href="'.route('blog.index').'?page=2">', false);

            $this->get('/blog')
                ->assertOk()
                ->assertSee('<link rel="canonical" href="'.route('blog.index').'">', false);
        });

        it('should emit rel=next/rel=prev links across pages', function () {
            BlogPost::factory()->count(11)->create();

            $this->get('/blog')
                ->assertOk()
                ->assertSee('<link rel="next" href="'.route('blog.index').'?page=2">', false)
                ->assertDontSee('rel="prev"', false);

            $this->get('/blog?page=2')
                ->assertOk()
                ->assertSee('<link rel="prev" href="'.route('blog.index').'">', false)
                ->assertDontSee('rel="next"', false);
        });

        it('should 404 for a page beyond the last one', function () {
            BlogPost::factory()->count(3)->create();

            $this->get('/blog?page=100')->assertNotFound();
        });

        it('should not 404 for page 1 when there is no content at all', function () {
            $this->get('/blog')->assertOk();
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
