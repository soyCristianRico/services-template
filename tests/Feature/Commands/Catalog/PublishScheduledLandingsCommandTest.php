<?php

declare(strict_types=1);

use App\Enums\LandingStatus;
use App\Models\Landing;

use function Pest\Laravel\artisan;

describe('PublishScheduledLandingsCommand', function (): void {
    describe('handle', function (): void {
        it('should publish scheduled landings whose date has passed', function (): void {
            $due = Landing::factory()->scheduled(now()->subDay())->create();

            artisan('landings:publish-scheduled')->assertSuccessful();

            expect($due->refresh()->status)->toBe(LandingStatus::Published);
        });

        it('should leave future scheduled landings untouched', function (): void {
            $future = Landing::factory()->scheduled(now()->addDays(2))->create();

            artisan('landings:publish-scheduled')->assertSuccessful();

            expect($future->refresh()->status)->toBe(LandingStatus::Scheduled);
        });

        it('should ignore drafts', function (): void {
            $draft = Landing::factory()->draft()->create();

            artisan('landings:publish-scheduled')->assertSuccessful();

            expect($draft->refresh()->status)->toBe(LandingStatus::Draft);
        });
    });
});
