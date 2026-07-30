<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;

/**
 * The command reads config(), not env(), because production runs config:cache —
 * so the tests set config values too.
 */
function productionEnv(array $overrides = []): void
{
    Config::set(array_merge([
        'app.env' => 'production',
        'app.debug' => false,
        'app.url' => 'https://makerguia.com',
        'mail.default' => 'mailgun',
        'mail.from.address' => 'noreply@makerguia.com',
        'queue.default' => 'redis',
    ], $overrides));
}

describe('EnvCheckCommand', function (): void {
    describe('handle', function (): void {
        it('should pass a correctly configured production environment', function (): void {
            productionEnv();

            $this->artisan('env:check')
                ->expectsOutputToContain('Nothing wrong found.')
                ->assertExitCode(0);
        });

        it('should skip any environment that is not production', function (): void {
            productionEnv(['app.env' => 'local', 'mail.default' => 'log', 'app.debug' => true]);

            $this->artisan('env:check')
                ->expectsOutputToContain('Only production is checked')
                ->assertExitCode(0);
        });
    });

    describe('the silent failures', function (): void {
        it('should catch a mailer left on log', function (): void {
            productionEnv(['mail.default' => 'log']);

            $this->artisan('env:check')
                ->expectsOutputToContain('written to the log file')
                ->assertExitCode(1);
        });

        it('should catch the placeholder from address', function (): void {
            productionEnv(['mail.from.address' => 'hello@example.com']);

            $this->artisan('env:check')
                ->expectsOutputToContain('placeholder')
                ->assertExitCode(1);
        });

        it('should catch an empty from address', function (): void {
            productionEnv(['mail.from.address' => '']);

            $this->artisan('env:check')->assertExitCode(1);
        });

        it('should catch debug left on', function (): void {
            productionEnv(['app.debug' => true]);

            $this->artisan('env:check')
                ->expectsOutputToContain('APP_DEBUG is true')
                ->assertExitCode(1);
        });

        it('should catch an APP_URL still on localhost', function (): void {
            productionEnv(['app.url' => 'http://localhost']);

            $this->artisan('env:check')
                ->expectsOutputToContain('APP_URL points at')
                ->assertExitCode(1);
        });

        it('should catch an APP_URL that is not https', function (): void {
            productionEnv(['app.url' => 'http://makerguia.com']);

            $this->artisan('env:check')
                ->expectsOutputToContain('not https')
                ->assertExitCode(1);
        });

        it('should report every problem at once, not just the first', function (): void {
            productionEnv([
                'mail.default' => 'log',
                'mail.from.address' => 'hello@example.com',
                'app.debug' => true,
                'app.url' => 'http://localhost',
            ]);

            $this->artisan('env:check')
                ->expectsOutputToContain('4 problem(s) found')
                ->assertExitCode(1);
        });
    });

    // The pair has to agree: what new-site.sh derives from the domain into
    // .env.production.example must be exactly what this command accepts. If one
    // side changes without the other, a deploy passes the check and still fails.
    describe('agreement with the generated .env.production.example', function (): void {
        it('should accept the values new-site.sh derives from the domain', function (): void {
            $domain = 'makerguia.com';

            Config::set([
                'app.env' => 'production',
                'app.debug' => false,
                'app.url' => "https://{$domain}",
                'mail.default' => 'mailgun',
                'mail.from.address' => "noreply@{$domain}",
                'queue.default' => 'redis',
            ]);

            $this->artisan('env:check')
                ->expectsOutputToContain('Nothing wrong found.')
                ->assertExitCode(0);
        });
    });

    describe('queue connection', function (): void {
        // It cannot be validated from inside the app — the worker's connection
        // lives in Forge's Supervisor config — so it is printed to be compared.
        it('should print the queue connection so it can be matched against the worker', function (): void {
            productionEnv(['queue.default' => 'redis']);

            $this->artisan('env:check')
                ->expectsOutputToContain('QUEUE_CONNECTION = redis')
                ->assertExitCode(0);
        });
    });
});
