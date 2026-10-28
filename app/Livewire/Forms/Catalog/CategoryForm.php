<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Models\Category;
use Illuminate\Validation\Rule;
use Livewire\Form;

class CategoryForm extends Form
{
    public ?int $id = null;

    public string $name = '';

    public string $slug = '';

    public ?int $parent_id = null;

    public ?string $icon = null;

    public int $position = 0;

    public ?string $meta_title = null;

    public ?string $meta_description = null;

    public function setCategory(Category $category): void
    {
        $this->id = $category->id;
        $this->name = $category->name;
        $this->slug = $category->slug;
        $this->parent_id = $category->parent_id;
        $this->icon = $category->icon;
        $this->position = $category->position ?? 0;
        $this->meta_title = $category->meta_title;
        $this->meta_description = $category->meta_description;
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
                Rule::unique('categories', 'slug')->ignore($this->id),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where(fn ($q) => $this->id ? $q->where('id', '!=', $this->id) : $q),
            ],
            'icon' => ['nullable', 'string', 'max:80'],
            'position' => ['required', 'integer', 'min:0'],
            'meta_title' => ['nullable', 'string', 'max:160'],
            'meta_description' => ['nullable', 'string', 'max:320'],
        ];
    }

    public function save(): Category
    {
        $this->validate();

        $attributes = [
            'name' => $this->name,
            'slug' => $this->slug,
            'parent_id' => $this->parent_id,
            'icon' => $this->icon,
            'position' => $this->position,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
        ];

        if ($this->id) {
            $category = Category::findOrFail($this->id);
            $category->update($attributes);
        } else {
            $category = Category::create($attributes);
            $this->id = $category->id;
        }

        return $category;
    }

    /**
     * Saves only the name, edited in the title of the record.
     */
    public function rename(): void
    {
        $this->validateOnly('name');

        Category::findOrFail($this->id)->update(['name' => $this->name]);
    }
}
