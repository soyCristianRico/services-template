<?php

declare(strict_types=1);

namespace App\Console\Commands\Monitoring;

use Bugsnag\BugsnagLaravel\BugsnagServiceProvider;
use Bugsnag\Client;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

/**
 * Comprueba que un error de esta aplicación llegaría a Bugsnag.
 *
 * Cada pieza puede faltar por su cuenta y ninguna se queja: sin clave el cliente
 * descarta en silencio, sin el canal en LOG_STACK los errores se quedan en el
 * fichero de log, y sin release stage la entrega se corta sin decir nada.
 */
class CheckBugsnagCommand extends Command
{
    protected $signature = 'bugsnag:check {--dry-run : Sólo comprueba la configuración, sin enviar un error de prueba}';

    protected $description = 'Comprueba la integración con Bugsnag y envía un error de prueba';

    public function handle(): int
    {
        $problems = array_filter([
            $this->checkApiKey(),
            $this->checkProvider(),
            $this->checkLogChannel(),
            $this->checkLogStack(),
            $this->checkReleaseStage(),
        ]);

        if ($problems !== []) {
            $this->newLine();
            $this->error('Bugsnag no está listo:');

            foreach ($problems as $problem) {
                $this->line("  · {$problem}");
            }

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('La configuración es correcta.');

        if ($this->option('dry-run')) {
            $this->line('No se envía nada (--dry-run).');

            return self::SUCCESS;
        }

        return $this->sendTestError();
    }

    protected function checkApiKey(): ?string
    {
        $key = config('bugsnag.api_key');

        if (blank($key)) {
            return 'BUGSNAG_API_KEY está vacía.';
        }

        $this->line('  ✓ Clave de API configurada ('.substr((string) $key, 0, 6).'…)');

        return null;
    }

    protected function checkProvider(): ?string
    {
        if (! app()->providerIsLoaded(BugsnagServiceProvider::class)) {
            return 'BugsnagServiceProvider no está registrado en bootstrap/providers.php.';
        }

        $this->line('  ✓ Service provider registrado');

        return null;
    }

    protected function checkLogChannel(): ?string
    {
        if (config('logging.channels.bugsnag.driver') !== 'bugsnag') {
            return "Falta el canal 'bugsnag' en config/logging.php.";
        }

        $this->line('  ✓ Canal de log definido');

        return null;
    }

    protected function checkLogStack(): ?string
    {
        $stack = config('logging.channels.stack.channels', []);

        if (! in_array('bugsnag', (array) $stack, true)) {
            return 'El canal no está en LOG_STACK. Los errores se quedan en el fichero de log: pon LOG_STACK="single,bugsnag".';
        }

        $this->line('  ✓ Canal dentro de LOG_STACK');

        return null;
    }

    /**
     * Un release stage fuera de la lista permitida hace que el cliente descarte
     * todo sin avisar, que es el fallo más difícil de ver desde fuera.
     */
    protected function checkReleaseStage(): ?string
    {
        $allowed = config('bugsnag.notify_release_stages');
        $stage = config('bugsnag.release_stage') ?? app()->environment();

        if (is_array($allowed) && ! in_array($stage, $allowed, true)) {
            return "El entorno «{$stage}» no está en BUGSNAG_NOTIFY_RELEASE_STAGES, así que no se envía nada.";
        }

        $this->line("  ✓ Entorno «{$stage}» habilitado para enviar");

        return null;
    }

    protected function sendTestError(): int
    {
        $this->newLine();
        $this->line('Enviando un error de prueba…');

        try {
            $client = app(Client::class);
            $client->notifyException(new RuntimeException(
                'Error de prueba de bugsnag:check en '.config('app.name')
            ));
            $client->flush();
        } catch (Throwable $e) {
            $this->error('No se pudo enviar: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Enviado. Debería aparecer en la bandeja de Bugsnag en unos segundos.');
        $this->line('Si no aparece, el problema está entre el servidor y Bugsnag (salida HTTPS), no en la configuración.');

        return self::SUCCESS;
    }
}
