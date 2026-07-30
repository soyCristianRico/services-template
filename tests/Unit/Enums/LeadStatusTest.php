<?php

declare(strict_types=1);

use App\Enums\LeadStatus;

describe('LeadStatus', function () {
    describe('label', function () {
        it('should return the Spanish label of each case', function (LeadStatus $case, string $expected) {
            expect($case->label())->toBe($expected);
        })->with([
            [LeadStatus::New, 'Nuevo'],
            [LeadStatus::Contacted, 'Contactado'],
            [LeadStatus::Qualified, 'Cualificado'],
            [LeadStatus::Lost, 'Perdido'],
        ]);
    });
    describe('cases', function () {
        it('should keep the exact domain declared by the origin CMS', function () {
            expect(LeadStatus::cases())->toHaveCount(4);
        });

        it('should back each case with its stored string value', function (LeadStatus $case, string $value) {
            expect($case->value)->toBe($value);
        })->with([
            [LeadStatus::New, 'new'],
            [LeadStatus::Contacted, 'contacted'],
            [LeadStatus::Qualified, 'qualified'],
            [LeadStatus::Lost, 'lost'],
        ]);
    });
});
