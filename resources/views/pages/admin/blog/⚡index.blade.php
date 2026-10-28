<?php

declare(strict_types=1);

use App\Models\BlogPost;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Layout('layouts.admin')]
#[Title('Blog')]
class extends Component
{
    use WithPagination;

    /** @var list<string> */
    protected const SORTABLE = ['title', 'published_at'];

    /** @var array<string, string> */
    protected const STATUSES = [
        'published' => 'Publicados',
        'draft' => 'Borradores',
        'scheduled' => 'Programados',
    ];

    #[Url(as: 's')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $status = 'all';

    /** Empty keeps the most recently touched posts first. */
    #[Url(as: 'orden')]
    public string $sortBy = '';

    #[Url(as: 'sentido')]
    public string $sortDirection = 'asc';

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    #[Computed]
    public function posts(): LengthAwarePaginator
    {
        $direction = $this->sortDirection === 'desc' ? 'desc' : 'asc';

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
            ->when(
                in_array($this->sortBy, self::SORTABLE, true),
                fn ($q) => $q->orderBy($this->sortBy, $direction),
            )
            ->orderByDesc('updated_at')
            ->paginate(25);
    }

    /** @return array<string, string> */
    #[Computed]
    public function statuses(): array
    {
        return self::STATUSES;
    }

    /**
     * @return Collection<int, array{key: string, label: string}>
     */
    #[Computed]
    public function chips(): Collection
    {
        return collect([
            isset(self::STATUSES[$this->status]) ? ['key' => 'status', 'label' => self::STATUSES[$this->status]] : null,
        ])->filter()->values();
    }

    /**
     * What the public sees of a post, derived from its activation and its date.
     *
     * @return array{label: string, color: string}
     */
    public function stateOf(BlogPost $post): array
    {
        return match (true) {
            ! $post->is_active => ['label' => 'Inactivo', 'color' => 'zinc'],
            $post->published_at === null => ['label' => 'Borrador', 'color' => 'amber'],
            $post->published_at->isFuture() => ['label' => 'Programado', 'color' => 'blue'],
            default => ['label' => 'Publicado', 'color' => 'green'],
        };
    }

    public function sort(string $column): void
    {
        if (! in_array($column, self::SORTABLE, true)) {
            return;
        }

        $this->sortDirection = $this->sortBy === $column && $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->sortBy = $column;
        $this->resetPage();
    }

    public function clearFilter(string $key): void
    {
        if ($key === 'status') {
            $this->status = 'all';
        }

        $this->resetPage();
    }

    public function clearAll(): void
    {
        $this->reset('search', 'status', 'sortBy', 'sortDirection');
        $this->resetPage();
    }
};
?>

<div class="space-y-6 p-8">
    <x-admin.page-header>
        Blog

        <x-slot:actions>
            <flux:button :href="route('admin.blog.create')" variant="primary" icon="plus">Nuevo artículo</flux:button>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar :chips="$this->chips" reset="clearAll" clear-all="clearAll">
        <x-admin.search-input wire:model.live.debounce.300ms="search" placeholder="Buscar por título o slug" clearable />

        <x-slot:advanced>
            <flux:select variant="listbox" wire:model.live="status" label="Estado">
                <flux:select.option value="all">Todos</flux:select.option>
                @foreach ($this->statuses as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </x-slot:advanced>
    </x-admin.filter-bar>

    <flux:table>
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'title'" :direction="$sortDirection" wire:click="sort('title')">Título</flux:table.column>
            <flux:table.column>URL</flux:table.column>
            <flux:table.column>Estado</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'published_at'" :direction="$sortDirection" wire:click="sort('published_at')">Publicación</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->posts as $post)
                <flux:table.row wire:key="post-{{ $post->id }}">
                    <flux:table.cell class="max-w-96">
                        <x-admin.record-link :href="route('admin.blog.edit', $post)" class="block truncate">{{ $post->title }}</x-admin.record-link>
                    </flux:table.cell>
                    <flux:table.cell class="max-w-64 truncate">
                        <a href="{{ url('/blog/'.$post->slug) }}" target="_blank" class="text-blue-600 hover:underline">/blog/{{ $post->slug }}</a>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$this->stateOf($post)['color']">{{ $this->stateOf($post)['label'] }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap text-zinc-500">{{ $post->published_at?->format('d/m/Y H:i') ?? '—' }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4" class="text-center text-zinc-500">No hay artículos con esos filtros.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{ $this->posts->links() }}
</div>
