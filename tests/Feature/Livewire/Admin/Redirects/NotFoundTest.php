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

    describe('dismiss', function (): void {
        it('should stop an address from nagging without redirecting it', function (): void {
            $log = NotFoundLog::factory()->create();

            Livewire::test('pages::admin.redirects.not-found')->call('dismiss', $log->id);

            expect($log->fresh()->resolved_at)->not->toBeNull();
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
