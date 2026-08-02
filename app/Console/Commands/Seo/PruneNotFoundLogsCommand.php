<?php

declare(strict_types=1);

namespace App\Console\Commands\Seo;

use App\Models\NotFoundLog;
use Illuminate\Console\Command;

class PruneNotFoundLogsCommand extends Command
{
    protected $signature = 'redirects:prune';

    protected $description = 'Delete logged 404s that have not been seen again in a long time';

    public function handle(): int
    {
        $days = (int) config('redirects.not_found_retention_days', 90);

        // Dropped by last sighting, not by age: an address that keeps being hit
        // three years on is a live broken link, and it is exactly the one worth
        // keeping on the list.
        $deleted = NotFoundLog::query()
            ->where('last_seen_at', '<', now()->subDays($days))
            ->delete();

        $this->info("Pruned {$deleted} logged 404(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
