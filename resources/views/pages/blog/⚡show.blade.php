<?php

declare(strict_types=1);

use App\Models\BlogPost;
use App\Services\Admin\AdminBar;
use App\Services\Seo\SeoService;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts.public')]
class extends Component
{
    public BlogPost $post;

    public function mount(string $slug, SeoService $seo, AdminBar $bar): void
    {
        $this->post = BlogPost::published()->where('slug', $slug)->firstOrFail();

        $bar->editing($this->post);

        $url = url('/blog/'.$this->post->slug);
        $heroImage = $this->post->getFirstMediaUrl('hero') ?: null;

        $seo->setSEO(
            title: $this->post->meta_title ?? $this->post->title,
            description: $this->post->meta_description ?? ($this->post->excerpt ?? Str::limit(strip_tags((string) $this->post->body), 160)),
            url: $url,
            image: $heroImage,
        );

        $seo->setArticleMeta(
            publishedAt: $this->post->published_at,
            modifiedAt: $this->post->updated_at,
            tags: $this->post->tags ?? [],
        );
    }
};
?>

<article class="mx-auto max-w-3xl px-6 py-16">
    <nav class="mb-8 text-sm text-zinc-500">
        <a href="{{ url('/') }}" class="hover:text-zinc-900">Inicio</a>
        <span class="mx-2">/</span>
        <a href="{{ url('/blog') }}" class="hover:text-zinc-900">Blog</a>
    </nav>

    @if ($post->getFirstMediaUrl('hero'))
        <img src="{{ $post->getFirstMediaUrl('hero') }}" alt="{{ $post->title }}"
             class="mb-8 aspect-[16/9] w-full rounded-lg object-cover">
    @endif

    <flux:heading level="1">{{ $post->title }}</flux:heading>

    <flux:text class="mt-3 text-sm text-zinc-500">
        {{ $post->published_at?->format('d/m/Y') }}
        @if ($post->author_name) · {{ $post->author_name }} @endif
    </flux:text>

    @if ($post->body)
        <div class="prose prose-zinc mt-8 max-w-none">
            {!! $post->body !!}
        </div>
    @endif

    @if (! empty($post->tags))
        <div class="mt-12 flex flex-wrap gap-2">
            @foreach ($post->tags as $tag)
                <flux:badge size="sm">{{ $tag }}</flux:badge>
            @endforeach
        </div>
    @endif
</article>
