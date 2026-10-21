<?php

declare(strict_types=1);

/**
 * The views that had @php when the rule arrived. The list only shrinks: move
 * a view's logic to its component and take it off.
 *
 * @return list<string>
 */
function bladeViewsAllowedToKeepPhp(): array
{
    return [
        'components/cookies/banner.blade.php',
        'components/cookies/consent-mode-default.blade.php',
        'components/site/admin-bar.blade.php',
        'components/site/pagination-seo-links.blade.php',
        'layouts/admin.blade.php',
        'pages/admin/blog/⚡index.blade.php',
        'pages/admin/categories/⚡index.blade.php',
        'pages/admin/locations/⚡index.blade.php',
        'pages/admin/menus/⚡index.blade.php',
        'sitemap/index.blade.php',
        'sitemap/urlset.blade.php',
    ];
}

/**
 * Every view under resources/views that uses @php, relative to that folder.
 *
 * @return list<string>
 */
function bladeViewsWithPhp(): array
{
    $root = dirname(__DIR__, 2).'/resources/views';
    $found = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        if (str_ends_with($file->getFilename(), '.blade.php') && preg_match('/@php\b/', (string) file_get_contents($file->getPathname())) === 1) {
            $found[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        }
    }

    sort($found);

    return $found;
}

describe('Blade views', function (): void {
    describe('@php', function (): void {
        it('should not appear in a view: the logic belongs in its component', function (): void {
            $new = array_values(array_diff(bladeViewsWithPhp(), bladeViewsAllowedToKeepPhp()));

            expect($new)->toBe([], 'Move this logic to the component (a #[Computed] property, or props for an anonymous component): '.implode(', ', $new));
        });

        it('should take a view off the list once it no longer has it', function (): void {
            $cleaned = array_values(array_diff(bladeViewsAllowedToKeepPhp(), bladeViewsWithPhp()));

            expect($cleaned)->toBe([], 'These views no longer use @php: take them off bladeViewsAllowedToKeepPhp(): '.implode(', ', $cleaned));
        });
    });
});
