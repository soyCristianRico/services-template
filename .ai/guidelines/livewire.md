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

## File Uploads
- Livewire does NOT remove a temporary upload when you are done with it. It only purges `livewire-tmp` files older than 24 hours when the next upload finishes, so the last batch stays on disk for good (Marfinques: 223 MB of forgotten camera originals).
- **Once an upload is handled — stored, imported or read — delete its temporary file**, in the same method. Copying it or handing a copy to the media library leaves the original behind.

```php
foreach ($this->newImages as $image) {
    // ...store it...
    $image->delete();
}

$this->newImages = [];
```

- Test it: assert no file is left in `FileUploadConfiguration::path()` on `FileUploadConfiguration::storage()`, ignoring the `.json` sidecar Livewire keeps next to each upload until its own purge.

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
