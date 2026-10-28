<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Models\Service;
use Illuminate\Validation\Rule;
use Livewire\Form;

class ServiceForm extends Form
{
    public ?int $id = null;

    public ?int $category_id = null;

    /** @var array<int, int> Extra categories the service also appears in. */
    public array $additional_category_ids = [];

    public string $name = '';

    public string $slug = '';

    public ?string $short_description = null;

    public ?string $description = null;

    public string $customFieldsJson = '';

    public bool $is_active = true;

    public int $position = 0;

    public function setService(Service $service): void
    {
        $this->id = $service->id;
        $this->category_id = $service->category_id;
        $this->additional_category_ids = $service->additionalCategories()->pluck('categories.id')->all();
        $this->name = $service->name;
        $this->slug = $service->slug;
        $this->short_description = $service->short_description;
        $this->description = $service->description;
        $this->customFieldsJson = $service->custom_fields !== null
            ? json_encode($service->custom_fields, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            : '';
        $this->is_active = $service->is_active;
        $this->position = $service->position;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'additional_category_ids' => ['array'],
            'additional_category_ids.*' => ['integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:160'],
            'slug' => [
                'required',
                'string',
                'max:200',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('services', 'slug')->ignore($this->id),
            ],
            'short_description' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'customFieldsJson' => ['nullable', 'string', function (string $attribute, $value, $fail): void {
                if (blank($value)) {
                    return;
                }
                $decoded = json_decode((string) $value, true);
                if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                    $fail('Los campos custom deben ser un JSON válido.');
                }
                if ($decoded !== null && ! is_array($decoded)) {
                    $fail('Los campos custom deben ser un objeto JSON (no un valor escalar).');
                }
            }],
            'is_active' => ['boolean'],
            'position' => ['integer', 'min:0'],
        ];
    }

    public function save(): Service
    {
        $this->validate();

        $customFields = blank($this->customFieldsJson) ? null : json_decode($this->customFieldsJson, true);

        $attributes = [
            'category_id' => $this->category_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'custom_fields' => $customFields,
            'is_active' => $this->is_active,
            'position' => $this->position,
        ];

        if ($this->id) {
            $service = Service::findOrFail($this->id);
            $service->update($attributes);
        } else {
            $service = Service::create($attributes);
            $this->id = $service->id;
        }

        $service->additionalCategories()->sync($this->additionalCategoryIdsToSync());

        return $service;
    }

    /**
     * Saves only the name, from the heading of the edit screen.
     */
    public function rename(): void
    {
        $this->validateOnly('name');

        Service::findOrFail($this->id)->update(['name' => $this->name]);
    }

    /**
     * The extra categories to persist, never duplicating the primary `category_id`.
     *
     * @return array<int, int>
     */
    protected function additionalCategoryIdsToSync(): array
    {
        return array_values(array_filter(
            array_unique($this->additional_category_ids),
            fn (int $id): bool => $id !== $this->category_id,
        ));
    }
}
