<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De dónde venía quien dejó sus datos.
 *
 * `source_url` ya guardaba en qué formulario escribió, que es otra pregunta.
 * Esto guarda cómo llegó a la web, y son columnas planas y no un JSON porque
 * `channel` se filtra en el listado y la fuente y la campaña se van a acabar
 * agrupando; un JSON obliga a funciones que MariaDB y MySQL no resuelven igual.
 *
 * Sólo `channel` lleva índice. Es lo único por lo que se filtra, y con 16
 * caracteres son 64 bytes de los 3072 que admite una clave de InnoDB.
 */
return new class extends Migration
{
    /**
     * @var array<string, int>
     */
    protected const COLUMNS = [
        'utm_source' => 120,
        'utm_medium' => 120,
        'utm_campaign' => 160,
        'utm_term' => 160,
        'utm_content' => 160,
        'click_id' => 191,
        'referrer' => 512,
        'landing_url' => 512,
    ];

    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            if (! Schema::hasColumn('leads', 'channel')) {
                $table->string('channel', 16)->nullable()->after('source_url')->index();
            }

            foreach (self::COLUMNS as $column => $length) {
                if (! Schema::hasColumn('leads', $column)) {
                    $table->string($column, $length)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $existing = array_filter(
                ['channel', ...array_keys(self::COLUMNS)],
                fn (string $column): bool => Schema::hasColumn('leads', $column),
            );

            if ($existing !== []) {
                $table->dropColumn(array_values($existing));
            }
        });
    }
};
