<?php

declare(strict_types=1);

namespace App\Enums;

enum MenuItemType: string
{
    case Link = 'link';
    case DynamicBlock = 'dynamic_block';

    public function label(): string
    {
        return match ($this) {
            self::Link => 'Enlace',
            self::DynamicBlock => 'Bloque dinámico',
        };
    }
}
