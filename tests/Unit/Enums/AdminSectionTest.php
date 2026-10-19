<?php

declare(strict_types=1);

use App\Enums\AdminGroup;
use App\Enums\AdminSection;

describe('AdminSection', function () {
    describe('label', function () {
        it('should name each screen the way the client says it', function (AdminSection $case, string $expected) {
            expect($case->label())->toBe($expected);
        })->with([
            [AdminSection::Dashboard, 'Resumen'],
            [AdminSection::Leads, 'Contactos'],
            [AdminSection::Blog, 'Blog'],
            [AdminSection::Services, 'Servicios'],
            [AdminSection::Categories, 'Categorías'],
            [AdminSection::Pages, 'Páginas'],
            [AdminSection::Menus, 'Menús'],
            [AdminSection::Locations, 'Ubicaciones'],
            [AdminSection::Landings, 'Landings'],
            [AdminSection::Redirects, 'Redirecciones'],
            [AdminSection::NotFound, 'Direcciones rotas'],
            [AdminSection::Profile, 'Perfil'],
        ]);
    });

    describe('cases', function () {
        // One per admin screen family; a new screen means a new case here, or
        // it never reaches the sidebar.
        it('should have one case per admin screen family', function () {
            expect(AdminSection::cases())->toHaveCount(12);
        });

        it('should put every loose entry before the first group', function () {
            $loose = array_map(
                fn (AdminSection $section): bool => $section->inSidebar() && $section->group() === null,
                AdminSection::cases(),
            );
            $firstGrouped = array_key_first(array_filter(AdminSection::cases(), fn (AdminSection $section): bool => $section->group() !== null));

            expect(array_slice($loose, $firstGrouped))->not->toContain(true);
        });
    });

    describe('group', function () {
        it('should group each screen by the work it is for', function (AdminSection $case, ?AdminGroup $expected) {
            expect($case->group())->toBe($expected);
        })->with([
            [AdminSection::Dashboard, null],
            [AdminSection::Leads, null],
            [AdminSection::Blog, null],
            [AdminSection::Services, AdminGroup::Catalog],
            [AdminSection::Pages, AdminGroup::Web],
            [AdminSection::Menus, AdminGroup::Web],
            [AdminSection::Redirects, AdminGroup::Seo],
            [AdminSection::NotFound, AdminGroup::Seo],
        ]);
    });

    describe('inSidebar', function () {
        it('should leave out the profile', function () {
            expect(AdminSection::Profile->inSidebar())->toBeFalse()
                ->and(AdminSection::Pages->inSidebar())->toBeTrue();
        });
    });

    describe('sidebarEntry', function () {
        it('should light up its own entry', function () {
            expect(AdminSection::Services->sidebarEntry())->toBe(AdminSection::Services);
        });
    });

    describe('routeName', function () {
        it('should open the list of the section', function (AdminSection $case, string $expected) {
            expect($case->routeName())->toBe($expected);
        })->with([
            [AdminSection::Dashboard, 'admin.dashboard'],
            [AdminSection::Services, 'admin.services.index'],
            [AdminSection::NotFound, 'admin.redirects.not-found'],
        ]);
    });

    describe('routePatterns', function () {
        it('should keep broken addresses apart from redirects', function () {
            expect(AdminSection::Redirects->routePatterns())->not->toContain('admin.redirects.*')
                ->and(AdminSection::Redirects->routePatterns())->not->toContain('admin.redirects.not-found')
                ->and(AdminSection::NotFound->routePatterns())->toBe(['admin.redirects.not-found']);
        });
    });
});
