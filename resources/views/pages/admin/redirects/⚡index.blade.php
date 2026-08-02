<?php

declare(strict_types=1);

use App\Enums\RedirectMatchType;
use App\Enums\RedirectStatusCode;
use App\Models\NotFoundLog;
use App\Models\Redirect;
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
    public string $type = '';

    #[Url]
    public string $state = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedType(): void
    {
        $this->resetPage();
    }

    public function updatedState(): void
    {
        $this->resetPage();
    }

    public function toggleActive(int $id): void
    {
        $redirect = Redirect::findOrFail($id);
        $redirect->update(['is_active' => ! $redirect->is_active]);
    }

    public function deleteRedirect(int $id): void
    {
        Redirect::findOrFail($id)->delete();

        session()->flash('status', 'Redirección eliminada.');
    }

    /**
     * @return \Illuminate\Pagination\LengthAwarePaginator<int, Redirect>
     */
    #[Computed]
    public function redirects(): \Illuminate\Pagination\LengthAwarePaginator
    {
        return Redirect::query()
            ->when($this->search !== '', fn ($query) => $query->where(function ($query): void {
                $query->where('source', 'like', "%{$this->search}%")
                    ->orWhere('destination', 'like', "%{$this->search}%")
                    ->orWhere('notes', 'like', "%{$this->search}%");
            }))
            ->when($this->type !== '', fn ($query) => $query->where('match_type', $this->type))
            ->when($this->state === 'active', fn ($query) => $query->where('is_active', true))
            ->when($this->state === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($this->state === 'unused', fn ($query) => $query->where('hits', 0))
            ->orderByDesc('hits')
            ->orderByDesc('id')
            ->paginate(30);
    }

    #[Computed]
    public function pendingNotFound(): int
    {
        return NotFoundLog::query()->pending()->count();
    }
};
?>

<div class="space-y-6 p-8">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">Redirecciones</flux:heading>
        <div class="flex gap-3">
            <flux:button :href="route('admin.redirects.import')" variant="ghost" icon="arrow-up-tray">Importar lista</flux:button>
            <flux:button :href="route('admin.redirects.create')" variant="primary" icon="plus">Nueva redirección</flux:button>
        </div>
    </div>

    <flux:text class="text-zinc-600">
        Envían una dirección antigua a su sustituta. Se usan cuando cambia una URL, cuando se retira una página
        o cuando una campaña vieja sigue enviando visitas. Sin ellas, esas direcciones devuelven un 404 y se
        pierde el posicionamiento que tuvieran.
    </flux:text>

    @session('status')
        <flux:callout icon="check-circle" color="green">{{ $value }}</flux:callout>
    @endsession

    @if ($this->pendingNotFound > 0)
        <flux:callout icon="exclamation-triangle" color="amber">
            Hay {{ $this->pendingNotFound }} {{ $this->pendingNotFound === 1 ? 'dirección visitada que no existe' : 'direcciones visitadas que no existen' }}.
            Alguien ha llegado a {{ $this->pendingNotFound === 1 ? 'ella' : 'ellas' }} y se ha encontrado un error.
            <a href="{{ route('admin.redirects.not-found') }}" class="font-medium underline">Revisar la lista</a>.
        </flux:callout>
    @endif

    <div class="flex flex-wrap items-end gap-3">
        <flux:input wire:model.live.debounce.300ms="search" type="search" placeholder="Buscar por origen, destino o nota…" icon="magnifying-glass" class="max-w-md" />

        <flux:select wire:model.live="type" placeholder="Todos los tipos" class="max-w-48">
            <flux:select.option value="">Todos los tipos</flux:select.option>
            @foreach (RedirectMatchType::cases() as $case)
                <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="state" placeholder="Todas" class="max-w-48">
            <flux:select.option value="">Todas</flux:select.option>
            <flux:select.option value="active">Activas</flux:select.option>
            <flux:select.option value="inactive">Desactivadas</flux:select.option>
            <flux:select.option value="unused">Sin usar nunca</flux:select.option>
        </flux:select>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Dirección antigua</flux:table.column>
            <flux:table.column>Va a</flux:table.column>
            <flux:table.column>Tipo</flux:table.column>
            <flux:table.column>Código</flux:table.column>
            <flux:table.column>Usos</flux:table.column>
            <flux:table.column>Activa</flux:table.column>
            <flux:table.column>Acciones</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->redirects as $redirect)
                <flux:table.row wire:key="redirect-{{ $redirect->id }}">
                    <flux:table.cell class="font-mono text-xs">{{ $redirect->source }}</flux:table.cell>
                    <flux:table.cell class="font-mono text-xs">
                        {{ $redirect->destination ?? '—' }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$redirect->match_type === RedirectMatchType::Regex ? 'purple' : 'zinc'">
                            {{ $redirect->match_type->label() }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$redirect->status_code === RedirectStatusCode::MovedPermanently ? 'green' : 'amber'">
                            {{ $redirect->status_code->value }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($redirect->hits > 0)
                            {{-- Formato europeo a mano: el helper formatNumber que documenta el
                                 CLAUDE.md viene del template base y este proyecto no lo tiene. --}}
                            <span title="Última vez: {{ $redirect->last_hit_at?->format('d/m/Y H:i') }}">
                                {{ number_format($redirect->hits, 0, ',', '.') }}
                            </span>
                        @else
                            <span class="text-zinc-400">—</span>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:switch wire:click="toggleActive({{ $redirect->id }})" :checked="$redirect->is_active" />
                    </flux:table.cell>
                    <flux:table.cell class="flex gap-2">
                        <flux:button :href="route('admin.redirects.edit', $redirect)" size="xs" variant="ghost" icon="pencil-square" />
                        <flux:button
                            wire:click="deleteRedirect({{ $redirect->id }})"
                            wire:confirm="¿Eliminar la redirección de {{ $redirect->source }}? Esa dirección volverá a devolver un error 404."
                            size="xs" variant="ghost" icon="trash" />
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="text-center text-zinc-500">
                        No hay redirecciones.
                        <a href="{{ route('admin.redirects.create') }}" class="underline">Crea la primera</a>
                        o <a href="{{ route('admin.redirects.import') }}" class="underline">importa una lista</a>.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{ $this->redirects->links() }}
</div>
