<?php

declare(strict_types=1);

namespace App\Enums;

enum RedirectMatchType: string
{
    case Exact = 'exact';
    case Prefix = 'prefix';
    case Regex = 'regex';

    public function label(): string
    {
        return match ($this) {
            self::Exact => 'Exacta',
            self::Prefix => 'Por prefijo',
            self::Regex => 'Expresión regular',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Exact => 'Solo esa dirección, tal cual. Es la que se usa el 95% de las veces.',
            self::Prefix => 'Esa dirección y todo lo que cuelgue de ella. Lo que sobra se pega al destino.',
            self::Regex => 'Patrón avanzado. Permite capturar trozos del origen y colocarlos en el destino con $1, $2…',
        };
    }
}
