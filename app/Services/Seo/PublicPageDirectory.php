<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Models\BlogPost;
use App\Models\Landing;
use App\Models\Page;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Enumerates the site's current public pages, landings, and blog posts as
 * plain {url, label} pairs.
 *
 * The qualifying rules below intentionally mirror SitemapController's own
 * enumeration (same allowlist, same indexability check, same "empty until
 * there's content" gates) so `/llms.txt` always lists exactly what the
 * sitemap does — without either one having to import the other.
 */
class PublicPageDirectory
{
    /**
     * Controller backing every full-page Livewire route (`Route::livewire`).
     * Used as a fail-closed allowlist: only the app's own page routes qualify,
     * so package- and framework-registered routes never leak into the index.
     */
    protected const PAGE_CONTROLLER = 'Livewire\Mechanisms\HandleRouting\LivewirePageController';

    /**
     * Parameter-less, indexable, non-admin Livewire pages, plus CMS Pages.
     *
     * @return list<array{url: string, label: string}>
     */
    public function pages(): array
    {
        $pages = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            if ($name === null) {
                continue;
            }

            if (! str_ends_with($route->getActionName(), self::PAGE_CONTROLLER)) {
                continue;
            }

            if ($route->parameterNames() !== []) {
                continue;
            }

            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            if (in_array('auth', $route->gatherMiddleware(), true)) {
                continue;
            }

            if (! SeoService::isIndexable($name)) {
                continue;
            }

            // /blog is noindex while empty (see BlogPost::hasPublished) — keep
            // it out of the index too until it has published posts.
            if ($name === 'blog.index' && ! BlogPost::hasPublished()) {
                continue;
            }

            $pages[] = [
                'url' => route($name),
                'label' => (string) Str::of($name)->replace('.', ' ')->headline(),
            ];
        }

        foreach (Page::active()->orderByDesc('updated_at')->get(['slug', 'title']) as $page) {
            $pages[] = ['url' => url('/'.$page->slug), 'label' => $page->title];
        }

        return $pages;
    }

    /**
     * Published landings — empty on sites with no geographic dimension, or
     * with none published yet.
     *
     * @return list<array{url: string, label: string}>
     */
    public function landings(): array
    {
        if (! config('site.locations')) {
            return [];
        }

        return Landing::query()
            ->published()
            ->orderByDesc('updated_at')
            ->get(['slug', 'title'])
            ->map(fn (Landing $landing): array => [
                'url' => url('/'.$landing->slug),
                'label' => $landing->title,
            ])
            ->all();
    }

    /**
     * Published blog posts — empty until the site has any.
     *
     * @return list<array{url: string, label: string}>
     */
    public function blogPosts(): array
    {
        return BlogPost::published()
            ->orderByDesc('published_at')
            ->get(['slug', 'title'])
            ->map(fn (BlogPost $post): array => [
                'url' => url('/blog/'.$post->slug),
                'label' => $post->title,
            ])
            ->all();
    }
}
