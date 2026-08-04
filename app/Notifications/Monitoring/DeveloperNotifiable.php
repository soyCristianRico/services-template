<?php

declare(strict_types=1);

namespace App\Notifications\Monitoring;

use Spatie\Backup\Notifications\Notifiable;

/**
 * Routes the backup notifications to the developer address of the site.
 *
 * Spatie resolves recipients from `backup.notifications.mail.to`, but it also
 * validates that value when it builds its config object: an empty string or a
 * comma separated list throws InvalidConfig and takes the whole `backup:run`
 * down with it. Since the address is optional in local development and may hold
 * several recipients in production, it is resolved here instead — at send time,
 * where a missing address simply means nobody is notified.
 */
class DeveloperNotifiable extends Notifiable
{
    /** @return array<int, string> */
    public function routeNotificationForMail(): array
    {
        $configured = config('monitoring.developer_email');

        if (blank($configured)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $configured))));
    }
}
