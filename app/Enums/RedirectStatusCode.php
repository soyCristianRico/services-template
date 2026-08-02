<?php

declare(strict_types=1);

namespace App\Enums;

enum RedirectStatusCode: int
{
    case MovedPermanently = 301;
    case Found = 302;
    case TemporaryRedirect = 307;
    case PermanentRedirect = 308;
    case Gone = 410;

    public function label(): string
    {
        return match ($this) {
            self::MovedPermanently => '301 · Permanente',
            self::Found => '302 · Temporal',
            self::TemporaryRedirect => '307 · Temporal (conserva el método)',
            self::PermanentRedirect => '308 · Permanente (conserva el método)',
            self::Gone => '410 · Contenido eliminado',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::MovedPermanently => 'La dirección cambió para siempre. Google traslada el posicionamiento al destino. Es la opción normal al migrar una web.',
            self::Found => 'El cambio es provisional. Google mantiene indexada la dirección antigua. Para campañas y mantenimientos.',
            self::TemporaryRedirect => 'Como la 302, pero el navegador no puede convertir un envío de formulario en una visita normal.',
            self::PermanentRedirect => 'Como la 301, pero el navegador no puede convertir un envío de formulario en una visita normal.',
            self::Gone => 'La página se retiró y no hay sustituta. Le dice a Google que la borre del índice en vez de seguir visitándola.',
        };
    }

    /**
     * A 410 is not a redirect: it has no destination and answers on the spot.
     */
    public function isGone(): bool
    {
        return $this === self::Gone;
    }
}
