<?php

declare(strict_types=1);

use App\Models\Lead;
use App\Services\Admin\DashboardMetrics;
use Carbon\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow('2026-10-28 10:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

describe('DashboardMetrics', function (): void {
    describe('leadsPerWeek', function (): void {
        it('should count the requests of each week, with empty weeks at zero', function (): void {
            Lead::factory()->count(2)->create(['created_at' => '2026-10-27 09:00:00']);
            Lead::factory()->create(['created_at' => '2026-10-14 09:00:00']);
            Lead::factory()->create(['created_at' => '2026-01-01 09:00:00']);

            $weeks = app(DashboardMetrics::class)->leadsPerWeek(4);

            expect($weeks)->toBe([
                ['week' => '05/10', 'leads' => 0],
                ['week' => '12/10', 'leads' => 1],
                ['week' => '19/10', 'leads' => 0],
                ['week' => '26/10', 'leads' => 2],
            ]);
        });
    });
});
