<?php

declare(strict_types=1);

use App\Enums\MenuLocation;

describe('MenuLocation', function (): void {
    describe('label', function (): void {
        it('should return the Spanish label of each case', function (MenuLocation $case, string $expected): void {
            expect($case->label())->toBe($expected);
        })->with([
            [MenuLocation::Header, 'Cabecera'],
            [MenuLocation::Footer, 'Pie de página'],
            [MenuLocation::Legal, 'Legales'],
        ]);
    });
    describe('cases', function (): void {
        it('should keep the exact domain declared by the origin CMS', function (): void {
            expect(MenuLocation::cases())->toHaveCount(3);
        });

        it('should back each case with its stored string value', function (MenuLocation $case, string $value): void {
            expect($case->value)->toBe($value);
        })->with([
            [MenuLocation::Header, 'header'],
            [MenuLocation::Footer, 'footer'],
            [MenuLocation::Legal, 'legal'],
        ]);
    });
});
