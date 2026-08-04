<?php

declare(strict_types=1);

use App\Notifications\Monitoring\DeveloperNotifiable;

/**
 * Where the technical alerts of the site end up. Spatie validates
 * `backup.notifications.mail.to` while building its config object, so an empty
 * or comma separated address there would throw and take `backup:run` down with
 * it — the recipients are resolved here instead, at send time.
 */
describe('DeveloperNotifiable', function (): void {
    describe('routeNotificationForMail', function (): void {
        it('should route to the configured address', function (): void {
            config()->set('monitoring.developer_email', 'dev@agencia.com');

            expect((new DeveloperNotifiable)->routeNotificationForMail())
                ->toBe(['dev@agencia.com']);
        });

        it('should route to every address of a comma separated list', function (): void {
            config()->set('monitoring.developer_email', 'dev@agencia.com,guardia@agencia.com');

            expect((new DeveloperNotifiable)->routeNotificationForMail())
                ->toBe(['dev@agencia.com', 'guardia@agencia.com']);
        });

        it('should trim the spaces around each address', function (): void {
            config()->set('monitoring.developer_email', ' dev@agencia.com , guardia@agencia.com ');

            expect((new DeveloperNotifiable)->routeNotificationForMail())
                ->toBe(['dev@agencia.com', 'guardia@agencia.com']);
        });

        it('should drop the empty slots of a trailing comma', function (): void {
            config()->set('monitoring.developer_email', 'dev@agencia.com,,');

            expect((new DeveloperNotifiable)->routeNotificationForMail())
                ->toBe(['dev@agencia.com']);
        });

        it('should route nowhere when no address is configured', function (): void {
            config()->set('monitoring.developer_email', null);

            expect((new DeveloperNotifiable)->routeNotificationForMail())->toBe([]);
        });

        it('should treat a blank address as none configured', function (): void {
            config()->set('monitoring.developer_email', '   ');

            expect((new DeveloperNotifiable)->routeNotificationForMail())->toBe([]);
        });
    });
});
