<?php

declare(strict_types=1);

use App\Models\NotFoundLog;
use App\Models\Redirect;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

describe('Admin\\Redirects\\Index', function (): void {
    describe('listing', function (): void {
        it('should show the redirects', function (): void {
            Redirect::factory()->create(['source' => '/curso-antiguo', 'destination' => '/blog']);

            Livewire::test('pages::admin.redirects.index')
                ->assertSee('/curso-antiguo')
                ->assertSee('/blog');
        });

        it('should filter by what is typed in the search box', function (): void {
            Redirect::factory()->create(['source' => '/curso-antiguo']);
            Redirect::factory()->create(['source' => '/promo-verano']);

            Livewire::test('pages::admin.redirects.index')
                ->set('search', 'promo')
                ->assertSee('/promo-verano')
                ->assertDontSee('/curso-antiguo');
        });

        it('should filter by match type', function (): void {
            Redirect::factory()->create(['source' => '/exacta']);
            Redirect::factory()->prefix()->create(['source' => '/con-prefijo']);

            Livewire::test('pages::admin.redirects.index')
                ->set('type', 'prefix')
                ->assertSee('/con-prefijo')
                ->assertDontSee('/exacta');
        });

        it('should single out the ones that have never fired', function (): void {
            Redirect::factory()->create(['source' => '/sin-usar', 'hits' => 0]);
            Redirect::factory()->create(['source' => '/muy-usada', 'hits' => 500]);

            Livewire::test('pages::admin.redirects.index')
                ->set('state', 'unused')
                ->assertSee('/sin-usar')
                ->assertDontSee('/muy-usada');
        });

        it('should point out the broken addresses waiting for a decision', function (): void {
            NotFoundLog::factory()->count(3)->create();

            Livewire::test('pages::admin.redirects.index')
                ->assertSee('Hay 3');
        });

        it('should stay quiet when nothing is pending', function (): void {
            NotFoundLog::factory()->resolved()->create();

            Livewire::test('pages::admin.redirects.index')
                ->assertDontSee('direcciones visitadas que no existen');
        });
    });

    describe('toggleActive', function (): void {
        it('should switch a redirect off without deleting it', function (): void {
            $redirect = Redirect::factory()->create();

            Livewire::test('pages::admin.redirects.index')
                ->call('toggleActive', $redirect->id);

            expect($redirect->fresh()->is_active)->toBeFalse();
        });

        it('should stop redirecting straight away', function (): void {
            $redirect = Redirect::factory()->create(['source' => '/vieja', 'destination' => '/blog']);

            $this->get('/vieja')->assertRedirect(url('/blog'));

            Livewire::test('pages::admin.redirects.index')->call('toggleActive', $redirect->id);

            $this->get('/vieja')->assertNotFound();
        });
    });

    describe('deleteRedirect', function (): void {
        it('should remove the redirect and stop sending anyone', function (): void {
            $redirect = Redirect::factory()->create(['source' => '/vieja', 'destination' => '/blog']);

            Livewire::test('pages::admin.redirects.index')
                ->call('deleteRedirect', $redirect->id);

            expect(Redirect::query()->count())->toBe(0);

            $this->get('/vieja')->assertNotFound();
        });
    });

    describe('access', function (): void {
        it('should be closed to anyone not signed in', function (): void {
            auth()->logout();

            $this->get('/admin/redirects')->assertRedirect(route('login'));
        });
    });
});
