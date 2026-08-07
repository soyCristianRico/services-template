<laravel-boost-guidelines>
=== .ai/livewire rules ===

# Livewire v4

## Component Namespaces
- `pages::` → `resources/views/pages/`
- `layouts::` → `resources/views/layouts/`

## File Naming
- `⚡` prefix indicates Livewire component (SFC or MFC)
- `pages::admin.products.edit` → `resources/views/pages/admin/products/⚡edit.blade.php`

## Full-Page Routes
```php
Route::livewire('/posts/create', 'pages::post.create');
```

## Creating Components
- `php artisan make:livewire pages::post.create` → SFC (single-file)
- `php artisan make:livewire pages::post.create --mfc` → MFC (multi-file, same folder)

## Authorization
- **NEVER** put authorization in `mount()` method with `Gate::authorize()`
- Authorization is handled at route level using `->can()`:
```php
Route::livewire('/posts/create', 'pages::post.create')
    ->can('create', Post::class);

Route::livewire('/posts/{post}/edit', 'pages::post.edit')
    ->can('update', 'post');
```
- The route parameter name (e.g., `'post'`) must match the model variable in `->can()`

## Deferred Loading (Skeleton Pattern)
Use `wire:init` + `$loaded` flag to show skeletons while heavy data loads. This makes `wire:navigate` transitions feel instant.

**Note:** `Route::livewire()->defer()` is documented in Livewire v4 docs but NOT available in v4.2.x. Do not use it.

### Pattern
```php
// In the component PHP:
public bool $loaded = false;

public function loadContent(): void
{
    $this->loaded = true;
}
```

```blade
{{-- Root element with wire:init --}}
<div wire:init="loadContent">
    {{-- Header, breadcrumbs, filters render immediately --}}

    @if(!$loaded)
        {{-- Skeleton using flux:skeleton components --}}
        <flux:skeleton.group animate="shimmer">
            <flux:skeleton.line />
        </flux:skeleton.group>
    @else
        {{-- Real content (heavy queries only execute when $loaded is true) --}}
    @endif
</div>
```

### Rules
- Gate ALL heavy `#[Computed]` property access behind `@if($loaded)` — they are lazy-evaluated, so if not accessed in the template, they don't execute
- Use `flux:skeleton` components (`flux:skeleton.group`, `flux:skeleton`, `flux:skeleton.line`) with `animate="shimmer"`
- Match skeleton spacing to real content spacing (e.g., if parent has `space-y-8`, skeleton group needs `class="space-y-8"`)
- Reusable skeleton: `<x-admin.product-list-skeleton :rows="6" />`

## Form Classes
- Location: `app/Livewire/Forms/{Module}/` → e.g., `app/Livewire/Forms/Catalog/ProductForm.php`
- Form handles: validation (`rules()`, `messages()` or `#[Validate]` attrs), form state (public properties), save methods (`save()`, `autoSave*()`)
- Component handles: authorization (`$this->authorize()`), UI state (modals), toasts/notifications
- Template binding: `wire:model="form.title"`

## Services
- Location: `app/Services/{Module}/` → e.g., `app/Services/Catalog/ProductService.php`, `app/Services/Lead/LeadService.php`, `app/Services/Seo/SeoService.php`
- Use Form for: simple CRUD, field updates, validation
- Use Service for: multi-model operations, notifications, events, transactions, workflow transitions
- Pattern: Form validates and delegates to Service for complex logic

=== .ai/helpers rules ===

# Helpers

## Numbers (European format: dot=thousands, comma=decimals)
- `formatNumber(1500)` → "1.500"
- `formatNumber(1500.25, 2)` → "1.500,25"
- `formatPercentage(25.5)` → "25,50%"

=== .ai/database rules ===

# Database

MariaDB in local, MySQL in production — same engine family, so a migration that
runs locally runs on the deploy. What still bites is the set of limits and
behaviours MySQL enforces and that are easy to write past. Check these before
writing schema.

The one place the two genuinely diverge is **JSON columns**: MariaDB's `JSON` is
`LONGTEXT` with a CHECK constraint, MySQL's is a native type. Anything leaning on
JSON functions is unverified until it runs on the real thing.

## Index key length

InnoDB caps a key at **3072 bytes**, and under `utf8mb4` every character costs
4 bytes — so a plain `string()` column is `VARCHAR(255)` = **1020 bytes** inside
an index. Three of them in one composite index already leaves almost no room.

- **Size string columns to what they hold.** A column backed by an enum needs
  `string('modality', 32)`, never the default 255. This is the fix, not index
  prefixes.
- **Add up the bytes before adding a composite index**: 4 × length for
  char/text columns, 8 for ints and datetimes.
- A `unique()` is an index too, and so is every `index()`.

## `timestamp` ends in 2038

A `timestamp` column cannot hold a date past **2038-01-19**; MySQL rejects it
with *Incorrect datetime value*. Fine for `publish_at` and friends, but never
put a far-future sentinel date in a factory or a test — use
`now()->addMonths(3)`. Reach for `datetime` if a column genuinely needs to go
further.

## DDL is not transactional

A migration that fails halfway leaves the earlier statements applied and the
migration unrecorded, so the next deploy re-runs it from the top and dies on the
first statement that already happened.

Any migration doing several DDL steps has to be **re-runnable**: guard with
`Schema::hasColumn()`, and check `Schema::getIndexes($table)` before dropping or
creating an index.

## `->change()` drops what you do not restate

Modifying a column replaces its whole definition. Any attribute left out —
default, nullable, length — is gone. Restate the full definition every time.

## Indexes block column changes

Renaming or dropping a column that belongs to an index needs the index removed
first, and recreated afterwards.

## The test suite runs on MySQL too

`phpunit.xml` deliberately pins **no** connection, so the database comes from
`.env.testing` — which points at MySQL, on its own `{project}_test` database
because `RefreshDatabase` wipes it on every run. `.env.testing` is tracked in
git (only `.env`, `.env.backup` and `.env.production` are ignored), so a fresh
clone has working tests without configuring anything.

**Laravel REPLACES `.env` with `.env.testing`; it does not merge them.** So it
has to be a complete env file, `APP_KEY` included, or every test dies with
`MissingAppKeyException`. Build it from `.env.example` — which is also why it
holds no real credentials and can live in git.

The point is coverage, not tidiness: the suite has to run on the same engine
family as production or it cannot catch any of the limits above. `new-site.sh`
creates both databases and writes both env files, so a new site starts out this
way.

## A cascade deletes rows, not files

`cascadeOnDelete` is enforced by the database, and the database knows nothing
about Eloquent. Deleting a parent removes the children with one statement and
**no model event fires for them** — no `deleting`, no observer, and none of the
cleanup those hooks exist to do.

That is invisible while a child holds only columns. It bites the moment a child
holds a **file**: a media-library collection, or a path to something on a private
disk. The row disappears, the file stays, and nothing left in the database names
it. Nobody notices, because the only symptom is a disk that grows.

**When the child owns files, delete the children through Eloquent from the
parent's `deleting` hook.** Leave the foreign key as it is — it stays as the
backstop for anything that reaches the table another way.

```php
protected static function booted(): void
{
    static::deleting(function (Category $category): void {
        $category->services->each->delete();
    });
}
```

The same reasoning applies one level down: a model that stores its own file on a
disk deletes it in its own `deleting` hook, so it cleans up however it is
deleted — from the admin, from a command, from a parent, or from tinker. Put the
cleanup on the model rather than at the call site; a rule that lives in one
button is a rule that holds until the second button.

The cheapest way to know whether this applies: **does deleting this row leave
bytes on a disk?** If yes, it needs a hook and a test that asserts the file is
gone, not just the row.

## Migrations

- One concern per migration, and always a working `down()`.
- When a scale or a meaning changes, migrate the stored values in the same
  migration as the column (e.g. a 0-5 score moving to 0-10 doubles its rows),
  and check the precision still fits: `decimal(2, 1)` cannot store `10.0`.
- Verify by running the migration, rolling it back and running it again. The
  rollback is where a missing `down()` or a forgotten index shows up, and the
  re-run is where a non-re-runnable migration shows up.

=== .ai/git rules ===

# Git

- Commit only when the user explicitly asks (see permissions).
- Commit to the current branch (usually `main`). Do NOT create a new branch for
  commits unless the user explicitly requests it.

=== .ai/styling rules ===

# Styling

## Public headings (type scale)

- Public page/section headings use `<flux:heading level="1|2|3">` with NO size or
  `text-*`/`font-*` classes. The brand type scale lives in ONE place:
  `resources/css/app.css`, scoped to the public site.
- Level mapping: `1` = page/hero title (one `<h1>` per page), `2` = section title,
  `3` = card / sub-item title.
- Why it works: `flux:heading` with a `level` renders a semantic
  `<h1|h2|h3 data-flux-heading>` but applies `text-sm`/`font-medium` by default.
  The rules in `app.css` (`[data-public-site] h1[data-flux-heading]`, …) beat
  those by specificity and are UNLAYERED, so no `!important` is needed.
- Scope: the `data-public-site` attribute is on the public layout `<body>`. The
  admin uses another layout and keeps Flux's compact heading defaults.
- A `flux:heading` with `size` but no `level` renders a `<div data-flux-heading>`
  and is NOT restyled — use `size=` for form/modal/banner headings that should
  stay compact.
- To change a heading size, edit `app.css` (single source of truth). NEVER add
  per-heading `text-*!`/`font-*!` utilities.

=== .ai/refactoring rules ===

# Refactoring

- NEVER create `*Refactored.php`, `*New.php` or `*V2.php` files
- Always modify the original file in-place
- Extract auxiliary classes if needed, adapt the original to use them

=== .ai/formatting rules ===

# Formatting

- Run `composer run format` before completing any task (Rector + Pint)
- Add imports manually, the formatter organizes them automatically
- Fallback: `vendor/bin/pint`

## Language
- **Code**: comments, documentation, commit messages, variable names, function names, class names → English
- **User-visible text**: labels, titles, buttons, placeholders, error messages, toasts, enum labels → Spanish
- This includes Blade templates, Livewire components, and any text shown in the UI
- Enum `label()` methods should return Spanish text (e.g., "Borrador" not "Draft")

=== .ai/php rules ===

# PHP

## Visibility
- Use `protected` instead of `private` for methods and properties

=== .ai/testing rules ===

# Testing

## Folder Structure
Pattern: `tests/Feature/{ClassType}/{Module}/` (type first, then module — Catalog, Blog, Lead, Seo, etc.)
- `tests/Feature/Commands/Catalog/`, `tests/Feature/Jobs/Lead/`, etc.
- `tests/Feature/Models/`, `tests/Feature/Policies/` → shared, not by module

## Required Syntax
Use Pest with `describe()` + `it()`. NEVER use `test()`.

```php
describe('ClassName', function () {
    describe('method_name', function () {
        it('should do something', function () { });
    });
});
```

## Execution
- The suite runs on MySQL, from `.env.testing` (see `.ai/guidelines/database.md`).
  `php artisan test` runs all of it in well under a minute — the old "never run
  the full suite, it runs out of memory" rule was an artefact of SQLite
  `:memory:` and no longer applies.
- While working, still run the narrowest thing that proves the change:
  `php artisan test --compact tests/Feature/ExampleTest.php` or `--filter=testName`.
  Run the whole suite before calling a task done.

## Rules
- One test file per class; add `describe()` blocks to existing files
- Search before creating: `find tests -name "*Name*Test.php"`
- Flux UI modals/toasts: test state changes, not visual behavior
- First `describe()` = class name, nested `describe()` = method name
- NEVER put `it()` directly under the class-level `describe()`

## Test Maintenance
When modifying logic:
1. Search for existing tests covering the modified functionality
2. Update existing tests to reflect new behavior
3. Create new tests if none exist
4. Run related tests after changes

## Browser Tests (Pest 4)
- Location: `tests/Browser/`
- Use for JavaScript/Alpine functionality requiring real browser interaction
- Use `@data-test` selectors: `->click('@resolve-feedback')` (maps to `data-test="resolve-feedback"`)

## Database Errors During Tests
- If you see "database is being recreated", "table not found", "database locked" or similar: **tests are likely running in another terminal**
- Wait for the other process to finish before running your tests
- Check for other running test processes before assuming a real DB problem

## Livewire Form Tests
- When creating a Form class (`app/Livewire/Forms/`), MUST create its test
- Host component in `tests/Support/Livewire/{FormName}TestComponent.php`
- Test file in `tests/Feature/Forms/{Module}/{FormName}Test.php`
- Test: initialization, validation (`rules()` or `#[Validate]`), save methods, `updated()` hook

=== .ai/javascript rules ===

# JavaScript & Alpine

## Alpine Component Structure

```javascript
Alpine.data('componentName', (serverData) => ({
    // Data (from server)
    items: serverData || [],

    // State (client-side)
    chart: null,
    isOpen: false,
    filter: Alpine.$persist('all').as('component-filter'),

    // Getters
    get filteredItems() { ... },

    // Lifecycle
    init() { ... },
    destroy() { ... },

    // Public: Actions
    toggle(id) { ... },

    // Public: Formatters
    formatNumber(val) { ... },

    // Private
    _initChart() { ... },
    _bindEvents() { ... },
}));
```

Section order: Data → State → Getters → Lifecycle → Public Actions → Public Formatters → Private (`_` prefix).

## Non-Reactive Data

Store library instances outside Alpine's reactive data to avoid proxy issues:

```javascript
Alpine.data('component', () => {
    let editorInstance = null;
    return {
        init() { editorInstance = this.$refs.editor.getEditor(); },
    };
});
```

## Flux Editor Auto-Save Pattern

Use Alpine with `$wire` instead of `wire:model` for `flux:editor` with debounced saving. Use `:value="$form->body"` on the component. Listen to `flux:editor:ready`, store the editor instance outside reactive data, and debounce `$wire.saveBody(html)` on editor `update` events.

## Optimistic UI Pattern

Update UI immediately, rollback on failure:

```javascript
async saveItem() {
    const original = this.item.text;
    this.item.text = newText;
    const success = await $wire.save(newText);
    if (!success) {
        this.item.text = original;
        Flux.toast({ text: 'Error message', variant: 'warning' });
    }
}
```

## Component Communication
- Dispatch: `window.dispatchEvent(new CustomEvent('event-name', { detail: { data } }))`
- Listen: `window.addEventListener('event-name', (e) => { ... })`
- Use `$watch()` to react to state changes

## External JS Modules
- Place in `resources/js/`, import in `app.js`
- Use `_method()` for protected methods (not `#method` - causes context issues)
- Export public API via `window` for Alpine access

## Selectors
- Use `data-` attributes for JS hooks, not classes
- Extract repeated selectors to constants

=== .ai/permissions rules ===

# Permissions

## NEVER without explicit user permission
- `git commit`
- `migrate:fresh`, `migrate:rollback`, `db:wipe`
- Delete files
- `composer update/require`, `npm update/install`

=== .ai/coderabbit rules ===

# CodeRabbit

## When to run
Only when user explicitly requests ("run coderabbit", "analyze with coderabbit")

## Command
`coderabbit --prompt-only --type uncommitted`

## Implement
Security vulnerabilities, race conditions, memory leaks, significant logic errors

## Ignore
Style issues (Pint handles it), pedantic suggestions, excessive validations

=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to enhance the user's satisfaction building Laravel applications.

## Foundational Context
This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.3.21
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v12
- laravel/mcp (MCP) - v0
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- livewire/flux (FLUXUI_FREE) - v2
- livewire/flux-pro (FLUXUI_PRO) - v2
- livewire/livewire (LIVEWIRE) - v4
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- rector/rector (RECTOR) - v2
- tailwindcss (TAILWINDCSS) - v4

## Conventions
- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts
- Do not create verification scripts or tinker when tests cover that functionality and prove it works. Unit and feature tests are more important.

## Application Structure & Architecture
- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling
- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Replies
- Be concise in your explanations - focus on what's important rather than explaining obvious details.

## Documentation Files
- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

## Laravel Boost
- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan
- Use the `list-artisan-commands` tool when you need to call an Artisan command to double-check the available parameters.

## URLs
- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Tinker / Debugging
- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool
- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)
- Boost comes with a powerful `search-docs` tool you should use before any other approaches when dealing with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- The `search-docs` tool is perfect for all Laravel-related packages, including Laravel, Inertia, Livewire, Filament, Tailwind, Pest, Nova, Nightwatch, etc.
- You must use this tool to search for Laravel ecosystem documentation before falling back to other approaches.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries to start. For example: `['rate limiting', 'routing rate limiting', 'routing']`.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax
- You can and should pass multiple queries at once. The most relevant results will be returned first.

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

## PHP

- Always use curly braces for control structures, even if it has one line.

### Constructors
- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

### Type Declarations
- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Comments
- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless there is something very complex going on.

## PHPDoc Blocks
- Add useful array shape type definitions for arrays when appropriate.

## Enums
- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

=== tests rules ===

## Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

## Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Database
- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation
- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources
- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

### Controllers & Validation
- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

### Queues
- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

### Authentication & Authorization
- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

### URL Generation
- When generating links to other pages, prefer named routes and the `route()` function.

### Configuration
- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

### Testing
- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

### Vite Error
- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

## Laravel 12

- Use the `search-docs` tool to get version-specific documentation.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

### Laravel 12 Structure
- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app\Console\Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

### Database
- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models
- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== mcp/core rules ===

## Laravel MCP

- MCP (Model Context Protocol) is very new. You must use the `search-docs` tool to get documentation for how to write and test Laravel MCP servers, tools, resources, and prompts effectively.
- MCP servers need to be registered with a route or handle in `routes/ai.php`. Typically, they will be registered using `Mcp::web()` to register an HTTP streaming MCP server.
- Servers are very testable; use the `search-docs` tool to find testing instructions.
- Do not run `mcp:start`. This command hangs waiting for JSON-RPC MCP requests.
- Some MCP clients use Node, which has its own certificate store. If a user tries to connect to their web MCP server locally using HTTPS, it could fail due to this reason. They will need to switch to HTTP during local development.

=== fluxui-pro/core rules ===

## Flux UI Pro

- This project is using the Pro version of Flux UI. It has full access to the free components and variants, as well as full access to the Pro components and variants.
- Flux UI is a component library for Livewire. Flux is a robust, hand-crafted UI component library for your Livewire applications. It's built using Tailwind CSS and provides a set of components that are easy to use and customize.
- You should use Flux UI components when available.
- Fallback to standard Blade components if Flux is unavailable.
- If available, use the `search-docs` tool to get the exact documentation and code snippets available for this project.
- Flux UI components look like this:

<code-snippet name="Flux UI Component Example" lang="blade">
    <flux:button variant="primary"/>
</code-snippet>

### Available Components
This is correct as of Boost installation, but there may be additional components within the codebase.

<available-flux-components>
accordion, autocomplete, avatar, badge, brand, breadcrumbs, button, calendar, callout, card, chart, checkbox, command, composer, context, date-picker, dropdown, editor, field, file-upload, heading, icon, input, kanban, modal, navbar, otp-input, pagination, pillbox, popover, profile, radio, select, separator, skeleton, slider, switch, table, tabs, text, textarea, time-picker, toast, tooltip
</available-flux-components>

=== livewire/core rules ===

## Livewire

- Use the `search-docs` tool to find exact version-specific documentation for how to write Livewire and Livewire tests.
- Use the `php artisan make:livewire [Posts\CreatePost]` Artisan command to create new components.
- State should live on the server, with the UI reflecting it.
- All Livewire requests hit the Laravel backend; they're like regular HTTP requests. Always validate form data and run authorization checks in Livewire actions.

## Livewire Best Practices
- Livewire components require a single root element.
- Use `wire:loading` and `wire:dirty` for delightful loading states.
- Add `wire:key` in loops:

    ```blade
    @foreach ($items as $item)
        <div wire:key="item-{{ $item->id }}">
            {{ $item->name }}
        </div>
    @endforeach
    ```

- Prefer lifecycle hooks like `mount()`, `updatedFoo()` for initialization and reactive side effects:

<code-snippet name="Lifecycle Hook Examples" lang="php">
    public function mount(User $user) { $this->user = $user; }
    public function updatedSearch() { $this->resetPage(); }
</code-snippet>

## Testing Livewire

<code-snippet name="Example Livewire Component Test" lang="php">
    Livewire::test(Counter::class)
        ->assertSet('count', 0)
        ->call('increment')
        ->assertSet('count', 1)
        ->assertSee(1)
        ->assertStatus(200);
</code-snippet>

<code-snippet name="Testing Livewire Component Exists on Page" lang="php">
    $this->get('/posts/create')
    ->assertSeeLivewire(CreatePost::class);
</code-snippet>

=== pint/core rules ===

## Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest
### Testing
- If you need to verify a feature is working, write or update a Unit / Feature test.

### Pest Tests
- All tests must be written using Pest. Use `php artisan make:test --pest {name}`.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files - these are core to the application.
- Tests should test all of the happy paths, failure paths, and weird paths.
- Tests live in the `tests/Feature` and `tests/Unit` directories.
- Pest tests look and behave like this:
<code-snippet name="Basic Pest Test Example" lang="php">
it('is true', function () {
    expect(true)->toBeTrue();
});
</code-snippet>

### Running Tests
- Run the minimal number of tests using an appropriate filter before finalizing code edits.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).
- When the tests relating to your changes are passing, ask the user if they would like to run the entire test suite to ensure everything is still passing.

### Pest Assertions
- When asserting status codes on a response, use the specific method like `assertForbidden` and `assertNotFound` instead of using `assertStatus(403)` or similar, e.g.:
<code-snippet name="Pest Example Asserting postJson Response" lang="php">
it('returns all', function () {
    $response = $this->postJson('/api/docs', []);

    $response->assertSuccessful();
});
</code-snippet>

### Mocking
- Mocking can be very helpful when appropriate.
- When mocking, you can use the `Pest\Laravel\mock` Pest function, but always import it via `use function Pest\Laravel\mock;` before using it. Alternatively, you can use `$this->mock()` if existing tests do.
- You can also create partial mocks using the same import or self method.

### Datasets
- Use datasets in Pest to simplify tests that have a lot of duplicated data. This is often the case when testing validation rules, so consider this solution when writing tests for validation rules.

<code-snippet name="Pest Dataset Example" lang="php">
it('has emails', function (string $email) {
    expect($email)->not->toBeEmpty();
})->with([
    'james' => 'james@laravel.com',
    'taylor' => 'taylor@laravel.com',
]);
</code-snippet>

=== pest/v4 rules ===

## Pest 4

- Pest 4 is a huge upgrade to Pest and offers: browser testing, smoke testing, visual regression testing, test sharding, and faster type coverage.
- Browser testing is incredibly powerful and useful for this project.
- Browser tests should live in `tests/Browser/`.
- Use the `search-docs` tool for detailed guidance on utilizing these features.

### Browser Testing
- You can use Laravel features like `Event::fake()`, `assertAuthenticated()`, and model factories within Pest 4 browser tests, as well as `RefreshDatabase` (when needed) to ensure a clean state for each test.
- Interact with the page (click, type, scroll, select, submit, drag-and-drop, touch gestures, etc.) when appropriate to complete the test.
- If requested, test on multiple browsers (Chrome, Firefox, Safari).
- If requested, test on different devices and viewports (like iPhone 14 Pro, tablets, or custom breakpoints).
- Switch color schemes (light/dark mode) when appropriate.
- Take screenshots or pause tests for debugging when appropriate.

### Example Tests

<code-snippet name="Pest Browser Test Example" lang="php">
it('may reset the password', function () {
    Notification::fake();

    $this->actingAs(User::factory()->create());

    $page = visit('/sign-in'); // Visit on a real browser...

    $page->assertSee('Sign In')
        ->assertNoJavascriptErrors() // or ->assertNoConsoleLogs()
        ->click('Forgot Password?')
        ->fill('email', 'nuno@laravel.com')
        ->click('Send Reset Link')
        ->assertSee('We have emailed your password reset link!')

    Notification::assertSent(ResetPassword::class);
});
</code-snippet>

<code-snippet name="Pest Smoke Testing Example" lang="php">
$pages = visit(['/', '/about', '/contact']);

$pages->assertNoJavascriptErrors()->assertNoConsoleLogs();
</code-snippet>

=== tailwindcss/core rules ===

## Tailwind CSS

- Use Tailwind CSS classes to style HTML; check and use existing Tailwind conventions within the project before writing your own.
- Offer to extract repeated patterns into components that match the project's conventions (i.e. Blade, JSX, Vue, etc.).
- Think through class placement, order, priority, and defaults. Remove redundant classes, add classes to parent or child carefully to limit repetition, and group elements logically.
- You can use the `search-docs` tool to get exact examples from the official documentation when needed.

### Spacing
- When listing items, use gap utilities for spacing; don't use margins.

<code-snippet name="Valid Flex Gap Spacing Example" lang="html">
    <div class="flex gap-8">
        <div>Superior</div>
        <div>Michigan</div>
        <div>Erie</div>
    </div>
</code-snippet>

### Dark Mode
- If existing pages and components support dark mode, new pages and components must support dark mode in a similar way, typically using `dark:`.

=== tailwindcss/v4 rules ===

## Tailwind CSS 4

- Always use Tailwind CSS v4; do not use the deprecated utilities.
- `corePlugins` is not supported in Tailwind v4.
- In Tailwind v4, configuration is CSS-first using the `@theme` directive — no separate `tailwind.config.js` file is needed.

<code-snippet name="Extending Theme in CSS" lang="css">
@theme {
  --color-brand: oklch(0.72 0.11 178);
}
</code-snippet>

- In Tailwind v4, you import Tailwind using a regular CSS `@import` statement, not using the `@tailwind` directives used in v3:

<code-snippet name="Tailwind v4 Import Tailwind Diff" lang="diff">
   - @tailwind base;
   - @tailwind components;
   - @tailwind utilities;
   + @import "tailwindcss";
</code-snippet>

### Replaced Utilities
- Tailwind v4 removed deprecated utilities. Do not use the deprecated option; use the replacement.
- Opacity values are still numeric.

| Deprecated |	Replacement |
|------------+--------------|
| bg-opacity-* | bg-black/* |
| text-opacity-* | text-black/* |
| border-opacity-* | border-black/* |
| divide-opacity-* | divide-black/* |
| ring-opacity-* | ring-black/* |
| placeholder-opacity-* | placeholder-black/* |
| flex-shrink-* | shrink-* |
| flex-grow-* | grow-* |
| overflow-ellipsis | text-ellipsis |
| decoration-slice | box-decoration-slice |
| decoration-clone | box-decoration-clone |
</laravel-boost-guidelines>
