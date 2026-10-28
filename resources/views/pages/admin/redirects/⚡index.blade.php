<?php

declare(strict_types=1);

use App\Enums\RedirectMatchType;
use App\Enums\RedirectStatusCode;
use App\Models\NotFoundLog;
use App\Models\Redirect;
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
#[Title('Redirecciones')]
class extends Component
{
    use WithPagination;

    /** @var list<string> */
    protected const SORTABLE = ['source', 'status_code', 'hits'];

    /** @var array<string, string> */
    protected const STATES = [
        'active' => 'Activas',
        'inactive' => 'Desactivadas',
        'unused' => 'Sin usar nunca',
    ];

    #[Url]
    public string $search = '';

    #[Url]
    public string $type = '';

    #[Url]
    public string $code = '';

    #[Url]
    public string $state = '';

    /** Empty keeps the default order: the most used first. */
    #[Url(as: 'orden')]
    public string $sortBy = '';

    #[Url(as: 'sentido')]
    public string $sortDirection = 'asc';

    public function mount(): void
    {
        if (session()->has('status')) {
            Flux::toast(text: session('status'), variant: 'success');
        }
    }

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function toggleActive(int $id): void
    {
        $redirect = Redirect::findOrFail($id);
        $redirect->update(['is_active' => ! $redirect->is_active]);

        Flux::toast(
            text: $redirect->is_active ? 'Redirección activada.' : 'Redirección desactivada.',
            variant: 'success',
        );
    }

    /**
     * @return LengthAwarePaginator<int, Redirect>
     */
    #[Computed]
    public function redirects(): LengthAwarePaginator
    {
        $direction = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        return Redirect::query()
            ->when($this->search !== '', fn ($query) => $query->where(function ($query): void {
                $query->where('source', 'like', "%{$this->search}%")
                    ->orWhere('destination', 'like', "%{$this->search}%")
                    ->orWhere('notes', 'like', "%{$this->search}%");
            }))
            ->when($this->type !== '', fn ($query) => $query->where('match_type', $this->type))
            ->when($this->code !== '', fn ($query) => $query->where('status_code', (int) $this->code))
            ->when($this->state === 'active', fn ($query) => $query->where('is_active', true))
            ->when($this->state === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($this->state === 'unused', fn ($query) => $query->where('hits', 0))
            ->when(
                in_array($this->sortBy, self::SORTABLE, true),
                fn ($query) => $query->orderBy($this->sortBy, $direction),
            )
            ->orderByDesc('hits')
            ->orderByDesc('id')
            ->paginate(30);
    }

    #[Computed]
    public function pendingNotFound(): int
    {
        return NotFoundLog::query()->pending()->count();
    }

    /** @return array<string, string> */
    #[Computed]
    public function states(): array
    {
        return self::STATES;
    }

    /**
     * @return Collection<int, array{key: string, label: string}>
     */
    #[Computed]
    public function chips(): Collection
    {
        return collect([
            $this->type !== '' ? ['key' => 'type', 'label' => (string) RedirectMatchType::tryFrom($this->type)?->label()] : null,
            $this->code !== '' ? ['key' => 'code', 'label' => (string) RedirectStatusCode::tryFrom((int) $this->code)?->label()] : null,
            $this->state !== '' ? ['key' => 'state', 'label' => self::STATES[$this->state] ?? ''] : null,
        ])->filter(fn ($chip) => $chip !== null && $chip['label'] !== '')->values();
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
        match ($key) {
            'type' => $this->type = '',
            'code' => $this->code = '',
            'state' => $this->state = '',
            default => null,
        };

        $this->resetPage();
    }

    public function clearAll(): void
    {
        $this->reset('search', 'type', 'code', 'state', 'sortBy', 'sortDirection');
        $this->resetPage();
    }
};
?>

<div class="space-y-6 p-8">
    <x-admin.page-header>
        Redirecciones

        <x-slot:actions>
            <flux:button :href="route('admin.redirects.import')" variant="ghost" icon="arrow-up-tray">Importar lista</flux:button>
            <flux:button :href="route('admin.redirects.create')" variant="primary" icon="plus">Nueva redirección</flux:button>
        </x-slot:actions>
    </x-admin.page-header>

    <flux:text class="max-w-3xl text-zinc-600">
        Envían una dirección antigua a su sustituta. Se usan cuando cambia una URL, cuando se retira una página
        o cuando una campaña vieja sigue enviando visitas. Sin ellas, esas direcciones devuelven un 404 y se
        pierde el posicionamiento que tuvieran.
    </flux:text>

    @if ($this->pendingNotFound > 0)
        <flux:callout icon="exclamation-triangle" color="amber" inline>
            <flux:callout.heading>
                Hay {{ $this->pendingNotFound }} {{ $this->pendingNotFound === 1 ? 'dirección visitada que no existe' : 'direcciones visitadas que no existen' }}
            </flux:callout.heading>
            <flux:callout.text>
                Alguien ha llegado a {{ $this->pendingNotFound === 1 ? 'ella' : 'ellas' }} y se ha encontrado un error.
            </flux:callout.text>

            <x-slot:actions>
                <flux:button :href="route('admin.redirects.not-found')" size="sm">Revisar la lista</flux:button>
            </x-slot:actions>
        </flux:callout>
    @endif

    <x-admin.filter-bar :chips="$this->chips" reset="clearAll" clear-all="clearAll">
        <x-admin.search-input wire:model.live.debounce.300ms="search" placeholder="Buscar por origen, destino o nota" clearable />

        <x-slot:advanced>
            <flux:select variant="listbox" clearable wire:model.live="type" label="Tipo" placeholder="Todos">
                @foreach (RedirectMatchType::cases() as $case)
                    <flux:select.option :value="$case->value">{{ $case->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select variant="listbox" clearable wire:model.live="code" label="Código" placeholder="Todos">
                @foreach (RedirectStatusCode::cases() as $case)
                    <flux:select.option :value="$case->value">{{ $case->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select variant="listbox" clearable wire:model.live="state" label="Estado" placeholder="Todas">
                @foreach ($this->states as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </x-slot:advanced>
    </x-admin.filter-bar>

    <flux:table>
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'source'" :direction="$sortDirection" wire:click="sort('source')">Dirección antigua</flux:table.column>
            <flux:table.column>Va a</flux:table.column>
            <flux:table.column>Tipo</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'status_code'" :direction="$sortDirection" wire:click="sort('status_code')">Código</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'hits'" :direction="$sortDirection" wire:click="sort('hits')">Usos</flux:table.column>
            <flux:table.column>Activa</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->redirects as $redirect)
                <flux:table.row wire:key="redirect-{{ $redirect->id }}">
                    <flux:table.cell class="max-w-96 font-mono text-xs">
                        <x-admin.record-link :href="route('admin.redirects.edit', $redirect)" class="block truncate" title="{{ $redirect->source }}">
                            {{ $redirect->source }}
                        </x-admin.record-link>
                    </flux:table.cell>
                    <flux:table.cell class="max-w-80 truncate font-mono text-xs" title="{{ $redirect->destination }}">
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
                    <flux:table.cell class="whitespace-nowrap">
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
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center text-zinc-500">
                        @if ($search !== '' || $this->chips->isNotEmpty())
                            No hay redirecciones con esos filtros.
                        @else
                            No hay redirecciones.
                            <a href="{{ route('admin.redirects.create') }}" class="underline">Crea la primera</a>
                            o <a href="{{ route('admin.redirects.import') }}" class="underline">importa una lista</a>.
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{ $this->redirects->links() }}
</div>
