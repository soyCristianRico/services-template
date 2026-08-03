<?php

declare(strict_types=1);

use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Landing;
use App\Models\Location;
use App\Models\Page;

describe('Sitemap', function (): void {
    describe('index', function (): void {
        it('should return XML sitemap index with the sub-sitemaps', function (): void {
            $response = $this->get('/sitemap.xml');

            $response->assertOk()
                ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
                ->assertSee('<sitemapindex', false)
                ->assertSee(route('sitemap.pages'), false)
                ->assertSee(route('sitemap.landings'), false);
        });
    });

    describe('pages sub-sitemap', function (): void {
        it('should include the home route', function (): void {
            $this->get('/sitemap-pages.xml')
                ->assertOk()
                ->assertSee('<loc>'.route('home').'</loc>', false);
        });

        it('should include every public marketing page route', function (): void {
            BlogPost::factory()->create();

            $response = $this->get('/sitemap-pages.xml')->assertOk();

            foreach (['home', 'blog.index'] as $name) {
                $response->assertSee('<loc>'.route($name).'</loc>', false);
            }
        });

        it('should include /blog when there are published posts', function (): void {
            BlogPost::factory()->create();

            $this->get('/sitemap-pages.xml')
                ->assertOk()
                ->assertSee('<loc>'.route('blog.index').'</loc>', false);
        });

        it('should NOT include /blog when there are no published posts', function (): void {
            $content = $this->get('/sitemap-pages.xml')->assertOk()->getContent();

            expect($content)->not->toContain('<loc>'.route('blog.index').'</loc>');
        });

        it('should NOT include package or framework routes', function (): void {
            $content = $this->get('/sitemap-pages.xml')->assertOk()->getContent();

            expect($content)
                ->not->toContain('sanctum/csrf-cookie')
                ->not->toContain('/flux/')
                ->not->toContain('/livewire')
                ->not->toContain('/mcp/')
                ->not->toContain('/api/')
                ->not->toContain('<loc>'.url('/up').'</loc>');
        });

        it('should include active Pages from the CMS', function (): void {
            Page::factory()->create(['slug' => 'aviso-legal']);
            Page::factory()->inactive()->create(['slug' => 'borrador']);

            $response = $this->get('/sitemap-pages.xml');

            $response->assertOk()
                ->assertSee('<loc>'.url('/aviso-legal').'</loc>', false);
            expect($response->getContent())->not->toContain('/borrador');
        });

        it('should NOT include auth routes (login, password.*)', function (): void {
            $response = $this->get('/sitemap-pages.xml');

            $response->assertOk();
            expect($response->getContent())->not->toContain(route('login'));
            expect($response->getContent())->not->toContain(route('password.request'));
        });

        it('should NOT include the admin dashboard route', function (): void {
            $response = $this->get('/sitemap-pages.xml');

            $response->assertOk();
            expect($response->getContent())->not->toContain('/admin');
        });

        it('should NOT include the sitemap routes themselves', function (): void {
            $response = $this->get('/sitemap-pages.xml');

            $response->assertOk();
            expect($response->getContent())->not->toContain('/sitemap-pages.xml');
            expect($response->getContent())->not->toContain('/sitemap-landings.xml');
            expect($response->getContent())->not->toContain('/sitemap.xml');
        });
    });

    describe('landings sub-sitemap', function (): void {
        it('should include only published landings', function (): void {
            $service = Category::factory()->create(['slug' => 'alquiler-generadores']);
            $location = Location::factory()->create(['slug' => 'madrid']);

            Landing::factory()->forCategory($service)->inLocation($location)->create();
            Landing::factory()->forCategory($service)->draft()->create(['slug' => 'inactive-landing']);

            $response = $this->get('/sitemap-landings.xml');

            $response->assertOk()
                ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
                ->assertSee('<loc>'.url('/alquiler-generadores-madrid').'</loc>', false);

            expect($response->getContent())->not->toContain('inactive-landing');
        });

        it('should emit lastmod for each landing', function (): void {
            $service = Category::factory()->create(['slug' => 'alquiler-generadores']);
            $landing = Landing::factory()->forCategory($service)->create();

            $this->get('/sitemap-landings.xml')
                ->assertOk()
                ->assertSee('<lastmod>'.$landing->updated_at?->toIso8601String().'</lastmod>', false);
        });

        it('should return an empty urlset when there are no landings', function (): void {
            $this->get('/sitemap-landings.xml')
                ->assertOk()
                ->assertSee('<urlset', false);
        });
    });

    describe('blog sub-sitemap', function (): void {
        it('should include published posts at /blog/{slug}', function (): void {
            BlogPost::factory()->create(['slug' => 'kva-guia']);

            $this->get('/sitemap-blog.xml')
                ->assertOk()
                ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
                ->assertSee('<loc>'.url('/blog/kva-guia').'</loc>', false);
        });

        it('should exclude drafts and scheduled posts', function (): void {
            BlogPost::factory()->draft()->create(['slug' => 'borrador']);
            BlogPost::factory()->scheduled()->create(['slug' => 'futuro']);

            $response = $this->get('/sitemap-blog.xml');

            $response->assertOk();
            expect($response->getContent())->not->toContain('/blog/borrador');
            expect($response->getContent())->not->toContain('/blog/futuro');
        });

        it('should appear in the sitemap index when there are published posts', function (): void {
            BlogPost::factory()->create();

            $this->get('/sitemap.xml')
                ->assertOk()
                ->assertSee(route('sitemap.blog'), false);
        });

        it('should NOT appear in the sitemap index when there are no published posts', function (): void {
            $content = $this->get('/sitemap.xml')->assertOk()->getContent();

            expect($content)->not->toContain(route('sitemap.blog'));
        });
    });
});
