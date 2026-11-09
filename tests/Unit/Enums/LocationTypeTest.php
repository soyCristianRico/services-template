<?php

declare(strict_types=1);

use App\Enums\LocationType;

describe('LocationType', function (): void {
    describe('label', function (): void {
        it('should return the Spanish label of each case', function (LocationType $case, string $expected): void {
            expect($case->label())->toBe($expected);
        })->with([
            [LocationType::Country, 'País'],
            [LocationType::Region, 'Comunidad / Región'],
            [LocationType::Province, 'Provincia'],
            [LocationType::City, 'Ciudad'],
            [LocationType::District, 'Distrito / Barrio'],
        ]);
    });
    describe('cases', function (): void {
        it('should keep the exact domain declared by the origin CMS', function (): void {
            expect(LocationType::cases())->toHaveCount(5);
        });

        it('should back each case with its stored string value', function (LocationType $case, string $value): void {
            expect($case->value)->toBe($value);
        })->with([
            [LocationType::Country, 'country'],
            [LocationType::Region, 'region'],
            [LocationType::Province, 'province'],
            [LocationType::City, 'city'],
            [LocationType::District, 'district'],
        ]);
    });
});
