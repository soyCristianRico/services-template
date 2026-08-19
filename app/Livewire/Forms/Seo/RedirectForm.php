<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Seo;

use App\Enums\RedirectMatchType;
use App\Enums\RedirectStatusCode;
use App\Models\Redirect;
use App\Services\Seo\RedirectPath;
use App\Services\Seo\RedirectWriter;
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
        return $this->writer()->rules($this->attributes(), $this->id);
    }

    public function messages(): array
    {
        return $this->writer()->messages();
    }

    public function save(): Redirect
    {
        // Normalised before validating, not after: otherwise the unique check runs
        // against what was typed and `/Contacto/` slips past the `/contacto` that
        // is already stored.
        $this->normalizeInput();

        $this->validate();

        $redirect = $this->writer()->persist($this->attributes(), $this->id);

        $this->id = $redirect->id;

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
        $normalized = $this->writer()->normalize($this->attributes());

        $this->source = $normalized['source'];
        $this->destination = $normalized['destination'];
    }

    /**
     * The form's state as the writer expects it — one shape shared with the MCP
     * tools, so both go through the same rules.
     *
     * @return array<string, mixed>
     */
    protected function attributes(): array
    {
        return [
            'source' => $this->source,
            'destination' => $this->destination,
            'match_type' => $this->match_type,
            'status_code' => $this->status_code,
            'is_active' => $this->is_active,
            'preserve_query' => $this->preserve_query,
            'notes' => $this->notes,
        ];
    }

    /**
     * Resolved on call rather than held in a property: a Form is rebuilt and
     * rehydrated on every Livewire request, and a service is not state to carry
     * across the wire.
     */
    protected function writer(): RedirectWriter
    {
        return app(RedirectWriter::class);
    }
}
