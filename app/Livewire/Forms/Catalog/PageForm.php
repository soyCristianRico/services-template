<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Models\Page;
use Illuminate\Validation\Rule;
use Livewire\Form;

class PageForm extends Form
{
    public ?int $id = null;

    public string $slug = '';

    public string $title = '';

    public ?string $body = null;

    public ?string $meta_title = null;

    public ?string $meta_description = null;

    public bool $is_active = true;

    public function setPage(Page $page): void
    {
        $this->id = $page->id;
        $this->slug = $page->slug;
        $this->title = $page->title;
        $this->body = $page->body;
        $this->meta_title = $page->meta_title;
        $this->meta_description = $page->meta_description;
        $this->is_active = $page->is_active;
    }

    public function rules(): array
    {
        return [
            'slug' => [
                'required',
                'string',
                'max:200',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('pages', 'slug')->ignore($this->id),
            ],
            'title' => ['required', 'string', 'max:200'],
            'body' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:200'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Saves only the title, edited in the screen's own heading.
     */
    public function rename(): void
    {
        $this->validateOnly('title');

        Page::findOrFail($this->id)->update(['title' => $this->title]);
    }

    public function save(): Page
    {
        $this->validate();

        $attributes = [
            'slug' => $this->slug,
            'title' => $this->title,
            'body' => $this->body,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'is_active' => $this->is_active,
        ];

        if ($this->id) {
            $page = Page::findOrFail($this->id);
            $page->update($attributes);
        } else {
            $page = Page::create($attributes);
            $this->id = $page->id;
        }

        return $page;
    }
}
