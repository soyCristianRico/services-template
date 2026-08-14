<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * De dónde salió un lead, en la lengua en la que se habla de esto.
 *
 * Es lo único de la atribución que se mira en el listado: la fuente, el medio y
 * la campaña son el detalle de la ficha, y el referrer sólo se abre cuando algo
 * no cuadra. Por eso el canal es una columna con índice y lo demás no.
 *
 * Un lead sin canal no es un lead sin origen: es uno que nació fuera de una
 * visita —la importación de TidyCal, el webhook de Stripe— donde no hay sesión
 * que preguntar. Se enseña como «—» y no como «Directo», que sería mentir.
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

    /**
     * El color del `flux:badge` del listado.
     */
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
