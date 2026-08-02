<?php

declare(strict_types=1);

use App\Enums\RedirectStatusCode;
use App\Models\Redirect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('RedirectRequests', function (): void {
    describe('handle', function (): void {
        it('should send a visitor from the old address to the new one', function (): void {
            Redirect::factory()->create(['source' => '/curso-antiguo', 'destination' => '/blog']);

            $this->get('/curso-antiguo')
                ->assertRedirect(url('/blog'))
                ->assertStatus(301);
        });

        it('should redirect an address that still has a live route', function (): void {
            // The reason this middleware runs before routing instead of hanging off
            // the 404 handler: an address that still resolves — a named route, the
            // `/{slug}` catch-all — never reaches a 404 to be caught.
            Redirect::factory()->create(['source' => '/legal', 'destination' => '/blog']);

            $this->get('/legal')->assertRedirect(url('/blog'));
        });

        it('should answer a retired page with 410 instead of sending anywhere', function (): void {
            Redirect::factory()->gone()->create(['source' => '/seccion-retirada']);

            $this->get('/seccion-retirada')->assertStatus(410);
        });

        it('should carry the campaign parameters across', function (): void {
            Redirect::factory()->create(['source' => '/promo', 'destination' => '/blog']);

            $this->get('/promo?utm_source=mail')
                ->assertRedirect(url('/blog').'?utm_source=mail');
        });

        it('should leave the admin alone even when a rule points at it', function (): void {
            // Written straight to the table: the form rejects this, but a bad
            // import must not be able to lock the client out of the panel.
            Redirect::factory()->create(['source' => '/admin/redirects', 'destination' => '/']);

            $this->actingAs(User::factory()->create())
                ->get('/admin/redirects')
                ->assertOk();
        });

        it('should not redirect a form submission', function (): void {
            Redirect::factory()->create(['source' => '/legal', 'destination' => '/blog']);

            // A redirected POST loses its body, so the middleware ignores it and
            // the route answers as it always did (405 here: there is no POST route).
            $this->post('/legal')->assertStatus(405);
        });

        it('should ignore a deactivated redirect', function (): void {
            Redirect::factory()->inactive()->create(['source' => '/curso-antiguo', 'destination' => '/blog']);

            $this->get('/curso-antiguo')->assertNotFound();
        });

        it('should use the code the redirect was given', function (): void {
            Redirect::factory()->create([
                'source' => '/promo',
                'destination' => '/blog',
                'status_code' => RedirectStatusCode::Found->value,
            ]);

            $this->get('/promo')->assertStatus(302);
        });
    });

    describe('hit counting', function (): void {
        it('should count the visit and remember when it happened', function (): void {
            $redirect = Redirect::factory()->create(['source' => '/curso-antiguo', 'destination' => '/blog']);

            $this->get('/curso-antiguo');

            expect($redirect->fresh()->hits)->toBe(1)
                ->and($redirect->fresh()->last_hit_at)->not->toBeNull();
        });

        it('should not touch the edited-at stamp while counting', function (): void {
            // Counting through the model would fire `saved`, flush the redirect
            // cache on every single visit and make the cache pointless.
            $redirect = Redirect::factory()->create([
                'source' => '/curso-antiguo',
                'destination' => '/blog',
                'updated_at' => now()->subYear(),
            ]);

            $before = $redirect->updated_at;

            $this->get('/curso-antiguo');

            expect($redirect->fresh()->updated_at->timestamp)->toBe($before->timestamp);
        });
    });
});
