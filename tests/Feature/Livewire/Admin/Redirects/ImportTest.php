<?php

declare(strict_types=1);

use App\Models\Redirect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

describe('Admin\\Redirects\\Import', function (): void {
    describe('review', function (): void {
        it('should say what each line would do before touching anything', function (): void {
            Livewire::test('pages::admin.redirects.import')
                ->set('raw', "/vieja;/nueva\n/rota")
                ->call('review')
                ->assertSee('Se creará')
                ->assertSee('Se descartará');

            expect(Redirect::query()->count())->toBe(0);
        });

        it('should warn that an existing redirect would be left alone', function (): void {
            Redirect::factory()->create(['source' => '/vieja']);

            Livewire::test('pages::admin.redirects.import')
                ->set('raw', '/vieja;/nueva')
                ->call('review')
                ->assertSee('Se dejará como está');
        });

        it('should say it would replace once overwriting is switched on', function (): void {
            Redirect::factory()->create(['source' => '/vieja']);

            Livewire::test('pages::admin.redirects.import')
                ->set('raw', '/vieja;/nueva')
                ->call('review')
                ->set('overwrite', true)
                ->assertSee('Se reemplazará');
        });
    });

    describe('import', function (): void {
        it('should create the redirects and leave them working', function (): void {
            Livewire::test('pages::admin.redirects.import')
                ->set('raw', "/vieja;/blog\n/otra;/legal")
                ->call('review')
                ->call('import');

            expect(Redirect::query()->count())->toBe(2);

            $this->get('/vieja')->assertRedirect(url('/blog'));
        });

        it('should report what it did', function (): void {
            Livewire::test('pages::admin.redirects.import')
                ->set('raw', "/vieja;/nueva\n/rota")
                ->call('review')
                ->call('import');

            expect(session('status'))->toContain('1 nuevas')
                ->and(session('status'))->toContain('1 descartadas');
        });

        it('should send the person back to the list when it is done', function (): void {
            Livewire::test('pages::admin.redirects.import')
                ->set('raw', '/vieja;/nueva')
                ->call('review')
                ->call('import')
                ->assertRedirect(route('admin.redirects.index'));
        });
    });
});
