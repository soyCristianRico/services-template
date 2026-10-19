<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A heading of the admin sidebar. Each one gathers ONE kind of work, named the
 * way the client names it — never a catch-all like «Contenido» that ends up
 * holding whatever did not fit elsewhere.
 */
enum AdminGroup: string
{
    case Catalog = 'catalog';
    case Web = 'web';
    case Seo = 'seo';

    public function label(): string
    {
        return match ($this) {
            self::Catalog => 'Catálogo',
            self::Web => 'Web',
            self::Seo => 'Posicionamiento',
        };
    }
}
