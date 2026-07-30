<?php

declare(strict_types=1);

use App\Enums\MenuItemType;

describe('MenuItemType', function () {
    describe('label', function () {
        it('should return the Spanish label of each case', function (MenuItemType $case, string $expected) {
            expect($case->label())->toBe($expected);
        })->with([
            [MenuItemType::Link, 'Enlace'],
            [MenuItemType::DynamicBlock, 'Bloque dinámico'],
        ]);
    });
    describe('cases', function () {
        it('should keep the exact domain declared by the origin CMS', function () {
            expect(MenuItemType::cases())->toHaveCount(2);
        });

        it('should back each case with its stored string value', function (MenuItemType $case, string $value) {
            expect($case->value)->toBe($value);
        })->with([
            [MenuItemType::Link, 'link'],
            [MenuItemType::DynamicBlock, 'dynamic_block'],
        ]);
    });
});
