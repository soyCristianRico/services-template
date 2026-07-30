<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Landing;
use App\Models\Page;
use App\Services\Seo\SeoService;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

class SitemapController
{
    public function index(): Response
    {
        $sitemaps = Cache::remember(
            'sitemap.index',
            now()->addHour(),
            fn (): array => array_values(array_filter([
                ['loc' => route('sitemap.pages'), 'lastmod' => $this->latestLastmod($this->pageUrls())],
                // Only advertise the landings sub-sitemap on sites that have a
                // geographic dimension; without it the route does not exist.
                config('site.locations')
                    ? ['loc' => route('sitemap.landings'), 'lastmod' => $this->latestLastmod($this->landingUrls())]
                    : null,
                // Only advertise the blog sub-sitemap once there is content.
                BlogPost::hasPublished()
                    ? ['loc' => route('sitemap.blog'), 'lastmod' => $this->latestLastmod($this->blogUrls())]
                    : null,
            ])),
        );

        return response()
            ->view('sitemap.index', ['sitemaps' => $sitemaps])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function pages(): Response
    {
        return $this->respondUrlset('sitemap.pages', fn (): array => $this->pageUrls());
    }

    public function landings(): Response
    {
        return $this->respondUrlset('sitemap.landings', fn (): array => $this->landingUrls());
    }

    public function blog(): Response
    {
        return $this->respondUrlset('sitemap.blog', fn (): array => $this->blogUrls());
    }

    /**
     * @param  callable(): list<array{loc: string, lastmod: ?string}>  $builder
     */
    protected function respondUrlset(string $cacheKey, callable $builder): Response
    {
        $urls = Cache::remember($cacheKey, now()->addHour(), $builder);

        return response()
            ->view('sitemap.urlset', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * Controller backing every full-page Livewire route (`Route::livewire`).
     * Used as a fail-closed allowlist: only the app's own page routes qualify,
     * so package- and framework-registered routes (Sanctum CSRF, Flux/Livewire
     * assets, Fortify, MCP, health check…) can never leak into the sitemap.
     */
    protected const PAGE_CONTROLLER = 'Livewire\Mechanisms\HandleRouting\LivewirePageController';

    /**
     * Static pages: every parameter-less indexable Livewire page route. The
     * Landing wildcard route has a {slug} parameter so it's automatically
     * excluded — landings live in their own sub-sitemap.
     *
     * @return list<array{loc: string, lastmod: ?string}>
     */
    protected function pageUrls(): array
    {
        $urls = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            if ($name === null) {
                continue;
            }

            // Only the app's own full-page Livewire routes are public pages.
            // Everything else (asset/CSRF/health/MCP routes) is excluded by
            // construction rather than relying on an exhaustive blocklist.
            if (! str_ends_with($route->getActionName(), self::PAGE_CONTROLLER)) {
                continue;
            }

            if ($route->parameterNames() !== []) {
                continue;
            }

            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            // Any route gated by auth is non-public by definition — skip
            // without needing to enumerate it in NON_INDEXABLE_ROUTES.
            if (in_array('auth', $route->gatherMiddleware(), true)) {
                continue;
            }

            if (! SeoService::isIndexable($name)) {
                continue;
            }

            // /blog is noindex while empty (see BlogPost::hasPublished) — keep it
            // out of the sitemap too until it has published posts.
            if ($name === 'blog.index' && ! BlogPost::hasPublished()) {
                continue;
            }

            $urls[] = [
                'loc' => route($name),
                'lastmod' => $this->staticPageLastmod($name),
            ];
        }

        foreach (Page::active()->orderByDesc('updated_at')->get(['slug', 'updated_at']) as $page) {
            $urls[] = [
                'loc' => url('/'.$page->slug),
                'lastmod' => $page->updated_at?->toIso8601String(),
            ];
        }

        return $urls;
    }

    /**
     * @return list<array{loc: string, lastmod: ?string}>
     */
    protected function landingUrls(): array
    {
        return Landing::query()
            ->published()
            ->orderByDesc('updated_at')
            ->get(['slug', 'updated_at'])
            ->map(fn (Landing $landing): array => [
                'loc' => url('/'.$landing->slug),
                'lastmod' => $landing->updated_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return list<array{loc: string, lastmod: ?string}>
     */
    protected function blogUrls(): array
    {
        $urls = [];

        // The /blog index is in the pages sub-sitemap (parameterless route),
        // so this sub-sitemap is purely individual posts.
        foreach (BlogPost::published()->orderByDesc('published_at')->get(['slug', 'updated_at']) as $post) {
            $urls[] = [
                'loc' => url('/blog/'.$post->slug),
                'lastmod' => $post->updated_at?->toIso8601String(),
            ];
        }

        return $urls;
    }

    protected function staticPageLastmod(string $routeName): ?string
    {
        $bladeName = str_replace('.', '/', $routeName);

        $candidates = [
            $this->fileLastmod(resource_path("views/pages/⚡{$bladeName}.blade.php")),
            $this->fileLastmod(resource_path("views/pages/{$bladeName}/⚡index.blade.php")),
        ];

        return $this->maxIso($candidates);
    }

    protected function fileLastmod(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        return Carbon::createFromTimestamp(filemtime($path))->toIso8601String();
    }

    /**
     * @param  list<array{loc: string, lastmod: ?string}>  $urls
     */
    protected function latestLastmod(array $urls): ?string
    {
        return $this->maxIso(array_column($urls, 'lastmod'));
    }

    /**
     * @param  list<?string>  $values
     */
    protected function maxIso(array $values): ?string
    {
        $latest = null;

        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }

            $carbon = Carbon::parse($value);

            if (! $latest instanceof Carbon || $carbon->greaterThan($latest)) {
                $latest = $carbon;
            }
        }

        return $latest?->toIso8601String();
    }
}
