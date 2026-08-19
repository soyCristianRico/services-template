<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Enums\RedirectMatchType;
use App\Enums\RedirectStatusCode;
use App\Models\Redirect;
use Closure;
use Illuminate\Validation\Rule;

/**
 * What a redirect has to satisfy before it reaches the table, in one place.
 *
 * Two doors write here now — the admin form and the MCP tools — and the rules
 * that matter are the ones nobody thinks about while typing: a chain that bleeds
 * positioning, a loop, a pattern aimed at the panel itself. A copy of them living
 * next to each door is a copy that drifts, and the door that drifts is the one
 * that lets the bad row in.
 *
 * @phpstan-type Attributes array{source?: string, destination?: ?string, match_type?: mixed, status_code?: mixed, is_active?: mixed, preserve_query?: mixed, notes?: ?string}
 */
final class RedirectWriter
{
    /**
     * Reduce what was typed to what will be stored and compared.
     *
     * @param  Attributes  $attributes
     * @return Attributes
     */
    public function normalize(array $attributes): array
    {
        $source = trim((string) ($attributes['source'] ?? ''));

        // An empty box has to stay empty so `required` can fire. Normalising it
        // would turn "nothing" into "/" and quietly put a redirect on the home
        // page — from a form the person thought they had left blank.
        $attributes['source'] = match (true) {
            $source === '' => '',
            $this->matchType($attributes) === RedirectMatchType::Regex => $source,
            default => RedirectPath::normalize($source),
        };

        if (($attributes['destination'] ?? null) !== null) {
            $attributes['destination'] = trim((string) $attributes['destination']);
        }

        if ($this->isGone($attributes)) {
            $attributes['destination'] = null;
        }

        return $attributes;
    }

    /**
     * The rules depend on what is being written — a regex source and an exact one
     * are checked against different things — so they are built from the
     * attributes, already normalised.
     *
     * @param  Attributes  $attributes
     * @return array<string, list<mixed>>
     */
    public function rules(array $attributes, ?int $ignoreId = null): array
    {
        $matchType = $this->matchType($attributes);
        $isGone = $this->isGone($attributes);
        $source = (string) ($attributes['source'] ?? '');

        return [
            'source' => [
                'required',
                'string',
                'max:500',
                Rule::unique('redirects', 'source')->ignore($ignoreId),
                fn (string $attribute, mixed $value, Closure $fail) => $this->checkSource((string) $value, $matchType, $fail),
            ],
            'destination' => [
                $isGone ? 'nullable' : 'required',
                'nullable',
                'string',
                'max:500',
                fn (string $attribute, mixed $value, Closure $fail) => $this->checkDestination((string) $value, $source, $matchType, $isGone, $ignoreId, $fail),
            ],
            'match_type' => ['required', Rule::enum(RedirectMatchType::class)],
            'status_code' => ['required', Rule::enum(RedirectStatusCode::class)],
            'is_active' => ['boolean'],
            'preserve_query' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'source.required' => 'Escribe la dirección antigua.',
            'source.unique' => 'Ya existe una redirección para esa dirección.',
            'destination.required' => 'Escribe a dónde tiene que ir. Si la página se retiró sin sustituta, elige el código 410.',
        ];
    }

    /**
     * @param  Attributes  $attributes
     */
    public function persist(array $attributes, ?int $id = null): Redirect
    {
        $payload = [
            'source' => (string) ($attributes['source'] ?? ''),
            'destination' => $this->isGone($attributes) ? null : ($attributes['destination'] ?? null),
            'match_type' => $this->matchType($attributes)->value,
            'status_code' => $this->statusCode($attributes)->value,
            'is_active' => (bool) ($attributes['is_active'] ?? true),
            'preserve_query' => (bool) ($attributes['preserve_query'] ?? true),
            'notes' => $attributes['notes'] ?? null,
        ];

        if ($id !== null) {
            $redirect = Redirect::findOrFail($id);
            $redirect->update($payload);

            return $redirect;
        }

        return Redirect::create($payload);
    }

    /**
     * @param  Attributes  $attributes
     */
    public function matchType(array $attributes): RedirectMatchType
    {
        $value = $attributes['match_type'] ?? null;

        if ($value instanceof RedirectMatchType) {
            return $value;
        }

        return RedirectMatchType::tryFrom((string) $value) ?? RedirectMatchType::Exact;
    }

    /**
     * @param  Attributes  $attributes
     */
    public function statusCode(array $attributes): RedirectStatusCode
    {
        $value = $attributes['status_code'] ?? null;

        if ($value instanceof RedirectStatusCode) {
            return $value;
        }

        return RedirectStatusCode::tryFrom((int) $value) ?? RedirectStatusCode::MovedPermanently;
    }

    /**
     * @param  Attributes  $attributes
     */
    public function isGone(array $attributes): bool
    {
        return $this->statusCode($attributes)->isGone();
    }

    protected function checkSource(string $value, RedirectMatchType $matchType, Closure $fail): void
    {
        if ($matchType === RedirectMatchType::Regex) {
            if (! RedirectPath::isValidPattern($value)) {
                $fail('El patrón no es una expresión regular válida. Revisa los paréntesis y las barras.');
            }

            return;
        }

        if (RedirectPath::isExcluded($value)) {
            $fail('Esa dirección pertenece al panel o a los servicios internos y no se puede redirigir.');

            return;
        }

        if ($matchType === RedirectMatchType::Prefix && RedirectPath::normalize($value) === '/') {
            $fail('Un prefijo «/» se llevaría por delante la web entera. Usa una dirección concreta.');
        }
    }

    protected function checkDestination(string $value, string $source, RedirectMatchType $matchType, bool $isGone, ?int $ignoreId, Closure $fail): void
    {
        if ($value === '' || $isGone) {
            return;
        }

        if (RedirectPath::isExternal($value)) {
            return;
        }

        if (! str_starts_with($value, '/')) {
            $fail('El destino tiene que empezar por «/», o ser una dirección completa con https://.');

            return;
        }

        // A pattern's destination holds `$1` placeholders, so comparing it against
        // anything before substitution says nothing.
        if ($matchType === RedirectMatchType::Regex) {
            return;
        }

        $destination = RedirectPath::normalize($value);

        if ($destination === RedirectPath::normalize($source)) {
            $fail('El origen y el destino son la misma dirección: el navegador se quedaría dando vueltas.');

            return;
        }

        $next = Redirect::query()
            ->active()
            ->where('match_type', RedirectMatchType::Exact->value)
            ->where('source', $destination)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->first();

        if ($next !== null) {
            $fail("El destino es a su vez el origen de otra redirección ({$next->source} → {$next->destination}). Encadenarlas hace perder posicionamiento: apunta directamente a {$next->destination}.");
        }
    }
}
