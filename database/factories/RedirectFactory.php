<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RedirectMatchType;
use App\Enums\RedirectStatusCode;
use App\Models\Redirect;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Redirect>
 */
class RedirectFactory extends Factory
{
    protected $model = Redirect::class;

    public function definition(): array
    {
        return [
            'source' => '/'.fake()->unique()->slug(),
            'destination' => '/'.fake()->slug(),
            'match_type' => RedirectMatchType::Exact->value,
            'status_code' => RedirectStatusCode::MovedPermanently->value,
            'is_active' => true,
            'preserve_query' => true,
            'notes' => null,
            'hits' => 0,
            'last_hit_at' => null,
        ];
    }

    public function prefix(): self
    {
        return $this->state(['match_type' => RedirectMatchType::Prefix->value]);
    }

    public function regex(): self
    {
        return $this->state(['match_type' => RedirectMatchType::Regex->value]);
    }

    public function gone(): self
    {
        return $this->state([
            'status_code' => RedirectStatusCode::Gone->value,
            'destination' => null,
        ]);
    }

    public function inactive(): self
    {
        return $this->state(['is_active' => false]);
    }
}
