<?php

declare(strict_types=1);

use App\Enums\LeadStatus;

describe('LeadStatus', function (): void {
    describe('label', function (): void {
        it('should return the Spanish label of each case', function (LeadStatus $case, string $expected): void {
            expect($case->label())->toBe($expected);
        })->with([
            [LeadStatus::New, 'Nuevo'],
            [LeadStatus::Contacted, 'Contactado'],
            [LeadStatus::Qualified, 'Cualificado'],
            [LeadStatus::Lost, 'Perdido'],
        ]);
    });
    describe('cases', function (): void {
        it('should keep the exact domain declared by the origin CMS', function (): void {
            expect(LeadStatus::cases())->toHaveCount(4);
        });

        it('should back each case with its stored string value', function (LeadStatus $case, string $value): void {
            expect($case->value)->toBe($value);
        })->with([
            [LeadStatus::New, 'new'],
            [LeadStatus::Contacted, 'contacted'],
            [LeadStatus::Qualified, 'qualified'],
            [LeadStatus::Lost, 'lost'],
        ]);
    });
});
