<?php

declare(strict_types=1);

use App\Models\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new
#[Layout('layouts.admin')]
#[Title('Páginas')]
class extends Component
{
    /** @var list<string> */
    protected const SORTABLE = ['title', 'updated_at'];

    #[Url(as: 's')]
    public string $search = '';

    /** Empty keeps the pages in alphabetical order. */
    #[Url(as: 'orden')]
    public string $sortBy = '';

    #[Url(as: 'sentido')]
    public string $sortDirection = 'asc';

    public function toggleActive(int $id): void
    {
        $page = Page::findOrFail($id);
        $page->update(['is_active' => ! $page->is_active]);
    }

    /**
     * @return Collection<int, Page>
     */
    #[Computed]
    public function pages(): Collection
    {
        $direction = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        return Page::query()
            ->when($this->search !== '', fn ($q) => $q->where(function ($q) {
                $q->where('title', 'like', "%{$this->search}%")
                    ->orWhere('slug', 'like', "%{$this->search}%");
            }))
            ->when(
                in_array($this->sortBy, self::SORTABLE, true),
                fn ($q) => $q->orderBy($this->sortBy, $direction),
            )
            ->orderBy('title')
            ->get();
    }

    public function sort(string $column): void
    {
        if (! in_array($column, self::SORTABLE, true)) {
            return;
        }

        $this->sortDirection = $this->sortBy === $column && $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->sortBy = $column;
    }
};
?>

<div class="space-y-6 p-8">
    <x-admin.page-header>
        Páginas

        <x-slot:actions>
            <flux:button :href="route('admin.pages.create')" variant="primary" icon="plus">Nueva página</flux:button>
        </x-slot:actions>
    </x-admin.page-header>

    <flux:text>
        Las páginas de solo texto: aviso legal, privacidad, cookies, gracias… El resto de la web (portada,
        fichas, landings) tiene diseño propio y se cambia en el código: pídelo al equipo.
    </flux:text>

    <x-admin.search-input wire:model.live.debounce.300ms="search" placeholder="Buscar por título o slug" clearable />

    <flux:table>
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'title'" :direction="$sortDirection" wire:click="sort('title')">Título</flux:table.column>
            <flux:table.column>URL</flux:table.column>
            <flux:table.column>Activa</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'updated_at'" :direction="$sortDirection" wire:click="sort('updated_at')">Actualizada</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->pages as $page)
                <flux:table.row wire:key="page-{{ $page->id }}">
                    <flux:table.cell class="max-w-96">
                        <x-admin.record-link :href="route('admin.pages.edit', $page)" class="block truncate">{{ $page->title }}</x-admin.record-link>
                    </flux:table.cell>
                    <flux:table.cell class="max-w-64 truncate">
                        <a href="{{ url('/'.$page->slug) }}" target="_blank" class="text-blue-600 hover:underline">/{{ $page->slug }}</a>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:switch wire:click="toggleActive({{ $page->id }})" :checked="$page->is_active" aria-label="Activa" />
                    </flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap text-zinc-500">{{ $page->updated_at?->format('d/m/Y') }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4" class="text-center text-zinc-500">
                        @if ($search !== '')
                            No hay páginas con esa búsqueda.
                        @else
                            No hay páginas. <a href="{{ route('admin.pages.create') }}" class="underline">Crea la primera</a>.
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
