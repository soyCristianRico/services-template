<?php

declare(strict_types=1);

use App\Notifications\Monitoring\DeveloperNotifiable;
use Spatie\Backup\Config\Config;
use Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification;
use Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes;

/**
 * The backup config is published from the package, so a `vendor:publish --force`
 * silently reverts every decision made here. These are the ones that would not
 * be noticed until the day somebody needs to restore.
 */
describe('config/backup.php', function (): void {
    describe('source', function (): void {
        it('should include the uploaded media and the private disk', function (): void {
            expect(config('backup.backup.source.files.include'))
                ->toContain(storage_path('app/public'))
                ->toContain(storage_path('app/private'));
        });

        it('should include the env file', function (): void {
            expect(config('backup.backup.source.files.include'))->toContain(base_path('.env'));
        });

        it('should not include the temporary directory of the backup itself', function (): void {
            expect(config('backup.backup.source.files.exclude'))
                ->toContain(storage_path('app/backup-temp'));
        });

        it('should back up the configured database connection', function (): void {
            expect(config('backup.backup.source.databases'))->toBe([config('database.default')]);
        });
    });

    describe('destination', function (): void {
        it('should write into the disk root, not a subdirectory', function (): void {
            // On Drive, listing a missing subdirectory throws, and the check runs
            // before the write that would have created it.
            expect(config('backup.backup.name'))->toBe('');
        });

        it('should monitor the same directory it writes to', function (): void {
            // A mismatch reports a directory nothing is written to as healthy.
            expect(config('backup.monitor_backups.0.name'))->toBe(config('backup.backup.name'));
        });

        it('should still name the site in the alerts', function (): void {
            // spatie builds it from app.name, not from backup.name.
            expect(config('app.name'))->not->toBeEmpty();
        });

        it('should tell its archives apart by prefix', function (): void {
            // With one flat directory, the prefix is what identifies the file.
            expect(config('backup.backup.destination.filename_prefix'))->not->toBeEmpty();
        });

        it('should write to google drive', function (): void {
            expect(config('backup.backup.destination.disks'))->toBe(['google'])
                ->and(config('filesystems.disks.google.driver'))->toBe('google');
        });

        it('should monitor the same disk it writes to', function (): void {
            // Monitoring a disk nothing is written to reports a healthy backup
            // forever, which is worse than no monitoring at all.
            expect(config('backup.monitor_backups.0.disks'))
                ->toBe(config('backup.backup.destination.disks'));
        });

        it('should keep the hard size limit below the health check threshold', function (): void {
            // Otherwise the monitor complains about space the cleanup was about
            // to free anyway, every single day.
            $cleanupCeiling = config('backup.cleanup.default_strategy.delete_oldest_backups_when_using_more_megabytes_than');
            $monitorCeiling = config('backup.monitor_backups.0.health_checks')[MaximumStorageInMegabytes::class] ?? null;

            expect($monitorCeiling)->toBeInt()
                ->and($cleanupCeiling)->toBeLessThan($monitorCeiling);
        });
    });

    describe('encryption', function (): void {
        it('should encrypt the archive', function (): void {
            // The zip carries buyer emails, leads with their GDPR consent, the
            // sold PDFs and a copy of .env, and it lands on a third party drive.
            expect(config('backup.backup.encryption'))->not->toBe('none')
                ->and(config('backup.backup.encryption'))->not->toBeNull();
        });

        it('should take the password from the environment', function (): void {
            expect(config('backup.backup.password'))->toBe(env('BACKUP_ARCHIVE_PASSWORD'));
        });
    });

    describe('notifications', function (): void {
        it('should build without an address configured', function (): void {
            // Spatie validates every recipient while building its config object,
            // so an address left in `mail.to` would throw here and take
            // `backup:run` down before it backs anything up.
            config()->set('monitoring.developer_email', null);

            expect(fn (): Config => app(Config::class))->not->toThrow(Exception::class);
        });

        it('should resolve the recipients through the developer notifiable', function (): void {
            expect(config('backup.notifications.notifiable'))->toBe(DeveloperNotifiable::class);
        });

        it('should mail every failure', function (): void {
            expect(config('backup.notifications.notifications'))
                ->toHaveKey(BackupHasFailedNotification::class, ['mail'])
                ->toHaveKey(UnhealthyBackupWasFoundNotification::class, ['mail'])
                ->toHaveKey(CleanupHasFailedNotification::class, ['mail']);
        });

        it('should stay quiet on success', function (): void {
            // A daily "backup successful" is a mail nobody reads after a week,
            // and the inbox rule that swallows it swallows the failure too.
            expect(config('backup.notifications.notifications'))
                ->toHaveKey(BackupWasSuccessfulNotification::class, [])
                ->toHaveKey(HealthyBackupWasFoundNotification::class, [])
                ->toHaveKey(CleanupWasSuccessfulNotification::class, []);
        });
    });
});
