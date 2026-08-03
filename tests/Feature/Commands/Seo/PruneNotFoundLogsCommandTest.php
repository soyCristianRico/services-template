<?php

declare(strict_types=1);

use App\Models\NotFoundLog;

describe('PruneNotFoundLogsCommand', function (): void {
    describe('handle', function (): void {
        it('should drop the addresses nobody has visited in a long time', function (): void {
            NotFoundLog::factory()->create(['path' => '/vieja', 'last_seen_at' => now()->subDays(120)]);

            $this->artisan('redirects:prune')->assertSuccessful();

            expect(NotFoundLog::query()->count())->toBe(0);
        });

        it('should keep an old address that is still being hit', function (): void {
            // Judged by last sighting, not by age: a link broken three years ago
            // and still followed today is the one most worth fixing.
            NotFoundLog::factory()->create([
                'path' => '/vieja',
                'first_seen_at' => now()->subYears(3),
                'last_seen_at' => now()->subDay(),
            ]);

            $this->artisan('redirects:prune')->assertSuccessful();

            expect(NotFoundLog::query()->count())->toBe(1);
        });

        it('should honour the retention window from the config', function (): void {
            config()->set('redirects.not_found_retention_days', 5);
            NotFoundLog::factory()->create(['last_seen_at' => now()->subDays(10)]);

            $this->artisan('redirects:prune')->assertSuccessful();

            expect(NotFoundLog::query()->count())->toBe(0);
        });
    });
});
