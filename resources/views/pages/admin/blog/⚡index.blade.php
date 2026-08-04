<?php

declare(strict_types=1);

use App\Models\BlogPost;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Layout('layouts.admin')]
class extends Component
{
    use WithPagination;

    #[Url(as: 's')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $status = 'all'; // all | published | draft | scheduled

    public function updating(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function posts(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return BlogPost::query()
            ->when($this->search !== '', fn ($q) => $q->where(function ($q) {
                $q->where('title', 'like', "%{$this->search}%")
                    ->orWhere('slug', 'like', "%{$this->search}%");
            }))
            ->when($this->status === 'published', fn ($q) => $q->where('is_active', true)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now()))
            ->when($this->status === 'draft', fn ($q) => $q->whereNull('published_at'))
            ->when($this->status === 'scheduled', fn ($q) => $q->whereNotNull('published_at')
                ->where('published_at', '>', now()))
            ->orderByDesc('updated_at')
            ->paginate(25);
    }
};
?>

<div class="space-y-6 p-8">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">Blog</flux:heading>
        <flux:button :href="route('admin.blog.create')" variant="primary" icon="plus">Nuevo artículo</flux:button>
    </div>

    <div class="grid gap-3 md:grid-cols-2">
        <flux:input wire:model.live.debounce.300ms="search" type="search" placeholder="Buscar por título o slug…" icon="magnifying-glass" />
        <flux:select wire:model.live="status">
            <flux:select.option value="all">Todos</flux:select.option>
            <flux:select.option value="published">Publicados</flux:select.option>
            <flux:select.option value="draft">Borradores</flux:select.option>
            <flux:select.option value="scheduled">Programados</flux:select.option>
        </flux:select>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Título</flux:table.column>
            <flux:table.column>URL</flux:table.column>
            <flux:table.column>Estado</flux:table.column>
            <flux:table.column>Fecha</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->posts as $post)
                @php($state = match (true) {
                    ! $post->is_active => ['Inactivo', 'zinc'],
                    $post->published_at === null => ['Borrador', 'amber'],
                    $post->published_at->isFuture() => ['Programado', 'blue'],
                    default => ['Publicado', 'green'],
                })
                <flux:table.row wire:key="post-{{ $post->id }}">
                    <flux:table.cell>
                        <x-admin.record-link :href="route('admin.blog.edit', $post)">{{ $post->title }}</x-admin.record-link>
                    </flux:table.cell>
                    <flux:table.cell>
                        <a href="{{ url('/blog/'.$post->slug) }}" target="_blank" class="text-blue-600 hover:underline">
                            /blog/{{ $post->slug }}
                        </a>
                    </flux:table.cell>
                    <flux:table.cell><flux:badge size="sm" :color="$state[1]">{{ $state[0] }}</flux:badge></flux:table.cell>
                    <flux:table.cell>{{ $post->published_at?->format('d/m/Y H:i') ?? '—' }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4" class="text-center text-zinc-500">
                        No hay artículos con esos filtros.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{ $this->posts->links() }}
</div>
