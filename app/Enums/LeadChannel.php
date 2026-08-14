<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * De dónde salió un lead. Es lo único de la atribución que se mira en el
 * listado, y por eso es la única columna con índice.
 *
 * Sin canal no es sin origen: es un lead nacido fuera de una visita, donde no
 * hay sesión que preguntar. Se enseña «—» y no «Directo», que sería mentir.
 */
enum LeadChannel: string
{
    case Ads = 'ads';
    case Organic = 'organic';
    case Social = 'social';
    case Email = 'email';
    case Referral = 'referral';
    case Direct = 'direct';

    public function label(): string
    {
        return match ($this) {
            self::Ads => 'Publicidad',
            self::Organic => 'Búsqueda orgánica',
            self::Social => 'Redes sociales',
            self::Email => 'Email',
            self::Referral => 'Referencia',
            self::Direct => 'Directo',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Ads => 'amber',
            self::Organic => 'green',
            self::Social => 'purple',
            self::Email => 'blue',
            self::Referral => 'cyan',
            self::Direct => 'zinc',
        };
    }
}
