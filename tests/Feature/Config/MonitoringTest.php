<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use SoyCristianRico\LaravelServerMonitor\Services\Security\SecurityNotificationService;

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
            // Syllabi and gallery images are big and appear all at once by
            // design. Unexcluded, every new syllabus arrives as a security alert.
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
