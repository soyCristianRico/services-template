<?php

declare(strict_types=1);

use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Landing;
use App\Models\Location;
use App\Models\Page;

describe('LlmsTxt', function (): void {
    describe('index', function (): void {
        it('should respond with the llms.txt structure', function (): void {
            $response = $this->get('/llms.txt');

            $response->assertOk()
                ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
                ->assertSee('# '.config('app.name'), false)
                ->assertSee('> '.config('site.tagline'), false)
                ->assertSee('Sitio: '.route('home'), false)
                ->assertSee('## Páginas', false)
                ->assertSee('- [Home]('.route('home').')', false);
        });

        it('should include CMS pages, landings and blog posts once they exist', function (): void {
            Page::factory()->create(['slug' => 'aviso-legal', 'title' => 'Aviso legal']);

            $category = Category::factory()->create(['slug' => 'alquiler-generadores']);
            $location = Location::factory()->create(['slug' => 'madrid']);
            $landing = Landing::factory()->forCategory($category)->inLocation($location)->create(['title' => 'Generadores en Madrid']);

            $post = BlogPost::factory()->create(['title' => 'Cómo elegir un generador']);

            $response = $this->get('/llms.txt');

            $response->assertOk()
                ->assertSee('- [Aviso legal]('.url('/aviso-legal').')', false)
                ->assertSee('## Aterrizajes', false)
                ->assertSee('- [Generadores en Madrid]('.url('/'.$landing->slug).')', false)
                ->assertSee('## Blog', false)
                ->assertSee('- [Cómo elegir un generador]('.url('/blog/'.$post->slug).')', false);
        });

        it('should omit the Aterrizajes and Blog sections when there is no content', function (): void {
            $content = $this->get('/llms.txt')->assertOk()->getContent();

            expect($content)
                ->not->toContain('## Aterrizajes')
                ->not->toContain('## Blog');
        });

        it('should NOT list itself, the sitemap, the admin dashboard or auth routes', function (): void {
            $content = $this->get('/llms.txt')->assertOk()->getContent();

            expect($content)
                ->not->toContain('/llms.txt')
                ->not->toContain('/sitemap')
                ->not->toContain('/admin')
                ->not->toContain(route('login'));
        });
    });
});
