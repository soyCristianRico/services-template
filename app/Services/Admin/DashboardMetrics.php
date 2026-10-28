<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Lead;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The series behind the admin home charts. A site built on the template adds
 * its own series here (registered or paying businesses, for example). Dates are bucketed in PHP rather
 * than with SQL date functions, so the same code runs on SQLite (local, tests)
 * and MySQL (production). The volumes are small enough for that.
 */
class DashboardMetrics
{
    /**
     * Requests received per week, Monday to Sunday, oldest first.
     *
     * @return list<array{week: string, leads: int}>
     */
    public function leadsPerWeek(int $weeks = 12): array
    {
        $firstWeek = CarbonImmutable::now()->startOfWeek()->subWeeks($weeks - 1);

        $counts = Lead::query()
            ->where('created_at', '>=', $firstWeek)
            ->pluck('created_at')
            ->countBy(fn ($createdAt): string => CarbonImmutable::parse($createdAt)->startOfWeek()->toDateString());

        return $this->periods($firstWeek, $weeks, 'week')
            ->map(fn (CarbonImmutable $week): array => [
                'week' => $week->format('d/m'),
                'leads' => $counts->get($week->toDateString(), 0),
            ])
            ->all();
    }

    /**
     * @return Collection<int, CarbonImmutable>
     */
    protected function periods(CarbonImmutable $first, int $count, string $unit): Collection
    {
        return collect(range(0, $count - 1))->map(fn (int $offset): CarbonImmutable => $first->add($unit, $offset));
    }
}
