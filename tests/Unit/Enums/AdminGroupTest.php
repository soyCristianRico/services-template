<?php

declare(strict_types=1);

use App\Enums\AdminGroup;

describe('AdminGroup', function () {
    describe('label', function () {
        it('should return the heading the sidebar prints', function (AdminGroup $case, string $expected) {
            expect($case->label())->toBe($expected);
        })->with([
            [AdminGroup::Catalog, 'Catálogo'],
            [AdminGroup::Web, 'Web'],
            [AdminGroup::Seo, 'Posicionamiento'],
        ]);
    });

    describe('cases', function () {
        // Three kinds of work: what the site sells, the site itself and how it
        // is found. A fourth heading needs a fourth kind, not a catch-all.
        it('should have three groups', function () {
            expect(AdminGroup::cases())->toHaveCount(3);
        });
    });
});
