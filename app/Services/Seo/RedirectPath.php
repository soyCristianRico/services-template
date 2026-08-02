<?php

declare(strict_types=1);

namespace App\Services\Seo;

/**
 * Address handling shared by the form, the importer, the resolver and the 404 log.
 *
 * It lives in one place because every one of them has to agree on what "the same
 * address" means. The day the form stores `/Contacto/` and the resolver looks up
 * `/contacto`, the redirect exists in the panel and does nothing on the site.
 */
final class RedirectPath
{
    /**
     * Reduce an address to the canonical form stored in `redirects.source` and
     * looked up on every request: leading slash, no trailing slash, no host, no
     * query, lower case.
     *
     * Accepts what a person actually pastes — a full URL off the old site, a bare
     * slug, something with a stray trailing slash — because that is what arrives
     * from a WordPress export.
     */
    public static function normalize(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '/';
        }

        // A pasted absolute URL keeps only its path.
        if (self::isExternal($value)) {
            $value = (string) parse_url($value, PHP_URL_PATH);
        }

        // The query travels separately — `preserve_query` decides its fate — and a
        // fragment never reaches the server at all.
        $value = strtok($value, '?#') ?: '';

        $value = '/'.trim($value, '/');

        // Collapse the double slashes that show up when two path fragments are
        // concatenated by hand.
        $value = (string) preg_replace('#/+#', '/', $value);

        return mb_strtolower($value);
    }

    /**
     * Wrap a bare pattern so it can be handed to preg_*.
     *
     * The delimiter is `\x01` on purpose. Any printable character can legitimately
     * appear inside a URL pattern, so picking one would force us to escape it and
     * break the day somebody escapes it themselves. A control byte cannot be typed
     * into the form, so no pattern will ever contain it.
     */
    public static function compilePattern(string $pattern): string
    {
        return "\x01".$pattern."\x01i";
    }

    /**
     * Whether a pattern compiles. Checked before saving, because an invalid one
     * would throw on every single request the middleware handles.
     */
    public static function isValidPattern(string $pattern): bool
    {
        if ($pattern === '') {
            return false;
        }

        return @preg_match(self::compilePattern($pattern), '') !== false;
    }

    /**
     * Whether an address is off limits to the redirect machinery.
     *
     * The admin prefix is the one that matters: without this, a careless pattern
     * could bounce the client out of the very screen they need in order to delete
     * it, and nobody could get back in without a developer.
     */
    public static function isExcluded(string $path): bool
    {
        $path = trim(self::normalize($path), '/');

        foreach ((array) config('redirects.excluded_prefixes', []) as $prefix) {
            $prefix = mb_strtolower(trim((string) $prefix, '/'));

            if ($prefix !== '' && ($path === $prefix || str_starts_with($path, $prefix.'/'))) {
                return true;
            }
        }

        return false;
    }

    /** Whether an address points off this site and must be used verbatim. */
    public static function isExternal(string $destination): bool
    {
        if (str_contains($destination, '://')) {
            return true;
        }

        if (! str_starts_with($destination, '//')) {
            return false;
        }

        // `//example.com/x` is a real protocol-relative URL; `//curso-antiguo` is
        // a path someone typed with a doubled slash. Telling them apart matters:
        // read the wrong way, the second one sends visitors to a host that does
        // not exist. A host always carries a dot, a path segment almost never.
        $firstSegment = strtok(substr($destination, 2), '/') ?: '';

        return str_contains($firstSegment, '.');
    }

    /**
     * Glue an incoming query string onto a destination that may already carry one.
     */
    public static function withQuery(string $destination, string $query): string
    {
        if ($query === '') {
            return $destination;
        }

        // The destination's own parameters win: they were chosen deliberately when
        // the redirect was written, the incoming ones are whatever the visitor
        // happened to arrive with.
        $separator = str_contains($destination, '?') ? '&' : '?';

        return $destination.$separator.$query;
    }
}
