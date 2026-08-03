<?php

declare(strict_types=1);

use App\Enums\LandingStatus;
use App\Enums\LeadStatus;
use App\Enums\LocationType;
use App\Mcp\Servers\ServicesServer;
use App\Mcp\Tools\Blog\CreateBlogPostTool;
use App\Mcp\Tools\Blog\GetBlogPostTool;
use App\Mcp\Tools\Blog\ListBlogPostsTool;
use App\Mcp\Tools\Blog\UpdateBlogPostTool;
use App\Mcp\Tools\Catalog\BulkCreateLandingsTool;
use App\Mcp\Tools\Catalog\CreateCategoryTool;
use App\Mcp\Tools\Catalog\CreateLandingTool;
use App\Mcp\Tools\Catalog\CreateLocationTool;
use App\Mcp\Tools\Catalog\CreateServiceTool;
use App\Mcp\Tools\Catalog\GetLandingTool;
use App\Mcp\Tools\Catalog\GetServiceTool;
use App\Mcp\Tools\Catalog\ListCategoriesTool;
use App\Mcp\Tools\Catalog\ListLandingsTool;
use App\Mcp\Tools\Catalog\ListLocationsTool;
use App\Mcp\Tools\Catalog\ListServicesTool;
use App\Mcp\Tools\Catalog\UpdateCategoryTool;
use App\Mcp\Tools\Catalog\UpdateLandingTool;
use App\Mcp\Tools\Catalog\UpdateLocationTool;
use App\Mcp\Tools\Catalog\UpdateServiceTool;
use App\Mcp\Tools\Leads\ListLeadsTool;
use App\Mcp\Tools\Leads\UpdateLeadStatusTool;
use App\Mcp\Tools\Pages\CreatePageTool;
use App\Mcp\Tools\Pages\GetPageTool;
use App\Mcp\Tools\Pages\ListPagesTool;
use App\Mcp\Tools\Pages\UpdatePageTool;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Landing;
use App\Models\Lead;
use App\Models\Location;
use App\Models\Page;
use App\Models\Service;

describe('ServicesServer', function (): void {
    describe('Categories', function (): void {
        it('should list categories with hierarchy info', function (): void {
            $parent = Category::factory()->create(['name' => 'Alquiler', 'slug' => 'alquiler']);
            Category::factory()->childOf($parent)->create(['name' => 'Generadores', 'slug' => 'alquiler-generadores']);

            ServicesServer::tool(ListCategoriesTool::class, [])
                ->assertOk()
                ->assertSee('Alquiler')
                ->assertSee('alquiler-generadores')
                ->assertSee('"count":2');
        });

        it('should create a category auto-slugging the name', function (): void {
            ServicesServer::tool(CreateCategoryTool::class, [
                'name' => 'Alquiler de generadores',
            ])->assertOk();

            $category = Category::where('slug', 'alquiler-de-generadores')->first();
            expect($category)->not->toBeNull();
            expect($category->name)->toBe('Alquiler de generadores');
        });

        it('should reject duplicate category slugs on create', function (): void {
            Category::factory()->create(['slug' => 'alquiler']);

            ServicesServer::tool(CreateCategoryTool::class, [
                'name' => 'Otro',
                'slug' => 'alquiler',
            ])->assertSee('Validation failed');

            expect(Category::count())->toBe(1);
        });

        it('should update a category', function (): void {
            $category = Category::factory()->create(['name' => 'Old', 'slug' => 'old']);

            ServicesServer::tool(UpdateCategoryTool::class, [
                'id' => $category->id,
                'name' => 'New',
            ])->assertOk();

            expect($category->refresh()->name)->toBe('New');
        });

        it('should reject self-as-own-parent on update', function (): void {
            $category = Category::factory()->create();

            ServicesServer::tool(UpdateCategoryTool::class, [
                'id' => $category->id,
                'parent_id' => $category->id,
            ])->assertSee('Validation failed');
        });

        it('should create a category with an explicit position', function (): void {
            ServicesServer::tool(CreateCategoryTool::class, [
                'name' => 'Andamios',
                'position' => 3,
            ])->assertOk();

            expect(Category::where('slug', 'andamios')->first()->position)->toBe(3);
        });

        it('should update a category position', function (): void {
            $category = Category::factory()->create(['position' => 0]);

            ServicesServer::tool(UpdateCategoryTool::class, [
                'id' => $category->id,
                'position' => 7,
            ])->assertOk();

            expect($category->refresh()->position)->toBe(7);
        });
    });

    describe('Cities', function (): void {
        it('should list locations filtered by type', function (): void {
            Location::factory()->ofType(LocationType::Country)->create(['name' => 'España']);
            Location::factory()->ofType(LocationType::City)->count(2)->create();

            ServicesServer::tool(ListLocationsTool::class, ['type' => 'country'])
                ->assertOk()
                ->assertSee('España')
                ->assertSee('"count":1');
        });

        it('should create a city with type and parent', function (): void {
            $country = Location::factory()->ofType(LocationType::Country)->create();

            ServicesServer::tool(CreateLocationTool::class, [
                'name' => 'Madrid',
                'type' => 'city',
                'parent_id' => $country->id,
            ])->assertOk();

            $madrid = Location::where('slug', 'madrid')->first();
            expect($madrid->parent_id)->toBe($country->id);
            expect($madrid->type)->toBe(LocationType::City);
        });

        it('should update a city', function (): void {
            $location = Location::factory()->create(['name' => 'Old']);

            ServicesServer::tool(UpdateLocationTool::class, [
                'id' => $location->id,
                'name' => 'New',
                'population' => 1000000,
            ])->assertOk();

            $location->refresh();
            expect($location->name)->toBe('New');
            expect($location->population)->toBe(1000000);
        });
    });

    describe('Landings', function (): void {
        it('should list landings with service+city names', function (): void {
            $service = Category::factory()->create(['name' => 'Generadores']);
            $location = Location::factory()->create(['name' => 'Madrid']);
            Landing::factory()->forCategory($service)->inLocation($location)->create();

            ServicesServer::tool(ListLandingsTool::class, [])
                ->assertOk()
                ->assertSee('Generadores')
                ->assertSee('Madrid');
        });

        it('should get a single landing by id with content', function (): void {
            $landing = Landing::factory()->create([
                'title' => 'Custom',
                'content' => ['hero' => ['title' => 'OK']],
            ]);

            ServicesServer::tool(GetLandingTool::class, ['id' => $landing->id])
                ->assertOk()
                ->assertSee('Custom')
                ->assertSee('"hero"');
        });

        it('should create a single landing', function (): void {
            $service = Category::factory()->create(['slug' => 'gen']);
            $location = Location::factory()->create(['slug' => 'mad']);

            ServicesServer::tool(CreateLandingTool::class, [
                'category_id' => $service->id,
                'location_id' => $location->id,
            ])->assertOk();

            expect(Landing::where('slug', 'gen-mad')->exists())->toBeTrue();
        });

        it('should update a landing', function (): void {
            $landing = Landing::factory()->create();

            ServicesServer::tool(UpdateLandingTool::class, [
                'id' => $landing->id,
                'title' => 'Nuevo título',
                'status' => 'draft',
            ])->assertOk();

            $landing->refresh();
            expect($landing->title)->toBe('Nuevo título');
            expect($landing->status)->toBe(LandingStatus::Draft);
        });

        it('should schedule a landing when publish_at is provided', function (): void {
            $landing = Landing::factory()->draft()->create();

            ServicesServer::tool(UpdateLandingTool::class, [
                'id' => $landing->id,
                'publish_at' => now()->addDays(3)->toIso8601String(),
            ])->assertOk();

            $landing->refresh();
            expect($landing->status)->toBe(LandingStatus::Scheduled)
                ->and($landing->publish_at)->not->toBeNull();
        });

        it('should bulk-create landings across a service×city cross-service', function (): void {
            $services = Category::factory()->count(2)->create();
            $locations = Location::factory()->count(3)->create();

            ServicesServer::tool(BulkCreateLandingsTool::class, [
                'category_ids' => $services->pluck('id')->all(),
                'location_ids' => $locations->pluck('id')->all(),
                'include_category_only' => true,
            ])
                ->assertOk()
                ->assertSee('"created":8'); // 2 services × (3 locations + 1 service-only) = 8

            expect(Landing::count())->toBe(8);
        });

        it('should skip existing combinations on bulk-create by default', function (): void {
            $service = Category::factory()->create();
            $location = Location::factory()->create();
            Landing::factory()->forCategory($service)->inLocation($location)->create();

            ServicesServer::tool(BulkCreateLandingsTool::class, [
                'category_ids' => [$service->id],
                'location_ids' => [$location->id],
            ])
                ->assertOk()
                ->assertSee('"created":0')
                ->assertSee('"skipped":1');
        });

        it('should republish existing draft landings when activate_existing=true', function (): void {
            $service = Category::factory()->create();
            $location = Location::factory()->create();
            $landing = Landing::factory()->forCategory($service)->inLocation($location)->draft()->create();

            ServicesServer::tool(BulkCreateLandingsTool::class, [
                'category_ids' => [$service->id],
                'location_ids' => [$location->id],
                'activate_existing' => true,
            ])
                ->assertOk()
                ->assertSee('"reactivated":1');

            expect($landing->refresh()->status)->toBe(LandingStatus::Published);
        });
    });

    describe('Services', function (): void {
        it('should list services with category info', function (): void {
            $category = Category::factory()->create(['name' => 'Generadores diésel']);
            Service::factory()->inCategory($category)->create(['name' => 'SDMO 100 kVA']);

            ServicesServer::tool(ListServicesTool::class, [])
                ->assertOk()
                ->assertSee('SDMO 100 kVA')
                ->assertSee('Generadores diésel');
        });

        it('should get a service including custom_fields', function (): void {
            $service = Service::factory()->create([
                'custom_fields' => ['power_kva' => 100, 'fuel' => 'diesel'],
            ]);

            ServicesServer::tool(GetServiceTool::class, ['id' => $service->id])
                ->assertOk()
                ->assertSee('"power_kva"')
                ->assertSee('100');
        });

        it('should create a service auto-slugging the name', function (): void {
            $category = Category::factory()->create();

            ServicesServer::tool(CreateServiceTool::class, [
                'category_id' => $category->id,
                'name' => 'SDMO 100 kVA insonorizado',
                'custom_fields' => ['power_kva' => 100, 'fuel' => 'diesel', 'autonomy_h' => 14],
            ])->assertOk();

            $service = Service::where('slug', 'sdmo-100-kva-insonorizado')->first();
            expect($service)->not->toBeNull();
            expect($service->custom_fields['power_kva'])->toBe(100);
        });

        it('should reject duplicate slugs on create', function (): void {
            $category = Category::factory()->create();
            Service::factory()->inCategory($category)->create(['slug' => 'sdmo']);

            ServicesServer::tool(CreateServiceTool::class, [
                'category_id' => $category->id,
                'name' => 'Otro',
                'slug' => 'sdmo',
            ])->assertSee('Validation failed');

            expect(Service::count())->toBe(1);
        });

        it('should update a service, replacing custom_fields blob', function (): void {
            $service = Service::factory()->create([
                'custom_fields' => ['power_kva' => 100],
            ]);

            ServicesServer::tool(UpdateServiceTool::class, [
                'id' => $service->id,
                'custom_fields' => ['power_kva' => 250, 'fuel' => 'diesel'],
            ])->assertOk();

            $service->refresh();
            expect($service->custom_fields)->toBe(['power_kva' => 250, 'fuel' => 'diesel']);
        });

        it('should create a service attached to additional categories', function (): void {
            $primary = Category::factory()->create();
            $extra = Category::factory()->create();

            ServicesServer::tool(CreateServiceTool::class, [
                'category_id' => $primary->id,
                'name' => 'Kit andamio',
                'additional_category_ids' => [$extra->id],
            ])->assertOk();

            $service = Service::where('slug', 'kit-andamio')->first();
            expect($service->additionalCategories()->pluck('categories.id')->all())->toBe([$extra->id]);
        });

        it('should sync additional categories on update, excluding the primary one', function (): void {
            $primary = Category::factory()->create();
            $extra = Category::factory()->create();
            $service = Service::factory()->inCategory($primary)->create();

            ServicesServer::tool(UpdateServiceTool::class, [
                'id' => $service->id,
                'additional_category_ids' => [$extra->id, $primary->id],
            ])->assertOk();

            expect($service->additionalCategories()->pluck('categories.id')->all())->toBe([$extra->id]);
        });

        it('should leave additional categories untouched when the field is omitted', function (): void {
            $extra = Category::factory()->create();
            $service = Service::factory()->create();
            $service->additionalCategories()->attach($extra);

            ServicesServer::tool(UpdateServiceTool::class, [
                'id' => $service->id,
                'name' => 'Renombrado',
            ])->assertOk();

            expect($service->additionalCategories()->pluck('categories.id')->all())->toBe([$extra->id]);
        });

        it('should reject non-URL images on create', function (): void {
            $category = Category::factory()->create();

            ServicesServer::tool(CreateServiceTool::class, [
                'category_id' => $category->id,
                'name' => 'Con imagen mala',
                'images' => ['not-a-url'],
            ])->assertSee('Validation failed');

            expect(Service::where('slug', 'con-imagen-mala')->exists())->toBeFalse();
        });

        it('should report failed image downloads without aborting create', function (): void {
            $category = Category::factory()->create();

            ServicesServer::tool(CreateServiceTool::class, [
                'category_id' => $category->id,
                'name' => 'Con imagen rota',
                'images' => ['https://example.invalid/does-not-exist.jpg'],
            ])->assertOk()->assertSee('"images_attached":0');

            $service = Service::where('slug', 'con-imagen-rota')->first();
            expect($service)->not->toBeNull()
                ->and($service->getMedia('gallery'))->toHaveCount(0);
        });
    });

    describe('Blog', function (): void {
        it('should list posts filtered by status', function (): void {
            BlogPost::factory()->create();
            BlogPost::factory()->draft()->count(2)->create();
            BlogPost::factory()->scheduled()->count(3)->create();

            ServicesServer::tool(ListBlogPostsTool::class, ['status' => 'published'])
                ->assertOk()
                ->assertSee('"count":1');

            ServicesServer::tool(ListBlogPostsTool::class, ['status' => 'draft'])
                ->assertOk()
                ->assertSee('"count":2');

            ServicesServer::tool(ListBlogPostsTool::class, ['status' => 'scheduled'])
                ->assertOk()
                ->assertSee('"count":3');
        });

        it('should get a post by slug including the body', function (): void {
            BlogPost::factory()->create([
                'slug' => 'guia-kva',
                'title' => 'Cómo calcular los kVA',
                'body' => '<p>Explicación.</p>',
            ]);

            ServicesServer::tool(GetBlogPostTool::class, ['slug' => 'guia-kva'])
                ->assertOk()
                ->assertSee('Cómo calcular los kVA')
                ->assertSee('Explicaci');
        });

        it('should create a post with tags', function (): void {
            ServicesServer::tool(CreateBlogPostTool::class, [
                'title' => 'Diésel vs gas',
                'body' => '<p>...</p>',
                'tags' => ['seo', 'comparativa'],
            ])->assertOk();

            $post = BlogPost::where('slug', 'diesel-vs-gas')->first();
            expect($post)->not->toBeNull();
            expect($post->tags)->toBe(['seo', 'comparativa']);
        });

        it('should update a post via slug_lookup', function (): void {
            $post = BlogPost::factory()->create(['slug' => 'guia-kva', 'title' => 'Old']);

            ServicesServer::tool(UpdateBlogPostTool::class, [
                'slug_lookup' => 'guia-kva',
                'title' => 'New',
            ])->assertOk();

            expect($post->refresh()->title)->toBe('New');
        });
    });

    describe('Pages', function (): void {
        it('should list pages', function (): void {
            Page::factory()->create(['title' => 'Aviso legal', 'slug' => 'aviso-legal']);

            ServicesServer::tool(ListPagesTool::class, [])
                ->assertOk()
                ->assertSee('Aviso legal')
                ->assertSee('aviso-legal');
        });

        it('should get a page by slug', function (): void {
            Page::factory()->create([
                'slug' => 'aviso-legal',
                'title' => 'Aviso',
                'body' => '<p>texto</p>',
            ]);

            ServicesServer::tool(GetPageTool::class, ['slug' => 'aviso-legal'])
                ->assertOk()
                ->assertSee('Aviso')
                ->assertSee('texto');
        });

        it('should create a page', function (): void {
            ServicesServer::tool(CreatePageTool::class, [
                'title' => 'Aviso legal',
                'body' => '<p>contenido</p>',
            ])->assertOk();

            expect(Page::where('slug', 'aviso-legal')->exists())->toBeTrue();
        });

        it('should update a page by id', function (): void {
            $page = Page::factory()->create(['title' => 'Old']);

            ServicesServer::tool(UpdatePageTool::class, [
                'id' => $page->id,
                'title' => 'New',
            ])->assertOk();

            expect($page->refresh()->title)->toBe('New');
        });

        it('should update a page via slug_lookup', function (): void {
            $page = Page::factory()->create(['slug' => 'aviso-legal', 'title' => 'Old']);

            ServicesServer::tool(UpdatePageTool::class, [
                'slug_lookup' => 'aviso-legal',
                'title' => 'New',
            ])->assertOk();

            expect($page->refresh()->title)->toBe('New');
        });
    });

    describe('Leads', function (): void {
        it('should list leads filtered by status', function (): void {
            Lead::factory()->create();
            Lead::factory()->ofStatus(LeadStatus::Contacted)->count(2)->create();

            ServicesServer::tool(ListLeadsTool::class, ['status' => 'contacted'])
                ->assertOk()
                ->assertSee('"count":2');
        });

        it('should update lead status', function (): void {
            $lead = Lead::factory()->create();

            ServicesServer::tool(UpdateLeadStatusTool::class, [
                'id' => $lead->id,
                'status' => 'qualified',
            ])->assertOk();

            expect($lead->refresh()->status)->toBe(LeadStatus::Qualified);
        });

        it('should reject invalid status', function (): void {
            $lead = Lead::factory()->create();

            ServicesServer::tool(UpdateLeadStatusTool::class, [
                'id' => $lead->id,
                'status' => 'invalid-status',
            ])->assertSee('Validation failed');
        });
    });
});
