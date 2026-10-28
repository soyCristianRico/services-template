<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Enums\LocationType;
use App\Models\Location;
use Illuminate\Validation\Rule;
use Livewire\Form;

class LocationForm extends Form
{
    public ?int $id = null;

    public string $name = '';

    public string $slug = '';

    public LocationType $type = LocationType::City;

    public ?int $parent_id = null;

    public ?int $population = null;

    public ?string $latitude = null;

    public ?string $longitude = null;

    public ?string $meta_title = null;

    public ?string $meta_description = null;

    public function setLocation(Location $location): void
    {
        $this->id = $location->id;
        $this->name = $location->name;
        $this->slug = $location->slug;
        $this->type = $location->type;
        $this->parent_id = $location->parent_id;
        $this->population = $location->population;
        $this->latitude = $location->latitude !== null ? (string) $location->latitude : null;
        $this->longitude = $location->longitude !== null ? (string) $location->longitude : null;
        $this->meta_title = $location->meta_title;
        $this->meta_description = $location->meta_description;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'required',
                'string',
                'max:160',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('locations', 'slug')->ignore($this->id),
            ],
            'type' => ['required', Rule::enum(LocationType::class)],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('locations', 'id')->where(fn ($q) => $this->id ? $q->where('id', '!=', $this->id) : $q),
            ],
            'population' => ['nullable', 'integer', 'min:0', 'max:99999999'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'meta_title' => ['nullable', 'string', 'max:160'],
            'meta_description' => ['nullable', 'string', 'max:320'],
        ];
    }

    public function save(): Location
    {
        $this->validate();

        $attributes = [
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type,
            'parent_id' => $this->parent_id,
            'population' => $this->population,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
        ];

        if ($this->id) {
            $location = Location::findOrFail($this->id);
            $location->update($attributes);
        } else {
            $location = Location::create($attributes);
            $this->id = $location->id;
        }

        return $location;
    }

    /**
     * Saves only the name, edited in the title of the record.
     */
    public function rename(): void
    {
        $this->validateOnly('name');

        Location::findOrFail($this->id)->update(['name' => $this->name]);
    }
}
