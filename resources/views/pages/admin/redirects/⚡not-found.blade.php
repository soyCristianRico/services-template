<?php

declare(strict_types=1);

use App\Models\NotFoundLog;
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

    #[Url]
    public string $search = '';

    #[Url]
    public bool $showResolved = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedShowResolved(): void
    {
        $this->resetPage();
    }

    /**
     * Dismiss an address without redirecting it: plenty of 404s are a mistyped
     * link from somewhere we do not control, and they should stop nagging.
     */
    public function dismiss(int $id): void
    {
        NotFoundLog::findOrFail($id)->update(['resolved_at' => now()]);
    }

    public function restore(int $id): void
    {
        NotFoundLog::findOrFail($id)->update(['resolved_at' => null]);
    }

    /**
     * @return \Illuminate\Pagination\LengthAwarePaginator<int, NotFoundLog>
     */
    #[Computed]
    public function logs(): \Illuminate\Pagination\LengthAwarePaginator
    {
        return NotFoundLog::query()
            ->when($this->search !== '', fn ($query) => $query->where('path', 'like', "%{$this->search}%"))
            ->unless($this->showResolved, fn ($query) => $query->pending())
            ->orderByDesc('hits')
            ->orderByDesc('last_seen_at')
            ->paginate(30);
    }
};
?>

<div class="space-y-6 p-8">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">Direcciones que no existen</flux:heading>
        <flux:button :href="route('admin.redirects.index')" variant="ghost" icon="arrow-left">Volver</flux:button>
    </div>

    <flux:text class="text-zinc-600">
        Cada línea es una dirección a la que ha llegado alguien y que devolvió un error. Ordenadas por número de
        visitas: las de arriba son las que más daño hacen. Si tienen sustituta, crea la redirección; si no venían
        de ningún sitio real, descártalas y dejan de aparecer.
    </flux:text>

    <div class="flex flex-wrap items-center gap-4">
        <flux:input wire:model.live.debounce.300ms="search" type="search" placeholder="Buscar dirección…" icon="magnifying-glass" class="max-w-md" />

        <flux:field variant="inline">
            <flux:switch wire:model.live="showResolved" />
            <flux:label>Ver también las ya resueltas</flux:label>
        </flux:field>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Dirección</flux:table.column>
            <flux:table.column>Visitas</flux:table.column>
            <flux:table.column>Última vez</flux:table.column>
            <flux:table.column>Venía de</flux:table.column>
            <flux:table.column>Acciones</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->logs as $log)
                <flux:table.row wire:key="log-{{ $log->id }}">
                    <flux:table.cell class="font-mono text-xs">
                        {{ $log->path }}
                        @if ($log->resolved_at)
                            <flux:badge size="sm" color="zinc">resuelta</flux:badge>
                        @endif
                    </flux:table.cell>
                    {{-- Formato europeo a mano: el helper formatNumber que documenta el
                         CLAUDE.md viene del template base y este proyecto no lo tiene. --}}
                    <flux:table.cell>{{ number_format($log->hits, 0, ',', '.') }}</flux:table.cell>
                    <flux:table.cell>{{ $log->last_seen_at?->format('d/m/Y H:i') }}</flux:table.cell>
                    <flux:table.cell class="max-w-xs truncate text-xs text-zinc-500" title="{{ $log->last_referrer }}">
                        {{ $log->last_referrer ?? '—' }}
                    </flux:table.cell>
                    <flux:table.cell class="flex gap-2">
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
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="text-center text-zinc-500">
                        {{ $showResolved ? 'No hay nada registrado.' : 'Ninguna dirección pendiente. Todo lo que se visita existe.' }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{ $this->logs->links() }}
</div>
