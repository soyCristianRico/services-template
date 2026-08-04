<?php

declare(strict_types=1);

use Bugsnag\Client;

/**
 * Cada pieza de la integración puede faltar por su cuenta sin que nada se queje,
 * así que lo que se prueba es que el comando las señale una a una.
 */
describe('CheckBugsnagCommand', function (): void {
    beforeEach(function (): void {
        config()->set('bugsnag.api_key', 'una-clave');
        config()->set('bugsnag.notify_release_stages', null);
        config()->set('logging.channels.bugsnag.driver', 'bugsnag');
        config()->set('logging.channels.stack.channels', ['single', 'bugsnag']);
    });

    describe('handle', function (): void {
        it('should pass when everything is in place', function (): void {
            $this->artisan('bugsnag:check --dry-run')->assertSuccessful();
        });

        it('should fail when the api key is missing', function (): void {
            config()->set('bugsnag.api_key', '');

            $this->artisan('bugsnag:check --dry-run')
                ->expectsOutputToContain('BUGSNAG_API_KEY')
                ->assertFailed();
        });

        it('should fail when the log channel is not defined', function (): void {
            config()->set('logging.channels.bugsnag', null);

            $this->artisan('bugsnag:check --dry-run')->assertFailed();
        });

        it('should fail when the channel is not in the stack', function (): void {
            // El fallo silencioso: la clave está puesta, el canal existe, y los
            // errores se quedan en el fichero de log porque nadie los reenvía.
            config()->set('logging.channels.stack.channels', ['single']);

            $this->artisan('bugsnag:check --dry-run')
                ->expectsOutputToContain('LOG_STACK')
                ->assertFailed();
        });

        it('should fail when the environment is not allowed to notify', function (): void {
            config()->set('bugsnag.notify_release_stages', ['production']);

            $this->artisan('bugsnag:check --dry-run')->assertFailed();
        });

        it('should not send anything on a dry run', function (): void {
            $client = Mockery::mock(Client::class);
            $client->shouldNotReceive('notifyException');
            app()->instance(Client::class, $client);

            $this->artisan('bugsnag:check --dry-run')->assertSuccessful();
        });

        it('should send a test error when not a dry run', function (): void {
            $client = Mockery::mock(Client::class);
            $client->shouldReceive('notifyException')->once();
            $client->shouldReceive('flush')->once();
            app()->instance(Client::class, $client);

            $this->artisan('bugsnag:check')->assertSuccessful();
        });

        it('should report a failure to deliver instead of throwing', function (): void {
            $client = Mockery::mock(Client::class);
            $client->shouldReceive('notifyException')->andThrow(new RuntimeException('sin salida'));
            app()->instance(Client::class, $client);

            $this->artisan('bugsnag:check')
                ->expectsOutputToContain('sin salida')
                ->assertFailed();
        });
    });
});
