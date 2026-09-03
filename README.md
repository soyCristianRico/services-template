# services-template

Laravel template to launch rental brokerage sites (generators, containers, pallets, cold storage, portable toilets…). You clone it, customize the visuals, create content from the admin panel or via MCP, and you have a site ready to capture leads from Google.

**What's included**:

- Programmatic pages `/{category}-{location}` with full SEO (title, OG, Twitter, JSON-LD: WebPage + Service@areaServed + BreadcrumbList).
- `/sitemap.xml` with sub-sitemaps (pages, landings, blog) and real `lastmod`.
- AI-agent friendly: any public page returns Markdown instead of HTML when requested with `Accept: text/markdown`, and `/llms.txt` auto-lists the site's current pages, landings and blog posts — both read whatever content exists (name, tagline in `config('site.tagline')`, live routes/DB rows), so they need no wiring after cloning. The `/generate-llms-txt` skill later replaces the auto-generated file with a curated `public/llms.txt` at deploy time, which takes precedence.
- Embedded lead form with honeypot, email notification, and signed (HMAC) webhook to your CRM.
- Livewire + Flux Pro admin: Categories · Services (with gallery) · Locations · Landings (with bulk-activate matrix) · Blog (drafts/scheduled/published) · Static pages · Leads.
- MCP server (`/mcp/services`) with 25 tools to manage everything from Claude.
- 241 Pest tests covering models, routes, admin, and MCP.

Stack: Laravel 12 · Livewire 4 · Flux Pro 2 · Sanctum · Spatie Media Library · Pest 4.

---

## Step-by-step to bootstrap a new site (e.g. `alquilergeneradores-com`)

### 1. Clone the template

```bash
gh repo create ruklab/alquilergeneradores-com --template ruklab/services-template --private
git clone git@github.com:ruklab/alquilergeneradores-com.git
cd alquilergeneradores-com
```

### 2. Install dependencies

```bash
composer install
npm install
```

**Flux Pro**: `composer require livewire/flux-pro` needs your license key. Add a local `auth.json` to the repo (already in `.gitignore`) or use `composer config http-basic.composer.fluxui.dev EMAIL LICENSE_KEY`.

### 3. Configure `.env`

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` with your site-specific values. The variables you **definitely** want to review:

```dotenv
APP_NAME="Alquiler Generadores"
APP_URL=https://alquilergeneradores.com
APP_LOCALE=es

# DB — recommended: MySQL locally and in production (see "DB setup" below for the SQL commands)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=alquilergeneradores
DB_USERNAME=alquilergeneradores
DB_PASSWORD=

# Lead notification — the operator receives email + webhook to CRM
LEAD_NOTIFY_EMAIL=cristian@ruklab.com
LEAD_WEBHOOK_URL=https://crm.ruklab.com/api/leads/incoming
LEAD_WEBHOOK_SECRET=something-long-and-random

# Mail — Mailgun comes pre-installed (driver + symfony/mailgun-mailer). In local, `log` is enough.
MAIL_MAILER=mailgun
MAIL_FROM_ADDRESS=hola@alquilergeneradores.com
MAIL_FROM_NAME="${APP_NAME}"
MAILGUN_DOMAIN=mg.alquilergeneradores.com
MAILGUN_SECRET=key-...
MAILGUN_ENDPOINT=api.eu.mailgun.net   # Europe (GDPR). Use api.mailgun.net for US accounts.

# SEO defaults — optional, but helps Google's JSON-LD
SEO_ORG_NAME="Alquiler Generadores"
SEO_ORG_LOGO=images/logo.png
SEO_ORG_LINKEDIN=https://www.linkedin.com/company/...

# URL slug separator: '' produces /alquiler-generadores-madrid (dense, recommended),
# 'en' produces /alquiler-generadores-en-madrid (more readable). Per site.
LANDING_SLUG_SEPARATOR=

# Google Tag Manager — optional. When set, GTM loads with Consent Mode v2 and the
# GDPR cookie banner appears. Leave empty to disable all tracking + the banner.
GOOGLE_TAG_MANAGER_ID=
COOKIE_POLICY_URL=https://alquilergeneradores.com/cookies
```

> **Cookies & tracking:** the cookie banner and GTM only render when `GOOGLE_TAG_MANAGER_ID`
> is set. It uses Google Consent Mode v2 (tags load in a *denied* state until the user
> accepts). Remember to configure the consent checks in your GTM container so GA4/Pixel
> actually honor the signal. See the components in `resources/views/components/cookies/`.

### 4. DB setup (MySQL)

Enter the MySQL shell as root (works out-of-the-box on Ubuntu/Debian via `auth_socket`):

```bash
sudo mysql
```

Inside the shell, paste line by line (replace `TU_PASSWORD` with the password you'll put in `.env`):

```sql
CREATE DATABASE alquilergeneradores CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE alquilergeneradores_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'alquilergeneradores'@'localhost' IDENTIFIED BY 'TU_PASSWORD';
GRANT ALL PRIVILEGES ON alquilergeneradores.* TO 'alquilergeneradores'@'localhost';
GRANT ALL PRIVILEGES ON alquilergeneradores_testing.* TO 'alquilergeneradores'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

Verify with the new user:

```bash
mysql -u alquilergeneradores -p alquilergeneradores -e "SELECT 1;"
```

The `_testing` DB is used by Pest (configured in `phpunit.xml`) so tests don't wipe your dev data.

### 5. Migrate + seed + storage

```bash
php artisan migrate:fresh --seed
php artisan storage:link   # to serve Spatie images from /storage/...
```

The seeder creates `admin@services.test` / `password`. **Change it** before pushing to production — edit `database/seeders/DatabaseSeeder.php`, or create a real user with:

```bash
php artisan services:create-user --name="Admin" --email=you@domain.com
# Omit --password to be prompted securely. This is the recommended way in production
# (no default credentials committed to the repo).
```

### 6. Start dev

```bash
composer run dev
```

This boots `php artisan serve` + `vite` + `queue:listen` + `pail` (logs) in parallel. Open [http://localhost:8000](http://localhost:8000).

Admin login: [http://localhost:8000/login](http://localhost:8000/login) with `admin@services.test` / `password`.

---

## Visual customization

The template ships with **minimal layouts**. The idea is you rewrite them to taste. What you need to touch:

| File | What you change |
|---|---|
| `resources/css/app.css` | Palette (`@theme`), font (`--font-sans`), CSS variables |
| `resources/views/layouts/public.blade.php` | Site header + footer (logo, nav, copy, social) |
| `resources/views/layouts/auth.blade.php` | Login look (centered, logo on top) |
| `resources/views/pages/⚡home.blade.php` | Home hero, sections, CTAs |
| `resources/views/pages/⚡landing.blade.php` | How programmatic landings look (hero, blocks, where the form goes) |
| `resources/views/pages/blog/⚡index.blade.php` and `⚡show.blade.php` | Blog list and detail |
| `resources/views/components/⚡lead-form/lead-form.blade.php` | Lead form design |

**The admin (`layouts/admin.blade.php` and `pages/admin/**`) — don't touch it** — it's internal, not publicly visible.

### If you want to show products on the landings

By default the public landing does NOT show the product catalog. If you want it (e.g. a grid of available generator models), edit `resources/views/pages/⚡landing.blade.php` and add wherever fits:

```blade
@php($products = \App\Models\Service::active()
    ->where('category_id', $landing->category_id)
    ->ordered()
    ->get())

@if ($products->isNotEmpty())
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        @foreach ($products as $p)
            <div>
                <img src="{{ $p->getFirstMediaUrl('gallery') }}" alt="{{ $p->name }}">
                <h3>{{ $p->name }}</h3>
                <p>{{ $p->short_description }}</p>
                <p>{{ $p->customField('power_kva') }} kVA</p>
            </div>
        @endforeach
    </div>
@endif
```

---

## Content management

You have **two equivalent paths**:

### A) Web admin — `/admin`

Log in with your user. Side sidebar with:

- **Catalog**: Categories (tree) · Services (with image upload to the gallery).
- **SEO**: Locations (tree: country → region → province → city → district) · Landings (list with filters + bulk-activate matrix).
- **Content**: Blog (filterable by status) · Static pages (legal notice, thank-you, policy).
- **Leads**: read-only list + detail view with status change.

The **landings matrix** is the key piece: in `/admin/landings/matrix` you pick N categories × M locations, check the combinations you want to activate, and one click creates them all. Unchecking deactivates (does NOT delete: edited content is preserved).

### B) MCP — from Claude in the terminal

Faster when you want to create things in bulk ("create 80 landings for these 8 categories × these 10 locations").

```bash
# 1. Make sure a user exists (skip if you already seeded / created one)
php artisan services:create-user --name="Admin" --email=you@domain.com
# Omit --password to be prompted securely.

# 2. Generate a Sanctum token for that user
php artisan services:mcp-token --email=you@domain.com
# It prints the token. Copy it (only shown once).

# 3. Connect your MCP client (Claude Desktop, Claude Code, etc.) pointing to:
#    URL:    https://your-domain.com/mcp/services
#    Header: Authorization: Bearer <your-token>
```

The **25 available tools**:

- **Categories**: list, create, update
- **Services**: list, get, create, update
- **Locations**: list, create, update
- **Landings**: list, get, create, update + `bulk-create` (the most useful one)
- **Blog**: list, get, create, update
- **Pages**: list, get, create, update
- **Leads**: list, update-status (not created via MCP — only via the public form)

---

## Tests

```bash
# Run a specific one
php artisan test --compact tests/Feature/Models/ServiceTest.php

# Filter by name
php artisan test --compact --filter="should bulk-create"

# Full suite (heads up: in a single session, not in parallel with another test runner)
php artisan test --compact
```

**Do not run** the full suite with `--parallel` or alongside another process touching the sqlite `:memory:` DB.

---

## Deployment (production)

This is NOT preconfigured — each site picks its infra. Recommendations:

- **Hosting**: Forge + Hetzner / Forge + AWS (whatever you already use).
- **DB**: MySQL (same engine as local; only `DB_HOST`, `DB_PASSWORD` and credentials change).
- **Image storage**: S3 (or R2 / Spaces). Edit `FILESYSTEM_DISK=s3` + the AWS keys. Spatie images inherit the disk.
- **Mail**: Mailgun is pre-installed (`symfony/mailgun-mailer`). Set `MAIL_MAILER=mailgun` + `MAILGUN_DOMAIN` + `MAILGUN_SECRET` and you're done. Postmark/Resend/SES also work — Laravel ships their drivers natively.
- **Queue worker**: you need a worker running (`php artisan queue:work`) so the lead email + webhook get processed. Set up Supervisor or the equivalent.
- **Scheduler (cron)**: scheduled landings are published by the daily `landings:publish-scheduled` command, which runs inside Laravel's scheduler — NOT via the queue. Add the single system cron entry Laravel needs (on Forge: enable the scheduler / "Scheduled Jobs"):
  ```cron
  * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
  ```
  Without this cron, landings stay in `scheduled` and never flip to `published`. The default `landings:publish-scheduled` runs at 07:00 (see `routes/console.php`); change `->dailyAt('07:00')` to `->hourly()` if you need hour-level precision.
- **Cache**: Redis recommended in production (`CACHE_STORE=redis`) — the sitemap uses 1h cache.

**Minimum steps on each deploy**:
```bash
composer install --no-dev --optimize-autoloader
php artisan config:cache route:cache view:cache event:cache
php artisan migrate --force
npm ci && npm run build
php artisan storage:link
```

---

## Repo structure

```
app/
├── Console/Commands/Services/         # artisan services:create-user, services:mcp-token
├── Console/Commands/                # landings:publish-scheduled (daily, via scheduler)
├── Enums/                           # LocationType, LeadStatus, LandingStatus
├── Http/Controllers/SitemapController.php
├── Jobs/Lead/SendLeadWebhook.php
├── Livewire/Forms/                  # *Form classes consumed by the Livewire pages
├── Mail/Lead/NewLeadMail.php
├── Mcp/
│   ├── Servers/ServicesServer.php     # Aggregates all tools
│   └── Tools/{Catalog,Blog,Pages,Leads}/
├── Models/                          # Location, Category, Service, Landing, BlogPost, Page, Lead, User
└── Services/
    ├── Lead/LeadService.php
    └── Seo/{SeoService, SchemaBuilder}.php

resources/views/
├── layouts/{public, auth, admin}.blade.php   # CUSTOMIZE public + auth
├── pages/
│   ├── ⚡home.blade.php                       # CUSTOMIZE
│   ├── ⚡landing.blade.php                    # CUSTOMIZE (optionally)
│   ├── blog/⚡{index,show}.blade.php          # CUSTOMIZE
│   └── admin/...                              # internal, don't touch
├── components/⚡lead-form/                    # CUSTOMIZE the form
└── sitemap/{index, urlset}.blade.php

routes/
├── web.php       # public + admin routes
└── ai.php        # MCP server

config/
├── seo.php       # default OG image, organization, landing_slug_separator
├── leads.php     # notify_email, webhook_url, webhook_secret
└── media-library.php
```

---

## FAQ

**How do Category and Service relate?** `Category` groups services by type (e.g. "reformas", "tasaciones") and is the unit landings are built from (category × location). `Service` is an individual offering shown on a listing/detail block (`Service belongsTo Category`, plus optional `additionalCategories`). A site can use only Categories + Landings for SEO and skip Services entirely if it has no catalog to show.

**Why aren't landings created automatically when you have categories + locations?** Because you lose control. If you have 20 categories × 50 locations = 1000 URLs and Google indexes garbage. The template prefers that you decide (via matrix admin or `bulk_create_landings` via MCP) which combinations get published.

**Why isn't Service shown by default on the public landing?** Because it depends on the business model. If you're a broker without a real catalog, the form + SEO copy is enough. If you have a catalog (or want to show representative offerings), add the Services block to `pages/⚡landing.blade.php` (example above).

**Can I manage content without opening the web admin?** Yes. The MCP server covers everything editable. Generate a token and talk to Claude.

**How do I add a custom field to Service specific to my niche (e.g. kVA, runtime, dB for generators)?** You don't touch the migration. Store them in `custom_fields` (jsonb) — from the admin you edit it as JSON, from MCP it's an object. To show them in the frontend, `$product->customField('power_kva')`. If you want a typed form UI per field, override `resources/views/pages/admin/services/⚡edit.blade.php` with the specific `<flux:input>` controls.

**How do I disable a landing without deleting it?** In `/admin/landings`, set its status to `Borrador` (draft) — or use the "Despublicar" quick action. The URL returns 404 and disappears from the sitemap, but the content (title, meta, content jsonb) is preserved. Publishing again is one click ("Publicar ahora").

**How do I schedule landings to publish gradually (drip)?** A landing has three states: `Borrador` (draft) · `Programada` (scheduled) · `Publicada` (published). In `/admin/landings/{landing}/edit`, set a publish date and the status flips to `Programada` automatically (clearing the date sends it back to draft). The daily `landings:publish-scheduled` command (Laravel scheduler — see the cron note in Deployment) publishes each one when its date arrives. To queue many at once, drive it from MCP: list drafts with `list_landings status=draft`, then set `publish_at` on the ones you want via `update_landing`. Only `Publicada` landings respond 200 and appear in the sitemap.

**How do I schedule a blog post for the future?** In `/admin/blog/{post}/edit`, set `published_at` to a future date. The sitemap and public `/blog` will ignore it until that date passes. (Note: blog scheduling is query-based and needs no cron; landing scheduling flips a status column via the scheduler — see above.)

---

## Resources

- Original template plan: `/home/cristian/.claude/plans/logical-moseying-ripple.md`
- Project CLAUDE.md: code rules (PHP, Livewire, Flux, testing) that Claude Code follows when editing this repo.
