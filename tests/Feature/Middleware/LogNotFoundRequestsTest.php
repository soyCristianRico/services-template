<?php

declare(strict_types=1);

use App\Models\NotFoundLog;
use App\Models\Redirect;
use App\Models\User;
use Livewire\Livewire;

describe('LogNotFoundRequests', function (): void {
    describe('handle', function (): void {
        it('should record an address that answered with an error', function (): void {
            $this->get('/pagina-que-no-existe')->assertNotFound();

            expect(NotFoundLog::query()->where('path', '/pagina-que-no-existe')->exists())->toBeTrue();
        });

        it('should keep one row per address and count the visits on it', function (): void {
            $this->get('/pagina-que-no-existe');
            $this->get('/pagina-que-no-existe/');
            $this->get('/Pagina-Que-No-Existe');

            $log = NotFoundLog::query()->where('path', '/pagina-que-no-existe')->first();

            expect(NotFoundLog::query()->count())->toBe(1)
                ->and($log->hits)->toBe(3);
        });

        it('should remember where the visit came from', function (): void {
            $this->get('/pagina-que-no-existe', ['referer' => 'https://google.es/search?q=x']);

            expect(NotFoundLog::query()->first()->last_referrer)->toBe('https://google.es/search?q=x');
        });

        it('should not record an address that answered fine', function (): void {
            $this->get('/blog')->assertOk();

            expect(NotFoundLog::query()->count())->toBe(0);
        });

        it('should not record an address that was redirected', function (): void {
            Redirect::factory()->create(['source' => '/curso-antiguo', 'destination' => '/blog']);

            $this->get('/curso-antiguo');

            expect(NotFoundLog::query()->count())->toBe(0);
        });

        it('should not record the admin', function (): void {
            $this->actingAs(User::factory()->create())->get('/admin/no-existe');

            expect(NotFoundLog::query()->count())->toBe(0);
        });

        it('should not record the noise bots generate', function (string $path): void {
            $this->get($path);

            expect(NotFoundLog::query()->count())->toBe(0);
        })->with([
            'wordpress probe' => '/wp-login.php',
            'wordpress folder' => '/wp-content/uploads/x',
            'environment file' => '/.env',
            'missing asset' => '/img/logo-viejo.png',
            'missing stylesheet' => '/css/app.css',
            'mail server probe' => '/owa/auth/logon.aspx',
        ]);

        it('should stay quiet when the log is switched off', function (): void {
            config()->set('redirects.log_not_found', false);

            $this->get('/pagina-que-no-existe');

            expect(NotFoundLog::query()->count())->toBe(0);
        });
    });

    describe('resolution', function (): void {
        it('should mark the address as resolved once a redirect covers it', function (): void {
            $this->get('/pagina-que-no-existe');

            Livewire::actingAs(User::factory()->create())
                ->test('pages::admin.redirects.edit')
                ->set('form.source', '/pagina-que-no-existe')
                ->set('form.destination', '/blog')
                ->call('save');

            expect(NotFoundLog::query()->first()->resolved_at)->not->toBeNull();
        });
    });
});
