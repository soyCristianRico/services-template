<?php

declare(strict_types=1);

use App\Enums\AdminGroup;
use App\Enums\AdminSection;
use App\Services\Admin\AdminNavigation;
use Illuminate\Http\Request;

/**
 * The navigation as a request to the given URL sees it.
 */
function navigationAt(string $path = '/admin'): AdminNavigation
{
    $request = Request::create($path);
    $request->setRouteResolver(fn () => rescue(fn () => app('router')->getRoutes()->match($request), report: false));

    return new AdminNavigation($request);
}

describe('AdminNavigation', function () {
    describe('sections', function () {
        it('should list the catalog when the site has one', function () {
            expect(navigationAt()->sections())->toContain(AdminSection::Services, AdminSection::Categories);
        });

        it('should leave the catalog out when the site has none', function () {
            config()->set('site.catalog', false);

            expect(navigationAt()->sections())
                ->not->toContain(AdminSection::Services)
                ->not->toContain(AdminSection::Categories);
        });

        it('should leave locations and landings out on a site with no geography', function () {
            config()->set('site.locations', false);

            expect(navigationAt()->sections())
                ->not->toContain(AdminSection::Locations)
                ->not->toContain(AdminSection::Landings);
        });

        it('should never list the profile', function () {
            expect(navigationAt()->sections())->not->toContain(AdminSection::Profile);
        });
    });

    describe('entries', function () {
        it('should open with the loose entries and follow with the groups', function () {
            $entries = navigationAt()->entries();

            expect(array_map(fn (array $entry): ?AdminGroup => $entry['group'], $entries))
                ->toBe([null, null, null, AdminGroup::Catalog, AdminGroup::Web, AdminGroup::Seo])
                ->and($entries[3]['sections'])->toBe([AdminSection::Services, AdminSection::Categories])
                ->and($entries[4]['sections'])->toBe([AdminSection::Pages, AdminSection::Menus])
                ->and($entries[5]['sections'])->toBe([AdminSection::Locations, AdminSection::Landings, AdminSection::Redirects, AdminSection::NotFound]);
        });

        it('should show a group left with one section as that section alone', function () {
            config()->set('site.catalog', false);
            config()->set('site.locations', false);

            $navigation = new class(Request::create('/admin')) extends AdminNavigation
            {
                public function isEnabled(AdminSection $section): bool
                {
                    return $section !== AdminSection::NotFound && parent::isEnabled($section);
                }
            };

            $redirects = array_values(array_filter(
                $navigation->entries(),
                fn (array $entry): bool => $entry['sections'] === [AdminSection::Redirects],
            ));

            expect($redirects)->toHaveCount(1)
                ->and($redirects[0]['group'])->toBeNull();
        });
    });

    describe('current', function () {
        it('should find the section of the screen', function (string $path, AdminSection $expected) {
            expect(navigationAt($path)->current())->toBe($expected);
        })->with([
            ['/admin', AdminSection::Dashboard],
            ['/admin/services/create', AdminSection::Services],
            ['/admin/redirects/404', AdminSection::NotFound],
            ['/admin/redirects/import', AdminSection::Redirects],
        ]);

        it('should be null outside the admin sections', function () {
            expect(navigationAt('/precios')->current())->toBeNull();
        });
    });

    describe('isCurrent', function () {
        it('should light up only the entry of the current screen', function () {
            $navigation = navigationAt('/admin/services/create');

            expect($navigation->isCurrent(AdminSection::Services))->toBeTrue()
                ->and($navigation->isCurrent(AdminSection::Categories))->toBeFalse();
        });
    });
});
