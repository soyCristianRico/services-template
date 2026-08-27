<?php

declare(strict_types=1);

use App\Models\BlogPost;
use App\Services\Seo\SeoService;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Layout('layouts.public')]
class extends Component
{
    use WithPagination;

    public function mount(SeoService $seo): void
    {
        $seo->setSEO(
            title: 'Blog',
            description: 'Guías, comparativas y consejos prácticos sobre lo que alquilamos.',
            // Don't expose an empty blog to search engines until it has content.
            index: BlogPost::hasPublished() ? null : false,
        );
    }

    /** @return LengthAwarePaginator<int, BlogPost> */
    #[Computed]
    public function posts(): LengthAwarePaginator
    {
        $paginator = BlogPost::published()->orderByDesc('published_at')->paginate(10);

        abort_if($paginator->currentPage() > 1 && $paginator->currentPage() > $paginator->lastPage(), 404);

        return $paginator;
    }
};
?>

<div class="mx-auto max-w-4xl px-6 py-16">
    <flux:heading level="1">Blog</flux:heading>
    <flux:text class="mt-2 text-zinc-600">Guías y novedades del sector.</flux:text>

    <div class="mt-12 space-y-8">
        @forelse ($this->posts as $post)
            <article class="border-b border-zinc-200 pb-8">
                <a href="{{ url('/blog/'.$post->slug) }}" class="block hover:opacity-90">
                    @if ($post->getFirstMediaUrl('hero'))
                        <img src="{{ $post->getFirstMediaUrl('hero') }}" alt="{{ $post->title }}"
                             class="mb-4 aspect-[16/9] w-full rounded-lg object-cover">
                    @endif
                    <flux:heading level="2">{{ $post->title }}</flux:heading>
                </a>
                <flux:text class="mt-2 text-sm text-zinc-500">
                    {{ $post->published_at?->format('d/m/Y') }}
                    @if ($post->author_name) · {{ $post->author_name }} @endif
                </flux:text>
                @if ($post->excerpt)
                    <flux:text class="mt-3">{{ $post->excerpt }}</flux:text>
                @endif
                <flux:link href="{{ url('/blog/'.$post->slug) }}" class="mt-3 inline-block">Leer →</flux:link>
            </article>
        @empty
            <flux:text class="text-zinc-500">Aún no hay artículos publicados.</flux:text>
        @endforelse
    </div>

    @if ($this->posts->hasPages())
        <div class="mt-12">{{ $this->posts->links() }}</div>
    @endif
</div>

@push('seo-links')
    <x-site.pagination-seo-links :paginator="$this->posts" />
@endpush
