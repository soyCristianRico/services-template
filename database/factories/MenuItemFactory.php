<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MenuItemType;
use App\Enums\MenuLocation;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    protected $model = MenuItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'location' => MenuLocation::Header,
            'type' => MenuItemType::Link,
            'label' => fake()->unique()->words(2, true),
            'url' => '/'.fake()->slug(),
            'position' => 0,
            'is_active' => true,
        ];
    }

    /**
     * A submenu entry lives in the same menu as the item it hangs from.
     */
    public function childOf(MenuItem $parent): self
    {
        return $this->state([
            'parent_id' => $parent->id,
            'location' => $parent->location,
        ]);
    }

    public function in(MenuLocation $location): self
    {
        return $this->state(['location' => $location]);
    }
}
