<?php

declare(strict_types=1);

namespace App\Services\Seo;

use Artesaos\SEOTools\Facades\JsonLdMulti;
use Artesaos\SEOTools\Facades\OpenGraph;
use Artesaos\SEOTools\Facades\SEOMeta;
use Artesaos\SEOTools\Facades\TwitterCard;
use DateTimeInterface;

class SeoService
{
    /**
     * Routes that must NOT be indexed by search engines.
     *
     * Single source of truth: pages call setSEO() without an `index` argument
     * and the service derives the directive from the current route name. The
     * same registry is read by the sitemap to decide which URLs to expose.
     *
     * Each cloned site adds its own non-indexable routes (gracias pages, etc.).
     *
     * @var list<string>
     */
    public const NON_INDEXABLE_ROUTES = [
        // Auth (Fortify)
        'login',
        'password.request',
        'password.reset',
        'password.confirm',
        'password.confirmation',
        // Admin (already auth-gated, defensive noindex)
        'admin.dashboard',
        // Sitemap routes themselves should never end up in a sitemap
        'sitemap',
        'sitemap.pages',
        'sitemap.landings',
        'sitemap.blog',
        // Blog show is parameter-based so it's excluded from /sitemap-pages.xml
        // automatically; the listing /blog IS indexable.
    ];

    protected string $siteName;

    protected string $siteUrl;

    protected ?string $defaultImage;

    protected bool $defaultsApplied = false;

    /**
     * Site-wide nodes (Organization, WebSite) seeded once per request.
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $defaultNodes = [];

    public function __construct(protected SchemaBuilder $schema)
    {
        $this->siteName = (string) config('app.name');
        $this->siteUrl = rtrim((string) config('app.url'), '/');
        $default = config('seo.default_image');
        $this->defaultImage = is_string($default) && $default !== '' ? asset($default) : null;
    }

    public static function isIndexable(?string $routeName): bool
    {
        if ($routeName === null) {
            return true;
        }

        return ! in_array($routeName, self::NON_INDEXABLE_ROUTES, true);
    }

    /**
     * Configure SEO for the current page.
     *
     * When `$index` is null the value is derived from the current route name
     * via NON_INDEXABLE_ROUTES, so pages do not need to repeat the setting.
     * When the page is indexable a WebPage node is automatically appended to
     * the @graph, so most pages only need to pass page-specific nodes
     * (Service, BreadcrumbList, FAQPage…) in `$structuredData`.
     *
     * @param  array<int, array<string, mixed>>  $structuredData  Extra JSON-LD nodes to merge into the @graph
     */
    public function setSEO(
        string $title,
        string $description,
        ?string $url = null,
        ?string $image = null,
        ?bool $index = null,
        bool $follow = true,
        bool $appendSiteName = true,
        array $structuredData = [],
    ): void {
        $url ??= $this->canonicalUrl();
        $image ??= $this->defaultImage;
        $index ??= self::isIndexable(request()->route()?->getName());

        SEOMeta::setTitle($title, $appendSiteName);
        SEOMeta::setDescription($description);
        SEOMeta::setCanonical($url);
        SEOMeta::setRobots(
            ($index ? 'index' : 'noindex').', '.
            ($follow ? 'follow' : 'nofollow').', '.
            'max-snippet:-1, max-video-preview:-1, max-image-preview:large'
        );

        OpenGraph::setTitle($title);
        OpenGraph::setDescription($description);
        OpenGraph::setUrl($url);
        if ($image !== null) {
            $this->addOpenGraphImage($image);
        }

        TwitterCard::setTitle($title);
        TwitterCard::setDescription($description);
        TwitterCard::setUrl($url);
        if ($image !== null) {
            TwitterCard::setImage($image);
        }

        $this->applyDefaultGraph();

        $pageNodes = [];
        if ($index) {
            $pageNodes[] = $this->schema->webPage($url, $title, $description, $image);
        }

        foreach ($structuredData as $node) {
            $pageNodes[] = $node;
        }

        JsonLdMulti::addValue('@graph', [...$this->defaultNodes, ...$pageNodes]);
    }

    /**
     * Add Open Graph and meta tags specific to article-like pages
     * (blog posts and case studies). Must be called after setSEO().
     *
     * @param  array<int, string>  $tags
     */
    public function setArticleMeta(
        DateTimeInterface $publishedAt,
        ?DateTimeInterface $modifiedAt = null,
        ?string $authorUrl = null,
        ?string $section = null,
        array $tags = [],
    ): void {
        $attributes = [
            'published_time' => $publishedAt->format(DateTimeInterface::ATOM),
        ];

        if ($authorUrl !== null) {
            $attributes['author'] = $authorUrl;
        }

        if ($modifiedAt instanceof DateTimeInterface) {
            $attributes['modified_time'] = $modifiedAt->format(DateTimeInterface::ATOM);
        }

        if ($section !== null) {
            $attributes['section'] = $section;
        }

        if ($tags !== []) {
            $attributes['tag'] = $tags;
        }

        OpenGraph::setType('article');
        OpenGraph::setArticle($attributes);
    }

    /**
     * Seed Organization + WebSite on the global @graph (only once per request).
     * Each cloned site can extend this by overriding the service in a child
     * class or via a service provider binding.
     */
    protected function applyDefaultGraph(): void
    {
        if ($this->defaultsApplied) {
            return;
        }

        $this->defaultsApplied = true;

        $organization = config('seo.organization', []);
        $node = [
            '@type' => $organization['type'] ?? ['Organization'],
            '@id' => $this->schema->organizationId,
            'name' => $organization['name'] ?? $this->siteName,
            'url' => $this->siteUrl,
        ];

        if (! empty($organization['logo'])) {
            $node['logo'] = [
                '@type' => 'ImageObject',
                '@id' => $this->siteUrl.'/#logo',
                'url' => asset($organization['logo']),
                'inLanguage' => 'es',
            ];
        }

        if (! empty($organization['area_served'])) {
            $node['areaServed'] = $organization['area_served'];
        }

        if (! empty($organization['same_as'])) {
            $node['sameAs'] = $organization['same_as'];
        }

        $this->defaultNodes[] = $node;

        $this->defaultNodes[] = [
            '@type' => 'WebSite',
            '@id' => $this->schema->websiteId,
            'url' => $this->siteUrl,
            'name' => $this->siteName,
            'inLanguage' => 'es',
            'publisher' => ['@id' => $this->schema->organizationId],
        ];
    }

    protected function addOpenGraphImage(string $imageUrl): void
    {
        $localPath = $this->localPathForAsset($imageUrl);
        [$width, $height] = $this->imageSize($localPath);
        $type = $this->mimeFromExtension($imageUrl);

        $attributes = ['type' => $type];
        if ($width > 0 && $height > 0) {
            $attributes['width'] = $width;
            $attributes['height'] = $height;
        }

        OpenGraph::addImage($imageUrl, $attributes);
    }

    /**
     * @return array{0: int, 1: int}
     */
    protected function imageSize(?string $absolutePath, int $fallbackWidth = 0, int $fallbackHeight = 0): array
    {
        if ($absolutePath === null || ! is_file($absolutePath)) {
            return [$fallbackWidth, $fallbackHeight];
        }

        $info = @getimagesize($absolutePath);

        if ($info === false) {
            return [$fallbackWidth, $fallbackHeight];
        }

        return [$info[0], $info[1]];
    }

    protected function localPathForAsset(string $assetUrl): ?string
    {
        $path = parse_url($assetUrl, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return null;
        }

        return public_path(ltrim($path, '/'));
    }

    protected function mimeFromExtension(string $url): string
    {
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));

        return match ($extension) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            default => 'image/jpeg',
        };
    }

    /**
     * Canonical for the current request: the plain path, self-referencing a
     * paginated page (`?page=2`) so each page of a listing canonicalizes to
     * itself rather than collapsing into page 1. Every other query param
     * (filters, search) is dropped — those variants are not meant to be
     * indexed separately from the plain listing.
     */
    protected function canonicalUrl(): string
    {
        $page = request()->query('page');

        if (is_string($page) && ctype_digit($page) && (int) $page > 1) {
            return request()->url().'?page='.$page;
        }

        return request()->url();
    }
}
