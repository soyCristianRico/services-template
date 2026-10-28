<?php

declare(strict_types=1);

namespace Tests\Support\Livewire;

use App\Livewire\Forms\Blog\BlogPostForm;
use App\Models\BlogPost;
use Livewire\Component;

/**
 * Host component so BlogPostForm can be exercised on its own.
 */
class BlogPostFormTestComponent extends Component
{
    public BlogPostForm $form;

    public function mount(?BlogPost $post = null): void
    {
        if ($post?->exists) {
            $this->form->setPost($post);
        }
    }

    public function save(): void
    {
        $this->form->save();
    }

    public function rename(): void
    {
        $this->form->rename();
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div></div>
        BLADE;
    }
}
