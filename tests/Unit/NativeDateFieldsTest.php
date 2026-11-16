<?php

declare(strict_types=1);

/**
 * The views that still had a browser date field when the rule arrived. The
 * list only shrinks: move a view's fields to Flux's calendar in Spanish and
 * take it off.
 *
 * @return list<string>
 */
function viewsAllowedToKeepNativeDateFields(): array
{
    return [
        'pages/admin/blog/partials/publication.blade.php',
        'pages/admin/landings/⚡edit.blade.php',
    ];
}

/**
 * Every view under resources/views, relative to that folder, with a field
 * that follows the browser's language: a native date, time or month input,
 * or a Flux calendar without a locale.
 *
 * @return list<string>
 */
function viewsWithNativeDateFields(): array
{
    $root = dirname(__DIR__, 2).'/resources/views';
    $found = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        if (! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        $contents = (string) file_get_contents($file->getPathname());
        $nativeField = preg_match('/type=["\'](date|time|datetime-local|month)["\']/', $contents) === 1;
        $calendarWithoutLocale = preg_match('/<flux:date-picker(?=[\s>])(?![^>]*\blocale=)[^>]*>/', $contents) === 1;

        if ($nativeField || $calendarWithoutLocale) {
            $found[] = substr($file->getPathname(), strlen($root) + 1);
        }
    }

    sort($found);

    return $found;
}

describe('Date fields', function (): void {
    describe('views', function (): void {
        it('should use Flux calendar in Spanish, not a field in the browser language', function (): void {
            $new = array_values(array_diff(viewsWithNativeDateFields(), viewsAllowedToKeepNativeDateFields()));

            expect($new)->toBe([], 'Use <flux:date-picker locale="es-ES"> in these views (see ui.md, «Selectores y fechas»)');
        });

        it('should take a view off the list once its fields are moved', function (): void {
            $fixed = array_values(array_diff(viewsAllowedToKeepNativeDateFields(), viewsWithNativeDateFields()));

            expect($fixed)->toBe([], 'These views no longer have one: take them off viewsAllowedToKeepNativeDateFields()');
        });
    });
});
