<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Every screen of the admin, in sidebar order. The sidebar, the browser tab
 * title and the "where am I" highlight all read from here, so a section is
 * renamed, regrouped or moved in one place and not in the layout.
 *
 * A section with no group sits at the top level; those go first, so a loose
 * link never reads as part of the group above it. Which sections a site shows
 * is decided by `AdminNavigation`, from the site switches.
 */
enum AdminSection: string
{
    case Dashboard = 'dashboard';
    case Leads = 'leads';
    case Blog = 'blog';
    case Services = 'services';
    case Categories = 'categories';
    case Pages = 'pages';
    case Menus = 'menus';
    case Locations = 'locations';
    case Landings = 'landings';
    case Redirects = 'redirects';
    case NotFound = 'not-found';

    /**
     * Not in the sidebar: the profile lives in the user menu. A screen that is
     * a tab of another one (the categories of an entity, say) goes here too,
     * and `sidebarEntry()` says which entry lights up for it.
     */
    case Profile = 'profile';

    public function label(): string
    {
        return match ($this) {
            self::Dashboard => 'Resumen',
            self::Services => 'Servicios',
            self::Categories => 'Categorías',
            self::Pages => 'Páginas',
            self::Menus => 'Menús',
            self::Blog => 'Blog',
            self::Leads => 'Contactos',
            self::Locations => 'Ubicaciones',
            self::Landings => 'Landings',
            self::Redirects => 'Redirecciones',
            self::NotFound => 'Direcciones rotas',
            self::Profile => 'Perfil',
        };
    }

    public function group(): ?AdminGroup
    {
        return match ($this) {
            self::Services, self::Categories => AdminGroup::Catalog,
            self::Pages, self::Menus => AdminGroup::Web,
            self::Locations, self::Landings, self::Redirects, self::NotFound => AdminGroup::Seo,
            default => null,
        };
    }

    /**
     * Only top-level entries carry an icon; inside a group the heading is enough.
     */
    public function icon(): ?string
    {
        return match ($this) {
            self::Dashboard => 'home',
            self::Blog => 'newspaper',
            self::Leads => 'inbox',
            default => null,
        };
    }

    public function inSidebar(): bool
    {
        return $this !== self::Profile;
    }

    /**
     * The route the sidebar entry opens.
     */
    public function routeName(): string
    {
        return match ($this) {
            self::Dashboard => 'admin.dashboard',
            self::Profile => 'admin.profile',
            self::NotFound => 'admin.redirects.not-found',
            default => "admin.{$this->value}.index",
        };
    }

    /**
     * The sidebar entry that lights up while this section is open. A screen
     * that is a tab of another maps to that one's entry here.
     */
    public function sidebarEntry(): self
    {
        return $this;
    }

    /**
     * The routes that belong to the section: its list, its record and its
     * create screen.
     *
     * @return list<string>
     */
    public function routePatterns(): array
    {
        return match ($this) {
            self::Dashboard => ['admin.dashboard'],
            self::Profile => ['admin.profile'],
            self::Redirects => ['admin.redirects.index', 'admin.redirects.create', 'admin.redirects.edit', 'admin.redirects.import'],
            self::NotFound => ['admin.redirects.not-found'],
            default => ["admin.{$this->value}.*"],
        };
    }
}
