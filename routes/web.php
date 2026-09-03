<?php

declare(strict_types=1);

use App\Http\Controllers\LlmsTxtController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;
use Toolkit\AgentMarkdown\NegotiateMarkdownResponse;

// Public pages only — never /admin. A crawler asking for `Accept: text/markdown`
// gets the page's <main> content converted instead of full HTML.
Route::middleware(NegotiateMarkdownResponse::class)->group(function (): void {
    Route::livewire('/', 'pages::home')->name('home');

    Route::livewire('/blog', 'pages::blog.index')->name('blog.index');
    Route::livewire('/blog/{slug}', 'pages::blog.show')
        ->where('slug', '[a-z0-9-]+')
        ->name('blog.show');
});

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function (): void {
    Route::livewire('/', 'pages::admin.dashboard')->name('dashboard');

    Route::livewire('/profile', 'pages::admin.profile')->name('profile');

    if (config('site.locations')) {
        Route::livewire('/locations', 'pages::admin.locations.index')->name('locations.index');
        Route::livewire('/locations/create', 'pages::admin.locations.edit')->name('locations.create');
        Route::livewire('/locations/{location}/edit', 'pages::admin.locations.edit')->name('locations.edit');
    }

    Route::livewire('/categories', 'pages::admin.categories.index')->name('categories.index');
    Route::livewire('/categories/create', 'pages::admin.categories.edit')->name('categories.create');
    Route::livewire('/categories/{category}/edit', 'pages::admin.categories.edit')->name('categories.edit');

    Route::livewire('/services', 'pages::admin.services.index')->name('services.index');
    Route::livewire('/services/create', 'pages::admin.services.edit')->name('services.create');
    Route::livewire('/services/{service}/edit', 'pages::admin.services.edit')->name('services.edit');

    if (config('site.locations')) {
        Route::livewire('/landings', 'pages::admin.landings.index')->name('landings.index');
        Route::livewire('/landings/matrix', 'pages::admin.landings.matrix')->name('landings.matrix');
        Route::livewire('/landings/create', 'pages::admin.landings.edit')->name('landings.create');
        Route::livewire('/landings/{landing}/edit', 'pages::admin.landings.edit')->name('landings.edit');
    }

    // `/redirects/404` e `/import` van antes que `/{redirect}/edit` sólo por
    // claridad: no comparten forma, pero leerlas juntas evita que la próxima
    // ruta con nombre fijo se cuele debajo del parámetro.
    Route::livewire('/redirects', 'pages::admin.redirects.index')->name('redirects.index');
    Route::livewire('/redirects/create', 'pages::admin.redirects.edit')->name('redirects.create');
    Route::livewire('/redirects/import', 'pages::admin.redirects.import')->name('redirects.import');
    Route::livewire('/redirects/404', 'pages::admin.redirects.not-found')->name('redirects.not-found');
    Route::livewire('/redirects/{redirect}/edit', 'pages::admin.redirects.edit')->name('redirects.edit');

    Route::livewire('/blog', 'pages::admin.blog.index')->name('blog.index');
    Route::livewire('/blog/create', 'pages::admin.blog.edit')->name('blog.create');
    Route::livewire('/blog/{post}/edit', 'pages::admin.blog.edit')->name('blog.edit');

    Route::livewire('/leads', 'pages::admin.leads.index')->name('leads.index');
    Route::livewire('/leads/{lead}', 'pages::admin.leads.show')->name('leads.show');

    Route::livewire('/pages', 'pages::admin.pages.index')->name('pages.index');
    Route::livewire('/pages/create', 'pages::admin.pages.edit')->name('pages.create');
    Route::livewire('/pages/{page}/edit', 'pages::admin.pages.edit')->name('pages.edit');

    Route::livewire('/menus', 'pages::admin.menus.index')->name('menus.index');
    Route::livewire('/menus/create', 'pages::admin.menus.edit')->name('menus.create');
    Route::livewire('/menus/{menuItem}/edit', 'pages::admin.menus.edit')->name('menus.edit');
});

Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/sitemap-pages.xml', [SitemapController::class, 'pages'])->name('sitemap.pages');
Route::get('/sitemap-blog.xml', [SitemapController::class, 'blog'])->name('sitemap.blog');

// Landings only exist when the site has a geographic dimension. Without it the
// `/{slug}` route below is never registered, so any landing URL in a sitemap
// would resolve to a 404 — the sub-sitemap has to disappear with the dimension,
// not merely come back empty.
if (config('site.locations')) {
    Route::get('/sitemap-landings.xml', [SitemapController::class, 'landings'])->name('sitemap.landings');
}

// llms.txt — index for AI agents, mirroring the sitemap's own page enumeration.
Route::get('/llms.txt', [LlmsTxtController::class, 'index'])->name('llms-txt');

Route::middleware(NegotiateMarkdownResponse::class)->group(function (): void {
    // Legal — one page carrying the four blocks as #anchors. Declared before
    // the catch-all so it wins over a CMS Page that happens to be slugged
    // "legal".
    Route::livewire('/legal', 'pages::legal')->name('legal');

    // Programmatic landings — must stay last so /, /admin, /blog, Fortify-named
    // routes and sitemap routes are matched first. Skipped entirely on sites
    // with no geographic dimension, where this catch-all would swallow every
    // other slug.
    if (config('site.locations')) {
        Route::livewire('/{slug}', 'pages::landing')
            ->where('slug', '[a-z0-9-]+')
            ->name('landing');
    }
});
