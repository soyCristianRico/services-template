<?php

declare(strict_types=1);

use App\Enums\AdminGroup;

describe('AdminGroup', function (): void {
    describe('label', function (): void {
        it('should return the heading the sidebar prints', function (AdminGroup $case, string $expected): void {
            expect($case->label())->toBe($expected);
        })->with([
            [AdminGroup::Catalog, 'Catálogo'],
            [AdminGroup::Web, 'Web'],
            [AdminGroup::Seo, 'Posicionamiento'],
        ]);
    });

    describe('cases', function (): void {
        // Three kinds of work: what the site sells, the site itself and how it
        // is found. A fourth heading needs a fourth kind, not a catch-all.
        it('should have three groups', function (): void {
            expect(AdminGroup::cases())->toHaveCount(3);
        });
    });
});
