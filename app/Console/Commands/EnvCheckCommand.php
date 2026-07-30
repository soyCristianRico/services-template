<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Diagnoses a production environment before trusting it.
 *
 * The failures this catches are all silent: the site answers, the form thanks
 * the user, and the notification email never arrives. Nothing logs an error,
 * so without this you find out when someone asks why they got no leads.
 *
 * Run by hand over SSH when verifying a deploy (DAP010 step 9), right before
 * the test lead. It is deliberately NOT wired into the Forge deploy script:
 * the first deploy of a site happens before its environment is configured, so
 * a gate there would abort every single first deploy, and the lesson learned
 * would be to remove the gate.
 *
 * Everything is read through config(), never env(): a production environment
 * runs `config:cache`, and after that env() returns null outside config files.
 */
class EnvCheckCommand extends Command
{
    protected $signature = 'env:check';

    protected $description = 'Check a production environment for the misconfigurations that fail silently';

    /** @var list<string> */
    protected array $problems = [];

    public function handle(): int
    {
        $environment = (string) config('app.env');

        $this->components->info("Environment: {$environment}");

        if ($environment !== 'production') {
            $this->line('  Only production is checked — nothing to do here.');

            return self::SUCCESS;
        }

        $this->checkMailer();
        $this->checkFromAddress();
        $this->checkDebug();
        $this->checkUrl();
        $this->reportQueue();

        if ($this->problems === []) {
            $this->newLine();
            $this->components->info('Nothing wrong found.');

            return self::SUCCESS;
        }

        // The problems are already listed above, in order, interleaved with what
        // passed — repeating them here would only make the output harder to read.
        $this->newLine();
        $this->components->error(count($this->problems).' problem(s) found. Fix them in the site\'s environment, redeploy and run this again.');

        return self::FAILURE;
    }

    protected function checkMailer(): void
    {
        $mailer = (string) config('mail.default');

        if ($mailer === 'log') {
            $this->problem("MAIL_MAILER is 'log': mail is written to the log file instead of being sent. Set the real mailer (e.g. mailgun).");

            return;
        }

        $this->pass("MAIL_MAILER = {$mailer}");
    }

    protected function checkFromAddress(): void
    {
        $from = (string) config('mail.from.address');

        if ($from === '' || Str::contains($from, 'example.com')) {
            $this->problem("MAIL_FROM_ADDRESS is still the placeholder ({$from}): the message is rejected or lands in spam. Use an address on the site's domain.");

            return;
        }

        $this->pass("MAIL_FROM_ADDRESS = {$from}");
    }

    protected function checkDebug(): void
    {
        if (config('app.debug') === true) {
            $this->problem('APP_DEBUG is true: stack traces and configuration are exposed to visitors.');

            return;
        }

        $this->pass('APP_DEBUG = false');
    }

    protected function checkUrl(): void
    {
        $url = (string) config('app.url');
        $host = (string) parse_url($url, PHP_URL_HOST);

        if ($host === '' || in_array($host, ['localhost', '127.0.0.1'], true)) {
            $this->problem("APP_URL points at {$url}: every generated link, canonical and sitemap entry is wrong.");

            return;
        }

        if (! Str::startsWith($url, 'https://')) {
            $this->problem("APP_URL is not https ({$url}): links and canonicals will be emitted over http.");

            return;
        }

        $this->pass("APP_URL = {$url}");
    }

    /**
     * Informational only. The worker's own connection lives in the Supervisor
     * configuration that Forge manages, outside the repo, so nothing here can
     * read it — but a worker listening on a different connection is exactly the
     * failure that leaves jobs queued forever. Print the value so it can be
     * compared against the worker by eye.
     */
    protected function reportQueue(): void
    {
        $connection = (string) config('queue.default');

        $this->line("  <fg=yellow>·</> QUEUE_CONNECTION = {$connection} — the Forge worker MUST listen on this same connection.");
    }

    protected function pass(string $message): void
    {
        $this->line("  <fg=green>✓</> {$message}");
    }

    protected function problem(string $message): void
    {
        $this->problems[] = $message;
        $this->line("  <fg=red>✗</> {$message}");
    }
}
