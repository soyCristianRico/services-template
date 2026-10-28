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

        it('should filter by status code', function (): void {
            Redirect::factory()->create(['source' => '/permanente', 'status_code' => 301]);
            Redirect::factory()->create(['source' => '/temporal', 'status_code' => 302]);

            Livewire::test('pages::admin.redirects.index')
                ->set('code', '302')
                ->assertSee('/temporal')
                ->assertDontSee('/permanente');
        });

        it('should show the outcome of an import as a toast', function (): void {
            session()->flash('status', 'Importadas: 2 nuevas.');

            Livewire::test('pages::admin.redirects.index')
                ->assertDispatched('toast-show');
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

    describe('sort', function (): void {
        it('should put the most used first by default', function (): void {
            Redirect::factory()->create(['source' => '/poco', 'hits' => 1]);
            Redirect::factory()->create(['source' => '/mucho', 'hits' => 900]);

            $sources = Livewire::test('pages::admin.redirects.index')
                ->get('redirects')->pluck('source')->all();

            expect($sources)->toBe(['/mucho', '/poco']);
        });

        it('should sort by the old address and flip the direction on a second click', function (): void {
            Redirect::factory()->create(['source' => '/b-vieja', 'hits' => 900]);
            Redirect::factory()->create(['source' => '/a-vieja', 'hits' => 1]);

            $component = Livewire::test('pages::admin.redirects.index')->call('sort', 'source');

            expect($component->get('redirects')->pluck('source')->all())->toBe(['/a-vieja', '/b-vieja']);

            $component->call('sort', 'source')->assertSet('sortDirection', 'desc');

            expect($component->get('redirects')->pluck('source')->all())->toBe(['/b-vieja', '/a-vieja']);
        });

        it('should ignore a column that cannot be sorted', function (): void {
            Livewire::test('pages::admin.redirects.index')
                ->call('sort', 'destination')
                ->assertSet('sortBy', '');
        });
    });

    describe('clearFilter', function (): void {
        it('should show each active filter as a chip and clear it', function (): void {
            Livewire::test('pages::admin.redirects.index')
                ->set('type', 'prefix')
                ->set('state', 'unused')
                ->assertSee('Por prefijo')
                ->assertSee('Sin usar nunca')
                ->call('clearFilter', 'type')
                ->assertSet('type', '')
                ->assertSet('state', 'unused')
                ->call('clearAll')
                ->assertSet('state', '');
        });
    });

    describe('toggleActive', function (): void {
        it('should switch a redirect off without deleting it', function (): void {
            $redirect = Redirect::factory()->create();

            Livewire::test('pages::admin.redirects.index')
                ->call('toggleActive', $redirect->id);

            expect($redirect->fresh()->is_active)->toBeFalse();
        });

        it('should confirm the change with a toast', function (): void {
            $redirect = Redirect::factory()->create();

            Livewire::test('pages::admin.redirects.index')
                ->call('toggleActive', $redirect->id)
                ->assertDispatched('toast-show');
        });

        it('should stop redirecting straight away', function (): void {
            $redirect = Redirect::factory()->create(['source' => '/vieja', 'destination' => '/blog']);

            $this->get('/vieja')->assertRedirect(url('/blog'));

            Livewire::test('pages::admin.redirects.index')->call('toggleActive', $redirect->id);

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
