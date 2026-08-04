<?php

declare(strict_types=1);

namespace App\Notifications\Monitoring;

use Spatie\Backup\Notifications\Notifiable;

/**
 * Routes the backup notifications to the developer address of the site.
 *
 * Resolved here rather than in `backup.notifications.mail.to` because spatie
 * validates that value at boot: an empty or comma separated address throws and
 * takes `backup:run` with it.
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
