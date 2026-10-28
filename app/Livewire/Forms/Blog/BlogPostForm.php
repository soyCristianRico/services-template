<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Blog;

use App\Models\BlogPost;
use Illuminate\Validation\Rule;
use Livewire\Form;

class BlogPostForm extends Form
{
    public ?int $id = null;

    public string $title = '';

    public string $slug = '';

    public ?string $excerpt = null;

    public ?string $body = null;

    public ?string $meta_title = null;

    public ?string $meta_description = null;

    public ?string $author_name = null;

    public string $tagsCsv = '';

    public ?string $published_at = null;

    public bool $is_active = true;

    public function setPost(BlogPost $post): void
    {
        $this->id = $post->id;
        $this->title = $post->title;
        $this->slug = $post->slug;
        $this->excerpt = $post->excerpt;
        $this->body = $post->body;
        $this->meta_title = $post->meta_title;
        $this->meta_description = $post->meta_description;
        $this->author_name = $post->author_name;
        $this->tagsCsv = is_array($post->tags) ? implode(', ', $post->tags) : '';
        $this->published_at = $post->published_at?->format('Y-m-d\TH:i');
        $this->is_active = $post->is_active;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'slug' => [
                'required',
                'string',
                'max:200',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('blog_posts', 'slug')->ignore($this->id),
            ],
            'excerpt' => ['nullable', 'string', 'max:320'],
            'body' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:200'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'author_name' => ['nullable', 'string', 'max:160'],
            'tagsCsv' => ['nullable', 'string', 'max:500'],
            'published_at' => ['nullable', 'date'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Saves only the title, edited in the screen's own heading.
     */
    public function rename(): void
    {
        $this->validateOnly('title');

        BlogPost::findOrFail($this->id)->update(['title' => $this->title]);
    }

    public function save(): BlogPost
    {
        $this->validate();

        $tags = blank($this->tagsCsv)
            ? null
            : array_values(array_filter(array_map('trim', explode(',', $this->tagsCsv))));

        $attributes = [
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'body' => $this->body,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'author_name' => $this->author_name,
            'tags' => $tags,
            'published_at' => blank($this->published_at) ? null : $this->published_at,
            'is_active' => $this->is_active,
        ];

        if ($this->id) {
            $post = BlogPost::findOrFail($this->id);
            $post->update($attributes);
        } else {
            $post = BlogPost::create($attributes);
            $this->id = $post->id;
        }

        return $post;
    }
}
