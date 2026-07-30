<?php

declare(strict_types=1);

use App\Enums\MenuLocation;

describe('MenuLocation', function () {
    describe('label', function () {
        it('should return the Spanish label of each case', function (MenuLocation $case, string $expected) {
            expect($case->label())->toBe($expected);
        })->with([
            [MenuLocation::Header, 'Cabecera'],
            [MenuLocation::Footer, 'Pie de página'],
            [MenuLocation::Legal, 'Legales'],
        ]);
    });
    describe('cases', function () {
        it('should keep the exact domain declared by the origin CMS', function () {
            expect(MenuLocation::cases())->toHaveCount(3);
        });

        it('should back each case with its stored string value', function (MenuLocation $case, string $value) {
            expect($case->value)->toBe($value);
        })->with([
            [MenuLocation::Header, 'header'],
            [MenuLocation::Footer, 'footer'],
            [MenuLocation::Legal, 'legal'],
        ]);
    });
});
