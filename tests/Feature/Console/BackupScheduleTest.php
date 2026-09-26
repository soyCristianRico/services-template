<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;

function backupEvents(): array
{
    return collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains((string) $event->command, 'backup:'))
        ->all();
}

describe('Backup schedule', function () {
    describe('storage credentials', function () {
        it('should skip every backup command when the disk is not configured', function () {
            config(['filesystems.disks.s3.key' => null]);

            expect(backupEvents())->toHaveCount(3)
                ->each(fn ($event) => $event->filtersPass(app())->toBeFalse());
        });

        it('should schedule every backup command when the disk is configured', function () {
            config([
                'filesystems.disks.s3.key' => 'key',
                'filesystems.disks.s3.secret' => 'secret',
                'filesystems.disks.s3.bucket' => 'bucket',
                'filesystems.disks.s3.endpoint' => 'https://s3.example.com',
            ]);

            expect(backupEvents())->toHaveCount(3)
                ->each(fn ($event) => $event->filtersPass(app())->toBeTrue());
        });
    });
});
