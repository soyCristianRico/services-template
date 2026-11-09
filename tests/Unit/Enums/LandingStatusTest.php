<?php

declare(strict_types=1);

use App\Enums\LandingStatus;

describe('LandingStatus', function (): void {
    describe('label', function (): void {
        it('should return the Spanish label of each case', function (LandingStatus $case, string $expected): void {
            expect($case->label())->toBe($expected);
        })->with([
            [LandingStatus::Draft, 'Borrador'],
            [LandingStatus::Scheduled, 'Programada'],
            [LandingStatus::Published, 'Publicada'],
        ]);
    });
    describe('cases', function (): void {
        it('should keep the exact domain declared by the origin CMS', function (): void {
            expect(LandingStatus::cases())->toHaveCount(3);
        });

        it('should back each case with its stored string value', function (LandingStatus $case, string $value): void {
            expect($case->value)->toBe($value);
        })->with([
            [LandingStatus::Draft, 'draft'],
            [LandingStatus::Scheduled, 'scheduled'],
            [LandingStatus::Published, 'published'],
        ]);
    });
    describe('color', function (): void {
        it('should map each case to a colour', function (LandingStatus $case, string $expected): void {
            expect($case->color())->toBe($expected);
        })->with([
            [LandingStatus::Draft, 'zinc'],
            [LandingStatus::Scheduled, 'amber'],
            [LandingStatus::Published, 'green'],
        ]);
    });
});
