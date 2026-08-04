<?php

declare(strict_types=1);

describe('AuthLayout', function (): void {
    beforeEach(function (): void {
        $this->withoutVite();
    });

    describe('favicon', function (): void {
        /**
         * Same gap the admin had: with no `<link rel="icon">` the browser falls
         * back to `/favicon.ico`, which is the 0-byte placeholder the Laravel
         * skeleton ships, and the login tab paints blank. The sign-in screen is
         * the first thing anyone sees of the panel.
         *
         * Rendered straight through the view, like the admin layout test: no
         * HTTP, no session and no database behind it.
         */
        it('should link the same icon as the public site', function (): void {
            $html = (string) view('layouts.auth')->render();

            expect($html)->toContain('href="'.asset('favicon.png').'"');
        });
    });
});
