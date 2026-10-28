<?php

declare(strict_types=1);

use App\Models\NotFoundLog;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Layout('layouts.admin')]
#[Title('Direcciones rotas')]
class extends Component
{
    use WithPagination;

    /** @var list<string> */
    protected const SORTABLE = ['path', 'hits', 'last_seen_at'];

    #[Url]
    public string $search = '';

    #[Url]
    public bool $showResolved = false;

    /** Empty keeps the work-queue order: the most visited first. */
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

    /**
     * Dismiss an address without redirecting it: plenty of 404s are a mistyped
     * link from somewhere we do not control, and they should stop nagging.
     */
    public function dismiss(int $id): void
    {
        NotFoundLog::findOrFail($id)->update(['resolved_at' => now()]);

        Flux::toast(text: 'Dirección descartada.', variant: 'success');
    }

    public function restore(int $id): void
    {
        NotFoundLog::findOrFail($id)->update(['resolved_at' => null]);

        Flux::toast(text: 'Dirección reabierta.', variant: 'success');
    }

    /**
     * @return LengthAwarePaginator<int, NotFoundLog>
     */
    #[Computed]
    public function logs(): LengthAwarePaginator
    {
        $direction = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        return NotFoundLog::query()
            ->when($this->search !== '', fn ($query) => $query->where('path', 'like', "%{$this->search}%"))
            ->unless($this->showResolved, fn ($query) => $query->pending())
            ->when(
                in_array($this->sortBy, self::SORTABLE, true),
                fn ($query) => $query->orderBy($this->sortBy, $direction),
            )
            ->orderByDesc('hits')
            ->orderByDesc('last_seen_at')
            ->paginate(30);
    }

    /**
     * @return Collection<int, array{key: string, label: string}>
     */
    #[Computed]
    public function chips(): Collection
    {
        return collect([
            $this->showResolved ? ['key' => 'showResolved', 'label' => 'Incluye las resueltas'] : null,
        ])->filter()->values();
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
        if ($key === 'showResolved') {
            $this->showResolved = false;
        }

        $this->resetPage();
    }

    public function clearAll(): void
    {
        $this->reset('search', 'showResolved', 'sortBy', 'sortDirection');
        $this->resetPage();
    }
};
?>

<div class="space-y-6 p-8">
    <x-admin.page-header>
        Direcciones rotas
    </x-admin.page-header>

    <flux:text class="max-w-3xl text-zinc-600">
        Cada línea es una dirección a la que ha llegado alguien y que devolvió un error. Ordenadas por número de
        visitas: las de arriba son las que más daño hacen. Si tienen sustituta, crea la redirección; si no venían
        de ningún sitio real, descártalas y dejan de aparecer.
    </flux:text>

    <x-admin.filter-bar :chips="$this->chips" reset="clearAll" clear-all="clearAll">
        <x-admin.search-input wire:model.live.debounce.300ms="search" placeholder="Buscar dirección" clearable />

        <x-slot:advanced>
            <flux:field>
                <flux:label>Ver también las ya resueltas</flux:label>
                <flux:description>Las que ya tienen redirección o se descartaron.</flux:description>
                <flux:switch wire:model.live="showResolved" />
            </flux:field>
        </x-slot:advanced>
    </x-admin.filter-bar>

    <flux:table>
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'path'" :direction="$sortDirection" wire:click="sort('path')">Dirección</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'hits'" :direction="$sortDirection" wire:click="sort('hits')">Visitas</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'last_seen_at'" :direction="$sortDirection" wire:click="sort('last_seen_at')">Última vez</flux:table.column>
            <flux:table.column>Venía de</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->logs as $log)
                <flux:table.row wire:key="log-{{ $log->id }}">
                    <flux:table.cell class="max-w-96">
                        <div class="flex items-center gap-2">
                            <span class="truncate font-mono text-xs" title="{{ $log->path }}">{{ $log->path }}</span>
                            @if ($log->resolved_at)
                                <flux:badge size="sm" color="zinc">resuelta</flux:badge>
                            @endif
                        </div>
                    </flux:table.cell>
                    {{-- Formato europeo a mano: el helper formatNumber que documenta el
                         CLAUDE.md viene del template base y este proyecto no lo tiene. --}}
                    <flux:table.cell class="whitespace-nowrap">{{ number_format($log->hits, 0, ',', '.') }}</flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap text-zinc-500">{{ $log->last_seen_at?->format('d/m/Y H:i') }}</flux:table.cell>
                    <flux:table.cell class="max-w-xs truncate text-xs text-zinc-500" title="{{ $log->last_referrer }}">
                        {{ $log->last_referrer ?? '—' }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end gap-2">
                            @if ($log->resolved_at)
                                <flux:button wire:click="restore({{ $log->id }})" size="xs" variant="ghost" icon="arrow-uturn-left">
                                    Reabrir
                                </flux:button>
                            @else
                                <flux:button
                                    :href="route('admin.redirects.create', ['source' => $log->path])"
                                    size="xs" variant="primary" icon="arrow-right">
                                    Redirigir
                                </flux:button>
                                <flux:button wire:click="dismiss({{ $log->id }})" size="xs" variant="ghost">
                                    Descartar
                                </flux:button>
                            @endif
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="text-center text-zinc-500">
                        @if ($search !== '')
                            Ninguna dirección coincide con la búsqueda.
                        @else
                            {{ $showResolved ? 'No hay nada registrado.' : 'Ninguna dirección pendiente. Todo lo que se visita existe.' }}
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{ $this->logs->links() }}
</div>
