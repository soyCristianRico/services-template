<?php

declare(strict_types=1);

use App\Enums\LeadStatus;
use App\Models\Landing;
use App\Models\Lead;

describe('Lead', function () {
    describe('relations', function () {
        it('should belong to a landing optionally', function () {
            $landing = Landing::factory()->create();
            $lead = Lead::factory()->fromLanding($landing)->create();

            expect($lead->landing->id)->toBe($landing->id);
        });

        it('should accept null landing_id for direct contacts', function () {
            $lead = Lead::factory()->create();

            expect($lead->landing_id)->toBeNull();
            expect($lead->landing)->toBeNull();
        });
    });

    describe('casts', function () {
        it('should cast status to LeadStatus enum', function () {
            $lead = Lead::factory()->create();

            expect($lead->status)->toBeInstanceOf(LeadStatus::class);
            expect($lead->status)->toBe(LeadStatus::New);
        });

        it('should cast payload to array', function () {
            $lead = Lead::factory()->create([
                'payload' => ['needs_invoice' => true, 'company' => 'Acme'],
            ]);

            expect($lead->payload)->toBeArray()
                ->and($lead->payload['company'])->toBe('Acme');
        });
    });

    describe('scopes', function () {
        it('should scope by status', function () {
            Lead::factory()->count(3)->create();
            Lead::factory()->ofStatus(LeadStatus::Contacted)->count(2)->create();

            expect(Lead::ofStatus(LeadStatus::New)->count())->toBe(3);
            expect(Lead::ofStatus(LeadStatus::Contacted)->count())->toBe(2);
        });

        it('should scope to new leads via shortcut', function () {
            Lead::factory()->count(2)->create();
            Lead::factory()->ofStatus(LeadStatus::Qualified)->create();

            expect(Lead::new()->count())->toBe(2);
        });
    });
});
