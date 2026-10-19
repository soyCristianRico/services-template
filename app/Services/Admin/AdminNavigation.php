<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\AdminGroup;
use App\Enums\AdminSection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * The admin sidebar of THIS site: the sections of `AdminSection` the site
 * actually has, grouped. A module the site does not use (no catalog, no
 * geography) leaves the sidebar instead of sitting there empty.
 */
class AdminNavigation
{
    public function __construct(protected Request $request) {}

    /**
     * The sidebar, in order. A group shows up where its first section is; a
     * group left with a single section shows that section on its own, since a
     * heading over one link only adds a line to read.
     *
     * @return list<array{group: ?AdminGroup, sections: list<AdminSection>}>
     */
    public function entries(): array
    {
        $entries = [];
        $slots = [];

        foreach ($this->sections() as $section) {
            $group = $section->group();

            if ($group === null) {
                $entries[] = ['group' => null, 'sections' => [$section]];

                continue;
            }

            if (! array_key_exists($group->value, $slots)) {
                $slots[$group->value] = count($entries);
                $entries[] = ['group' => $group, 'sections' => []];
            }

            $entries[$slots[$group->value]]['sections'][] = $section;
        }

        return array_map(
            fn (array $entry): array => count($entry['sections']) === 1 ? ['group' => null, 'sections' => $entry['sections']] : $entry,
            $entries,
        );
    }

    /**
     * The sidebar sections this site has.
     *
     * @return list<AdminSection>
     */
    public function sections(): array
    {
        return array_values(array_filter(
            AdminSection::cases(),
            fn (AdminSection $section): bool => $section->inSidebar() && $this->isEnabled($section),
        ));
    }

    public function isEnabled(AdminSection $section): bool
    {
        $enabled = match ($section) {
            AdminSection::Services, AdminSection::Categories => (bool) config('site.catalog'),
            AdminSection::Locations, AdminSection::Landings => (bool) config('site.locations'),
            default => true,
        };

        return $enabled && Route::has($section->routeName());
    }

    /**
     * The section the current screen belongs to, if any.
     */
    public function current(): ?AdminSection
    {
        foreach (AdminSection::cases() as $section) {
            if ($this->request->routeIs(...$section->routePatterns())) {
                return $section;
            }
        }

        return null;
    }

    /**
     * Whether a sidebar entry stands for the current screen.
     */
    public function isCurrent(AdminSection $section): bool
    {
        return $this->current()?->sidebarEntry() === $section;
    }
}
