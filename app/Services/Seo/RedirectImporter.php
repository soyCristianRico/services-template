<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Enums\RedirectMatchType;
use App\Enums\RedirectStatusCode;
use App\Models\Redirect;

/**
 * Bulk load of redirects pasted or uploaded as a list.
 *
 * A migrated site arrives with hundreds of them coming out of the old CMS. Typing
 * those one at a time is how half of them end up never being typed at all.
 *
 * @phpstan-type ImportRow array{line: int, source: string, destination: ?string, status_code: int, match_type: string, outcome: string, message: ?string}
 */
final class RedirectImporter
{
    public const OUTCOME_NEW = 'new';

    public const OUTCOME_REPLACES = 'replaces';

    public const OUTCOME_DUPLICATE = 'duplicate';

    public const OUTCOME_ERROR = 'error';

    /**
     * Read the pasted text without touching the database, so the screen can show
     * what would happen before anything happens.
     *
     * @return list<ImportRow>
     */
    public function parse(string $raw): array
    {
        $rows = [];
        $seen = [];

        foreach (preg_split('/\R/', $raw) ?: [] as $index => $line) {
            $line = trim($line);

            // Blank separators and `#` comments: both show up in hand-kept lists.
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $columns = $this->split($line);

            if ($this->isHeader($columns)) {
                continue;
            }

            $row = $this->buildRow($index + 1, $columns);

            if ($row['outcome'] !== self::OUTCOME_ERROR) {
                $key = $row['match_type'].'|'.$row['source'];

                if (isset($seen[$key])) {
                    $row['outcome'] = self::OUTCOME_DUPLICATE;
                    $row['message'] = "Repetida: ya aparece en la línea {$seen[$key]} de esta misma lista.";
                } else {
                    $seen[$key] = $row['line'];
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param  list<ImportRow>  $rows
     * @return array{created: int, updated: int, skipped: int}
     */
    public function import(array $rows, bool $overwrite): array
    {
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($rows as $row) {
            if ($row['outcome'] === self::OUTCOME_ERROR || $row['outcome'] === self::OUTCOME_DUPLICATE) {
                $result['skipped']++;

                continue;
            }

            $existing = Redirect::query()->where('source', $row['source'])->first();

            if ($existing !== null && ! $overwrite) {
                $result['skipped']++;

                continue;
            }

            $attributes = [
                'source' => $row['source'],
                'destination' => $row['destination'],
                'match_type' => $row['match_type'],
                'status_code' => $row['status_code'],
                'is_active' => true,
                'preserve_query' => true,
            ];

            if ($existing !== null) {
                $existing->update($attributes);
                $result['updated']++;

                continue;
            }

            Redirect::create($attributes);
            $result['created']++;
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    protected function split(string $line): array
    {
        // Tab and semicolon first because a URL can legitimately contain a comma,
        // and guessing wrong there silently truncates the destination.
        $separator = match (true) {
            str_contains($line, "\t") => "\t",
            str_contains($line, ';') => ';',
            str_contains($line, ',') => ',',
            default => null,
        };

        $columns = $separator === null
            ? preg_split('/\s+/', $line) ?: []
            : str_getcsv($line, $separator, '"', '\\');

        return array_values(array_map(static fn ($value): string => trim((string) $value), $columns));
    }

    /**
     * @param  list<string>  $columns
     */
    protected function isHeader(array $columns): bool
    {
        $first = mb_strtolower($columns[0] ?? '');

        return in_array($first, ['source', 'origen', 'from', 'url', 'origin', 'old', 'antigua'], true);
    }

    /**
     * @param  list<string>  $columns
     * @return ImportRow
     */
    protected function buildRow(int $line, array $columns): array
    {
        $row = [
            'line' => $line,
            'source' => $columns[0] ?? '',
            'destination' => $columns[1] ?? null,
            'status_code' => RedirectStatusCode::MovedPermanently->value,
            'match_type' => RedirectMatchType::Exact->value,
            'outcome' => self::OUTCOME_NEW,
            'message' => null,
        ];

        if (isset($columns[2]) && $columns[2] !== '') {
            $code = RedirectStatusCode::tryFrom((int) $columns[2]);

            if ($code === null) {
                return $this->fail($row, "Código «{$columns[2]}» no soportado. Usa 301, 302, 307, 308 o 410.");
            }

            $row['status_code'] = $code->value;
        }

        if (isset($columns[3]) && $columns[3] !== '') {
            $type = $this->matchType($columns[3]);

            if ($type === null) {
                return $this->fail($row, "Tipo «{$columns[3]}» no reconocido. Usa exacta, prefijo o regex.");
            }

            $row['match_type'] = $type->value;
        }

        return $this->validate($row);
    }

    /**
     * @param  ImportRow  $row
     * @return ImportRow
     */
    protected function validate(array $row): array
    {
        $isRegex = $row['match_type'] === RedirectMatchType::Regex->value;
        $isGone = $row['status_code'] === RedirectStatusCode::Gone->value;

        if (trim($row['source']) === '') {
            return $this->fail($row, 'Falta la dirección antigua.');
        }

        $row['source'] = $isRegex ? trim($row['source']) : RedirectPath::normalize($row['source']);

        if ($isRegex && ! RedirectPath::isValidPattern($row['source'])) {
            return $this->fail($row, 'El patrón no es una expresión regular válida.');
        }

        if (! $isRegex && RedirectPath::isExcluded($row['source'])) {
            return $this->fail($row, 'Pertenece al panel o a los servicios internos: no se puede redirigir.');
        }

        if ($isGone) {
            $row['destination'] = null;

            return $this->withExistingOutcome($row);
        }

        $destination = trim((string) $row['destination']);

        if ($destination === '') {
            return $this->fail($row, 'Falta el destino. Si la página se retiró sin sustituta, pon 410 en la tercera columna.');
        }

        $destination = $this->localise($destination);

        if (! RedirectPath::isExternal($destination) && ! str_starts_with($destination, '/')) {
            $destination = '/'.ltrim($destination, '/');
        }

        if (! $isRegex && ! RedirectPath::isExternal($destination) && RedirectPath::normalize($destination) === $row['source']) {
            return $this->fail($row, 'El origen y el destino son la misma dirección.');
        }

        $row['destination'] = $destination;

        return $this->withExistingOutcome($row);
    }

    /**
     * An export off the old CMS writes destinations as absolute URLs on the old
     * domain. Left alone, every single redirect would bounce visitors back to the
     * site being retired.
     */
    protected function localise(string $destination): string
    {
        if (! RedirectPath::isExternal($destination)) {
            return $destination;
        }

        $host = parse_url($destination, PHP_URL_HOST);
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if ($host === null || $appHost === null || mb_strtolower($host) !== mb_strtolower($appHost)) {
            return $destination;
        }

        $path = (string) parse_url($destination, PHP_URL_PATH);
        $query = parse_url($destination, PHP_URL_QUERY);

        return RedirectPath::normalize($path).($query === null ? '' : '?'.$query);
    }

    /**
     * @param  ImportRow  $row
     * @return ImportRow
     */
    protected function withExistingOutcome(array $row): array
    {
        if (Redirect::query()->where('source', $row['source'])->exists()) {
            $row['outcome'] = self::OUTCOME_REPLACES;
            $row['message'] = 'Ya existe una redirección con ese origen.';
        }

        return $row;
    }

    /**
     * @param  ImportRow  $row
     * @return ImportRow
     */
    protected function fail(array $row, string $message): array
    {
        $row['outcome'] = self::OUTCOME_ERROR;
        $row['message'] = $message;

        return $row;
    }

    protected function matchType(string $value): ?RedirectMatchType
    {
        return match (mb_strtolower($value)) {
            'exact', 'exacta', 'exacto' => RedirectMatchType::Exact,
            'prefix', 'prefijo' => RedirectMatchType::Prefix,
            'regex', 'regexp', 'regular' => RedirectMatchType::Regex,
            default => null,
        };
    }
}
