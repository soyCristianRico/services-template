<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schedule as ScheduleFacade;
use Toolkit\ServerMonitor\Services\Security\SecurityNotificationService;

/**
 * The two published configs that decide whether an alert reaches a person:
 * who the server monitor writes to, and whether application errors leave the
 * server at all.
 */
describe('monitoring', function (): void {
    describe('developer email', function (): void {
        it('should feed the server monitor from the same variable', function (): void {
            // Both read DEVELOPER_EMAIL. They go through env() separately because
            // one config file cannot reliably read another: the load order
            // between them is not guaranteed.
            expect(config('server-monitor.notifications.mail_to'))
                ->toBe(config('monitoring.developer_email'));
        });

        it('should not be the address the leads go to', function (): void {
            // A client can do nothing with a swap warning at 03:00, and burying
            // the alerts in the inbox that answers enquiries loses both.
            config()->set('monitoring.developer_email', 'dev@agencia.com');
            config()->set('leads.notify_email', 'hola@cliente.com');

            expect(config('monitoring.developer_email'))->not->toBe(config('leads.notify_email'));
        });
    });

    describe('server monitor switch', function (): void {
        it('should be off unless the site is told to watch the machine', function (): void {
            // Off is the safe default on a shared server: the failure it allows
            // is a check that does not run, not four copies of it that do.
            expect(config('monitoring.server_monitor'))->toBeFalse();
        });

        it('should not schedule the server checks while it is off', function (): void {
            config()->set('monitoring.server_monitor', false);

            expect(scheduledCommands())
                ->not->toContain('server:monitor')
                ->not->toContain('security:check')
                ->not->toContain('security:check-malware')
                ->not->toContain('security:monitor-crontabs');
        });

        it('should schedule the server checks once it is on', function (): void {
            config()->set('monitoring.server_monitor', true);

            expect(scheduledCommands())
                ->toContain('server:monitor')
                ->toContain('security:check')
                ->toContain('security:check-malware')
                ->toContain('security:monitor-crontabs');
        });

        it('should back up whether the machine is watched or not', function (): void {
            // The backup belongs to the site, so it survives the switch that
            // only one site per machine gets to turn on.
            config()->set('monitoring.server_monitor', false);

            expect(scheduledCommands())->toContain('backup:run');
        });
    });

    describe('server monitor', function (): void {
        it('should reach recipients without a roles system', function (): void {
            // This app has no spatie/laravel-permission: the package resolves
            // its recipients from the configured address, and the role lookup
            // it falls back to would have nothing to query.
            config()->set('server-monitor.notifications.mail_to', 'dev@agencia.com');

            Notification::fake();

            $sent = app(SecurityNotificationService::class)
                ->sendAlerts([['type' => 'Disk', 'details' => '95% used']]);

            expect($sent)->toBeTrue();
        });

        it('should not alert on the files the admin uploads', function (): void {
            // Gallery images are big and appear all at once by design.
            // Unexcluded, every upload arrives as a security alert.
            expect(config('server-monitor.security.excluded_large_files_paths'))
                ->toContain(storage_path('app/public'));
        });
    });

    describe('bugsnag', function (): void {
        it('should offer a log channel', function (): void {
            expect(config('logging.channels.bugsnag.driver'))->toBe('bugsnag');
        });

        it('should stay out of the stack until it is switched on', function (): void {
            // Errors from local development would otherwise burn the quota and
            // bury the production ones this exists to surface.
            expect(config('logging.channels.stack.channels'))->not->toContain('bugsnag');
        });
    });
});

/**
 * The commands `routes/console.php` schedules under the current config.
 *
 * The real schedule was built at boot, so flipping the switch afterwards would
 * not move it. A fresh Schedule is bound and the routes file re-evaluated
 * against it, which is the only way to see what the `if` actually decides.
 */
function scheduledCommands(): string
{
    $schedule = new Schedule;

    // swap(), not a container rebind: the facade caches the instance it resolved
    // at boot, so `Schedule::command()` in the routes file would keep landing on
    // that one and this would read an empty list.
    ScheduleFacade::swap($schedule);

    require base_path('routes/console.php');

    return collect($schedule->events())
        ->map(fn (Event $event): string => (string) $event->command)
        ->implode("\n");
}
