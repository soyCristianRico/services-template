<?php

declare(strict_types=1);

namespace App\Services\Seo;

/**
 * Rewrites links that point at this site into the shape its addresses actually
 * have: a relative path, with no trailing slash.
 *
 * Content cloned from another install arrives with that install's addresses written
 * inside it, and a CMS like WordPress ends every one of them in a slash. Nothing
 * looks broken, which is the problem: Laravel trims the slash when it routes, so
 * `/faqs/` answers 200 exactly like `/faqs`. What it costs is invisible from the
 * browser — every one of those links points at an address that is not the one the
 * canonical, the sitemap and the internal navigation agree on.
 *
 * A host is internal only when it matches exactly. A subdomain — the shop, the
 * campus, the client area — is another system entirely, and turning one of its links
 * into a relative path would point it at a page of this site that does not exist.
 */
final class InternalLink
{
    /**
     * Addresses of another kind. They are not paths and must survive untouched.
     */
    protected const OPAQUE_SCHEMES = ['mailto:', 'tel:', 'sms:', 'javascript:', 'data:'];

    /** @param  list<string>  $internalHosts  Lower-case hosts that ARE this site. */
    public function __construct(protected array $internalHosts) {}

    /**
     * Build the rewriter for this installation.
     *
     * `www.` counts as the same site because it is the same site: it answers on
     * the canonical host after a redirect nobody should be forced through.
     *
     * @param  list<string>  $extraHosts  Hosts of a database being cleaned from
     *                                    elsewhere, where `app.url` is not the
     *                                    public address.
     */
    public static function forSite(array $extraHosts = []): self
    {
        $host = mb_strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        $hosts = [$host, 'www.'.$host];

        foreach ($extraHosts as $extra) {
            $extra = mb_strtolower(trim($extra));

            // Accepts a full URL as readily as a bare host: both are what somebody
            // has at hand when they run this.
            if (str_contains($extra, '://')) {
                $extra = (string) parse_url($extra, PHP_URL_HOST);
            }

            if ($extra === '') {
                continue;
            }

            $hosts[] = $extra;
            $hosts[] = 'www.'.$extra;
        }

        return new self(array_values(array_unique(array_filter($hosts, fn (string $h): bool => $h !== '' && $h !== 'www.'))));
    }

    /**
     * Rewrite every `href` of every anchor in a blob of HTML.
     *
     * Only the `href` of an `<a>`, and only the part of it before the query: an
     * asset path is not a page, and never touching the query string means an
     * `&amp;` written by the editor comes out the far side exactly as it went in.
     */
    public function inHtml(string $html): string
    {
        if ($html === '' || stripos($html, 'href') === false) {
            return $html;
        }

        return (string) preg_replace_callback(
            '/(<a\b[^>]*?\bhref\s*=\s*)(?:"([^"]*)"|\'([^\']*)\')/is',
            function (array $matches): string {
                $quote = isset($matches[3]) ? "'" : '"';
                $href = $matches[3] ?? $matches[2];

                return $matches[1].$quote.$this->normalize($href).$quote;
            },
            $html
        );
    }

    /**
     * Rewrite a single address held in its own column — a menu item's URL, a CTA.
     *
     * Anything this cannot vouch for comes back byte for byte: an external host, a
     * subdomain, a `mailto:`, a bare relative segment. Rewriting only what it is
     * sure about is what makes the command safe to run over a live database.
     */
    public function normalize(string $url): string
    {
        $value = trim($url);

        if ($value === '' || str_starts_with($value, '#')) {
            return $url;
        }

        foreach (self::OPAQUE_SCHEMES as $scheme) {
            if (stripos($value, $scheme) === 0) {
                return $url;
            }
        }

        // The query and the fragment travel along untouched. Only a path can carry
        // the trailing slash this exists to remove.
        $suffix = '';
        $cut = strcspn($value, '?#');

        if ($cut < strlen($value)) {
            $suffix = substr($value, $cut);
            $value = substr($value, 0, $cut);
        }

        $path = $this->toSitePath($value);

        if ($path === null) {
            return $url;
        }

        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        return ($path === '' ? '/' : $path).$suffix;
    }

    /** Whether rewriting this address would change it. */
    public function changes(string $url): bool
    {
        return $this->normalize($url) !== $url;
    }

    /**
     * The path this address resolves to on this site, or null when it is not an
     * address into this site that can be rewritten safely.
     */
    protected function toSitePath(string $value): ?string
    {
        if (str_contains($value, '://')) {
            return $this->isInternalHost((string) parse_url($value, PHP_URL_HOST))
                ? ((string) parse_url($value, PHP_URL_PATH) ?: '/')
                : null;
        }

        // `//example.com/x` is a protocol-relative URL; `//curso-antiguo` is a path
        // someone typed with a doubled slash. A host carries a dot, a path segment
        // almost never — the same reading `RedirectPath` uses.
        if (str_starts_with($value, '//')) {
            $firstSegment = strtok(substr($value, 2), '/') ?: '';

            if (! str_contains($firstSegment, '.') || ! $this->isInternalHost($firstSegment)) {
                return null;
            }

            return '/'.ltrim(substr($value, 2 + strlen($firstSegment)), '/');
        }

        // A bare relative segment (`contacto/`) is left alone: it resolves against
        // whatever page it sits on, so rewriting it is guesswork.
        return str_starts_with($value, '/') ? $value : null;
    }

    protected function isInternalHost(string $host): bool
    {
        return in_array(mb_strtolower($host), $this->internalHosts, true);
    }
}
