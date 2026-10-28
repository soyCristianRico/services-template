<?php

declare(strict_types=1);

use App\Models\NotFoundLog;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

describe('Admin\\Redirects\\NotFound', function (): void {
    describe('listing', function (): void {
        it('should put the most visited broken address first', function (): void {
            NotFoundLog::factory()->create(['path' => '/poco-visitada', 'hits' => 2]);
            NotFoundLog::factory()->create(['path' => '/muy-visitada', 'hits' => 900]);

            $rendered = Livewire::test('pages::admin.redirects.not-found')->html();

            expect(strpos($rendered, '/muy-visitada'))->toBeLessThan(strpos($rendered, '/poco-visitada'));
        });

        it('should hide the ones already dealt with', function (): void {
            NotFoundLog::factory()->resolved()->create(['path' => '/ya-resuelta']);

            Livewire::test('pages::admin.redirects.not-found')
                ->assertDontSee('/ya-resuelta');
        });

        it('should show them when asked', function (): void {
            NotFoundLog::factory()->resolved()->create(['path' => '/ya-resuelta']);

            Livewire::test('pages::admin.redirects.not-found')
                ->set('showResolved', true)
                ->assertSee('/ya-resuelta');
        });

        it('should filter by what is typed in the search box', function (): void {
            NotFoundLog::factory()->create(['path' => '/curso-antiguo']);
            NotFoundLog::factory()->create(['path' => '/promo-verano']);

            Livewire::test('pages::admin.redirects.not-found')
                ->set('search', 'promo')
                ->assertSee('/promo-verano')
                ->assertDontSee('/curso-antiguo');
        });
    });

    describe('sort', function (): void {
        it('should sort by the most recent visit when asked', function (): void {
            NotFoundLog::factory()->create(['path' => '/antigua', 'hits' => 900, 'last_seen_at' => now()->subMonth()]);
            NotFoundLog::factory()->create(['path' => '/reciente', 'hits' => 1, 'last_seen_at' => now()]);

            $paths = Livewire::test('pages::admin.redirects.not-found')
                ->call('sort', 'last_seen_at')
                ->call('sort', 'last_seen_at')
                ->assertSet('sortDirection', 'desc')
                ->get('logs')->pluck('path')->all();

            expect($paths)->toBe(['/reciente', '/antigua']);
        });

        it('should ignore a column that cannot be sorted', function (): void {
            Livewire::test('pages::admin.redirects.not-found')
                ->call('sort', 'last_referrer')
                ->assertSet('sortBy', '');
        });
    });

    describe('clearFilter', function (): void {
        it('should show the resolved ones as a chip and clear it', function (): void {
            Livewire::test('pages::admin.redirects.not-found')
                ->set('showResolved', true)
                ->assertSee('Incluye las resueltas')
                ->call('clearFilter', 'showResolved')
                ->assertSet('showResolved', false);
        });
    });

    describe('dismiss', function (): void {
        it('should stop an address from nagging without redirecting it', function (): void {
            $log = NotFoundLog::factory()->create();

            Livewire::test('pages::admin.redirects.not-found')->call('dismiss', $log->id);

            expect($log->fresh()->resolved_at)->not->toBeNull();
        });

        it('should confirm it with a toast', function (): void {
            $log = NotFoundLog::factory()->create();

            Livewire::test('pages::admin.redirects.not-found')
                ->call('dismiss', $log->id)
                ->assertDispatched('toast-show');
        });
    });

    describe('restore', function (): void {
        it('should put a dismissed address back on the list', function (): void {
            $log = NotFoundLog::factory()->resolved()->create();

            Livewire::test('pages::admin.redirects.not-found')->call('restore', $log->id);

            expect($log->fresh()->resolved_at)->toBeNull();
        });
    });
});
