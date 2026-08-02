<?php

declare(strict_types=1);

use App\Enums\RedirectMatchType;
use App\Enums\RedirectStatusCode;
use App\Models\NotFoundLog;
use App\Models\Redirect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

describe('Admin\\Redirects\\Edit', function (): void {
    describe('mount', function (): void {
        it('should arrive pre-filled from the broken-address screen', function (): void {
            Livewire::withQueryParams(['source' => 'https://example.test/Curso-Antiguo/'])
                ->test('pages::admin.redirects.edit')
                ->assertSet('form.source', '/curso-antiguo')
                ->assertSet('testPath', '/curso-antiguo');
        });

        it('should open an existing redirect ready to edit', function (): void {
            $redirect = Redirect::factory()->create(['source' => '/vieja', 'destination' => '/nueva']);

            Livewire::test('pages::admin.redirects.edit', ['redirect' => $redirect])
                ->assertSet('form.source', '/vieja')
                ->assertSet('form.destination', '/nueva');
        });
    });

    describe('save', function (): void {
        it('should create a redirect that works from that moment on', function (): void {
            Livewire::test('pages::admin.redirects.edit')
                ->set('form.source', '/curso-antiguo')
                ->set('form.destination', '/blog')
                ->call('save')
                ->assertHasNoErrors();

            $this->get('/curso-antiguo')->assertRedirect(url('/blog'));
        });

        it('should tick off the broken address it came from', function (): void {
            $log = NotFoundLog::factory()->create(['path' => '/curso-antiguo']);

            Livewire::test('pages::admin.redirects.edit')
                ->set('form.source', '/curso-antiguo')
                ->set('form.destination', '/blog')
                ->call('save');

            expect($log->fresh()->resolved_at)->not->toBeNull();
        });
    });

    describe('testResult', function (): void {
        it('should say where an address would end up', function (): void {
            Livewire::test('pages::admin.redirects.edit')
                ->set('form.source', '/curso-antiguo')
                ->set('form.destination', '/blog')
                ->set('testPath', '/curso-antiguo/')
                ->assertSee('Coincide')
                ->assertSee('/blog');
        });

        it('should say plainly when the rule would not catch the address', function (): void {
            Livewire::test('pages::admin.redirects.edit')
                ->set('form.source', '/curso-antiguo')
                ->set('form.destination', '/blog')
                ->set('testPath', '/otra-cosa')
                ->assertSee('No coincide');
        });

        it('should resolve a pattern against the address before saving anything', function (): void {
            Livewire::test('pages::admin.redirects.edit')
                ->set('form.match_type', RedirectMatchType::Regex->value)
                ->set('form.source', '^/blog/\d{4}/(.+)$')
                ->set('form.destination', '/blog/$1')
                ->set('testPath', '/blog/2019/como-estudiar')
                ->assertSee('/blog/como-estudiar');
        });

        it('should announce a retired page instead of a destination', function (): void {
            Livewire::test('pages::admin.redirects.edit')
                ->set('form.source', '/retirada')
                ->set('form.status_code', RedirectStatusCode::Gone->value)
                ->set('testPath', '/retirada')
                ->assertSee('contenido eliminado');
        });
    });
});
