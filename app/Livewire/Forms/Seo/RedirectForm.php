<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Seo;

use App\Enums\RedirectMatchType;
use App\Enums\RedirectStatusCode;
use App\Models\Redirect;
use App\Services\Seo\RedirectPath;
use Closure;
use Illuminate\Validation\Rule;
use Livewire\Form;

class RedirectForm extends Form
{
    public ?int $id = null;

    public string $source = '';

    public ?string $destination = null;

    public string $match_type = 'exact';

    public int $status_code = 301;

    public bool $is_active = true;

    public bool $preserve_query = true;

    public ?string $notes = null;

    public function setRedirect(Redirect $redirect): void
    {
        $this->id = $redirect->id;
        $this->source = $redirect->source;
        $this->destination = $redirect->destination;
        $this->match_type = $redirect->match_type->value;
        $this->status_code = $redirect->status_code->value;
        $this->is_active = $redirect->is_active;
        $this->preserve_query = $redirect->preserve_query;
        $this->notes = $redirect->notes;
    }

    public function matchType(): RedirectMatchType
    {
        return RedirectMatchType::tryFrom($this->match_type) ?? RedirectMatchType::Exact;
    }

    public function isGone(): bool
    {
        return $this->status_code === RedirectStatusCode::Gone->value;
    }

    public function rules(): array
    {
        return [
            'source' => [
                'required',
                'string',
                'max:500',
                Rule::unique('redirects', 'source')->ignore($this->id),
                fn (string $attribute, mixed $value, Closure $fail) => $this->checkSource((string) $value, $fail),
            ],
            'destination' => [
                $this->isGone() ? 'nullable' : 'required',
                'nullable',
                'string',
                'max:500',
                fn (string $attribute, mixed $value, Closure $fail) => $this->checkDestination((string) $value, $fail),
            ],
            'match_type' => ['required', Rule::enum(RedirectMatchType::class)],
            'status_code' => ['required', Rule::enum(RedirectStatusCode::class)],
            'is_active' => ['boolean'],
            'preserve_query' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'source.required' => 'Escribe la dirección antigua.',
            'source.unique' => 'Ya existe una redirección para esa dirección.',
            'destination.required' => 'Escribe a dónde tiene que ir. Si la página se retiró sin sustituta, elige el código 410.',
        ];
    }

    public function save(): Redirect
    {
        // Normalised before validating, not after: otherwise the unique check runs
        // against what was typed and `/Contacto/` slips past the `/contacto` that
        // is already stored.
        $this->normalizeInput();

        $this->validate();

        $attributes = [
            'source' => $this->source,
            'destination' => $this->isGone() ? null : $this->destination,
            'match_type' => $this->match_type,
            'status_code' => $this->status_code,
            'is_active' => $this->is_active,
            'preserve_query' => $this->preserve_query,
            'notes' => $this->notes,
        ];

        if ($this->id !== null) {
            $redirect = Redirect::findOrFail($this->id);
            $redirect->update($attributes);
        } else {
            $redirect = Redirect::create($attributes);
            $this->id = $redirect->id;
        }

        return $redirect;
    }

    /**
     * What this redirect would do to a given address. Powers the tester on the
     * edit screen, which is the only way a non-technical person can tell a working
     * pattern from a broken one before it is live.
     */
    public function preview(string $path): ?string
    {
        $type = $this->matchType();
        $destination = (string) $this->destination;

        if ($this->isGone()) {
            return null;
        }

        if ($type === RedirectMatchType::Regex) {
            if (! RedirectPath::isValidPattern($this->source)) {
                return null;
            }

            $compiled = RedirectPath::compilePattern($this->source);
            $normalized = RedirectPath::normalize($path);

            if (@preg_match($compiled, $normalized) !== 1) {
                return null;
            }

            $replaced = @preg_replace($compiled, $destination, $normalized);

            return is_string($replaced) && $replaced !== '' ? $replaced : null;
        }

        $normalized = RedirectPath::normalize($path);
        $source = RedirectPath::normalize($this->source);

        if ($type === RedirectMatchType::Exact) {
            return $normalized === $source ? $destination : null;
        }

        if ($normalized !== $source && ! str_starts_with($normalized, $source.'/')) {
            return null;
        }

        $remainder = substr($normalized, strlen($source));

        return rtrim($destination, '/').$remainder;
    }

    protected function normalizeInput(): void
    {
        $source = trim($this->source);

        // An empty box has to stay empty so `required` can fire. Normalising it
        // would turn "nothing" into "/" and quietly put a redirect on the home
        // page — from a form the person thought they had left blank.
        $this->source = match (true) {
            $source === '' => '',
            $this->matchType() === RedirectMatchType::Regex => $source,
            default => RedirectPath::normalize($source),
        };

        if ($this->destination !== null) {
            $this->destination = trim($this->destination);
        }

        if ($this->isGone()) {
            $this->destination = null;
        }
    }

    protected function checkSource(string $value, Closure $fail): void
    {
        if ($this->matchType() === RedirectMatchType::Regex) {
            if (! RedirectPath::isValidPattern($value)) {
                $fail('El patrón no es una expresión regular válida. Revisa los paréntesis y las barras.');
            }

            return;
        }

        if (RedirectPath::isExcluded($value)) {
            $fail('Esa dirección pertenece al panel o a los servicios internos y no se puede redirigir.');

            return;
        }

        if ($this->matchType() === RedirectMatchType::Prefix && RedirectPath::normalize($value) === '/') {
            $fail('Un prefijo «/» se llevaría por delante la web entera. Usa una dirección concreta.');
        }
    }

    protected function checkDestination(string $value, Closure $fail): void
    {
        if ($value === '' || $this->isGone()) {
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
        if ($this->matchType() === RedirectMatchType::Regex) {
            return;
        }

        $destination = RedirectPath::normalize($value);

        if ($destination === RedirectPath::normalize($this->source)) {
            $fail('El origen y el destino son la misma dirección: el navegador se quedaría dando vueltas.');

            return;
        }

        $next = Redirect::query()
            ->active()
            ->where('match_type', RedirectMatchType::Exact->value)
            ->where('source', $destination)
            ->when($this->id !== null, fn ($query) => $query->whereKeyNot($this->id))
            ->first();

        if ($next !== null) {
            $fail("El destino es a su vez el origen de otra redirección ({$next->source} → {$next->destination}). Encadenarlas hace perder posicionamiento: apunta directamente a {$next->destination}.");
        }
    }
}
