<?php

declare(strict_types=1);


describe('Error pages', function () {
    describe('404', function () {
        beforeEach(function () {
            config()->set('services.google_tag_manager.id', 'GTM-TEST123');
        });

        it('should render the public 404 page for an unknown URL', function () {
            $this->get('/esta-url-no-existe')
                ->assertNotFound()
                ->assertSee('Esta página no existe');
        });

        /**
         * Unmatched multi-segment URLs are rejected by the router before the `web`
         * group runs, so ShareErrorsFromSession never shares `$errors` — and the
         * layout renders Flux fields (cookie banner) that read it.
         */
        it('should render the public 404 page when no route matches at all', function () {
            $this->get('/una/url/que/no/existe')
                ->assertNotFound()
                ->assertSee('Esta página no existe')
                ->assertSee('Usamos cookies');
        });
    });
});
