<?php

declare(strict_types=1);

/**
 * Every `⚡index.blade.php` under the admin — one per listing screen.
 *
 * @return array<int, string>
 */
function adminListings(): array
{
    $listings = glob(resource_path('views/pages/admin/*/⚡index.blade.php')) ?: [];

    sort($listings);

    return $listings;
}

/**
 * What a listing screen is named after, for a readable failure message.
 */
function listingName(string $path): string
{
    return basename(dirname($path));
}

/**
 * Whether a listing's rows are records with a screen of their own.
 *
 * Two kinds of screen fail this and are meant to: one whose rows are parts of
 * something else (`menus`, whose rows are the items of a menu), and one whose
 * rows are moderated in place and never opened (`reviews`). Neither has
 * anywhere to move a row action to, so neither is held to the rule.
 */
function listingHasRecordScreen(string $path): bool
{
    if (listingName($path) === 'menus') {
        return false;
    }

    return file_exists(dirname($path).'/⚡edit.blade.php');
}

describe('AdminListing', function (): void {
    describe('row_actions', function (): void {
        /**
         * Acting on a record happens inside the record. A listing row carries
         * no pencil and no bin: the name in the first column is the way in, and
         * whatever you do next you do from the record's own screen, where the
         * thing you are about to act on is in front of you.
         */
        it('should keep destructive actions out of the rows', function (): void {
            $offenders = [];

            foreach (adminListings() as $listing) {
                if (! listingHasRecordScreen($listing)) {
                    continue;
                }

                $source = (string) file_get_contents($listing);

                if (preg_match('/public function delete\w*\(/', $source, $method)) {
                    $offenders[] = listingName($listing).' still exposes '.rtrim($method[0], '(');
                }

                if (str_contains($source, 'icon="trash"')) {
                    $offenders[] = listingName($listing).' still renders a bin in the row';
                }

                if (str_contains($source, 'icon="pencil-square"')) {
                    $offenders[] = listingName($listing).' still renders a pencil in the row';
                }
            }

            expect($offenders)->toBe([]);
        });

        /**
         * A listing with no way into its records is a dead end. Every screen
         * whose rows are records must reach them from the row itself.
         */
        it('should reach the record from the row', function (): void {
            $offenders = [];

            foreach (adminListings() as $listing) {
                if (! listingHasRecordScreen($listing)) {
                    continue;
                }

                $name = listingName($listing);
                $source = (string) file_get_contents($listing);

                if (! str_contains($source, "route('admin.{$name}.edit'")) {
                    continue;
                }

                if (! str_contains($source, '<x-admin.record-link')) {
                    $offenders[] = $name.' links to its edit screen without x-admin.record-link';
                }
            }

            expect($offenders)->toBe([]);
        });
    });
});
