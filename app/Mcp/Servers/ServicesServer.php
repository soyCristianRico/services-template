<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

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
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Services — Content & Leads')]
#[Version('0.3.0')]
#[Instructions(<<<'MARKDOWN'
    Manage the site's DB-driven content from Claude.

    What lives here:
    - **Categories** (catalog tree) — list, create, update.
    - **Services** (catalog items, belong to a Category) — list, get, create, update. Custom attributes live in `custom_fields` (jsonb) and the shape is per-site (kVA/dB/fuel for generators, m³/lockable for containers…).
    - **Locations** (geo tree: country → region → province → city → district) — list, create, update.
    - **Landings** (the public category×location pages) — list, get, create, update, bulk-create.
      Use bulk-create for the typical "I want N landings for these categories × these locations" workflow.
    - **Blog posts** — list, get, create, update. Set published_at in the past to publish, in the future to schedule, null for draft.
    - **Pages** (editable static pages: aviso legal, gracias, sobre nosotros) — list, get, create, update.
    - **Leads** (incoming form submissions) — list, update-status. Leads are NOT created here, only via the public form.

    What does NOT live here:
    - Hardcoded Blade content (home, hero sections, layouts) — edit those in code/git.
    - User accounts, auth, sessions — never exposed.

    Conventions:
    - Slugs are always lowercase + digits + hyphens.
    - Inactive Landings, Services, BlogPosts and Pages return 404 publicly and disappear from the sitemap, but their content is preserved.
    - Setting parent_id to null on a tree node makes it a root.
    MARKDOWN)]
class ServicesServer extends Server
{
    protected array $tools = [
        // Categories
        ListCategoriesTool::class,
        CreateCategoryTool::class,
        UpdateCategoryTool::class,

        // Services
        ListServicesTool::class,
        GetServiceTool::class,
        CreateServiceTool::class,
        UpdateServiceTool::class,

        // Locations
        ListLocationsTool::class,
        CreateLocationTool::class,
        UpdateLocationTool::class,

        // Landings (incl. the bulk hammer)
        ListLandingsTool::class,
        GetLandingTool::class,
        CreateLandingTool::class,
        UpdateLandingTool::class,
        BulkCreateLandingsTool::class,

        // Blog
        ListBlogPostsTool::class,
        GetBlogPostTool::class,
        CreateBlogPostTool::class,
        UpdateBlogPostTool::class,

        // Pages
        ListPagesTool::class,
        GetPageTool::class,
        CreatePageTool::class,
        UpdatePageTool::class,

        // Leads
        ListLeadsTool::class,
        UpdateLeadStatusTool::class,
    ];

    protected array $resources = [];

    protected array $prompts = [];

    /**
     * Drop the geographic tools on sites with no locations, so the agent is not
     * offered tools for a dimension the site does not have.
     */
    protected function boot(): void
    {
        parent::boot();

        if (! config('site.locations')) {
            $this->tools = array_values(array_diff($this->tools, [
                ListLocationsTool::class,
                CreateLocationTool::class,
                UpdateLocationTool::class,
                ListLandingsTool::class,
                GetLandingTool::class,
                CreateLandingTool::class,
                UpdateLandingTool::class,
                BulkCreateLandingsTool::class,
            ]));
        }
    }
}
