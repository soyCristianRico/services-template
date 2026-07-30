<?php

declare(strict_types=1);

namespace App\Enums;

enum MenuLocation: string
{
    case Header = 'header';
    case Footer = 'footer';
    case Legal = 'legal';

    public function label(): string
    {
        return match ($this) {
            self::Header => 'Cabecera',
            self::Footer => 'Pie de página',
            self::Legal => 'Legales',
        };
    }
}
