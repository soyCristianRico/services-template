<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\NotFoundLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotFoundLog>
 */
class NotFoundLogFactory extends Factory
{
    protected $model = NotFoundLog::class;

    public function definition(): array
    {
        return [
            'path' => '/'.fake()->unique()->slug(),
            'hits' => fake()->numberBetween(1, 200),
            'first_seen_at' => now()->subDays(10),
            'last_seen_at' => now(),
            'last_referrer' => null,
            'resolved_at' => null,
        ];
    }

    public function resolved(): self
    {
        return $this->state(['resolved_at' => now()]);
    }
}
