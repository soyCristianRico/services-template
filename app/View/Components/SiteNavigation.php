<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Enums\MenuLocation;
use App\Models\MenuItem;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

/**
 * The navigation of one place in the site, read from the database.
 *
 * Until this existed each site wrote its links by hand in the layout, which
 * meant a menu could only be changed by someone who could deploy. Now they are
 * rows: the admin edits them, and so does Ruk Lab through the connector.
 */
class SiteNavigation extends Component
{
    public function __construct(
        public MenuLocation $location = MenuLocation::Header,
    ) {}

    /**
     * Top-level entries, with their children already loaded.
     *
     * One query for the roots and one for the children, whatever the depth of
     * the menu. An inactive parent takes its children with it — hiding a
     * section should hide what hangs from it.
     *
     * @return Collection<int, MenuItem>
     */
    public function items(): Collection
    {
        return MenuItem::query()
            ->active()
            ->in($this->location)
            ->roots()
            ->with(['children' => fn ($query) => $query->where('is_active', true)])
            ->get();
    }

    public function render(): View
    {
        return view('components.site.navigation', ['items' => $this->items()]);
    }
}
