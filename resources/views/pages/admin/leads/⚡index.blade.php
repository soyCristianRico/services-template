<?php

declare(strict_types=1);

use App\Enums\LeadChannel;
use App\Enums\LeadStatus;
use App\Models\Landing;
use App\Models\Lead;
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
#[Title('Contactos')]
class extends Component
{
    use WithPagination;

    /** @var list<string> */
    protected const SORTABLE = ['name', 'status', 'created_at'];

    #[Url(as: 's')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $statusFilter = 'all';

    #[Url(as: 'landing')]
    public ?int $landingId = null;

    #[Url(as: 'canal')]
    public string $channelFilter = 'all';

    /** Empty keeps the team's own order: newest first. */
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

    /**
     * @return Collection<int, Landing>
     */
    #[Computed]
    public function landings(): Collection
    {
        return Landing::orderBy('slug')->get(['id', 'slug']);
    }

    #[Computed]
    public function leads(): LengthAwarePaginator
    {
        $direction = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        return Lead::query()
            ->with('landing:id,slug')
            ->when($this->search !== '', fn ($q) => $q->where(function ($q) {
                $q->where('email', 'like', "%{$this->search}%")
                    ->orWhere('name', 'like', "%{$this->search}%")
                    ->orWhere('phone', 'like', "%{$this->search}%");
            }))
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->landingId, fn ($q) => $q->where('landing_id', $this->landingId))
            ->when($this->channelFilter === 'none', fn ($q) => $q->whereNull('channel'))
            ->when(
                LeadChannel::tryFrom($this->channelFilter) !== null,
                fn ($q) => $q->where('channel', $this->channelFilter)
            )
            ->when(
                $this->sortBy === 'status',
                fn ($q) => $q->orderByRaw($this->statusWorkflowOrder().' '.$direction),
            )
            ->when(
                in_array($this->sortBy, ['name', 'created_at'], true),
                fn ($q) => $q->orderBy($this->sortBy, $direction),
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25);
    }

    /** @return array<string, string> */
    #[Computed]
    public function statuses(): array
    {
        return collect(LeadStatus::cases())->mapWithKeys(fn (LeadStatus $s) => [$s->value => $s->label()])->all();
    }

    /**
     * «Sin canal» is not one more channel: it groups the leads born outside a visit.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function channels(): array
    {
        return collect(LeadChannel::cases())
            ->mapWithKeys(fn (LeadChannel $c) => [$c->value => $c->label()])
            ->put('none', 'Sin canal')
            ->all();
    }

    /**
     * @return Collection<int, array{key: string, label: string}>
     */
    #[Computed]
    public function chips(): Collection
    {
        return collect([
            $this->statusFilter !== 'all' ? ['key' => 'statusFilter', 'label' => (string) ($this->statuses[$this->statusFilter] ?? '')] : null,
            $this->channelFilter !== 'all' ? ['key' => 'channelFilter', 'label' => (string) ($this->channels[$this->channelFilter] ?? '')] : null,
            $this->landingId ? ['key' => 'landingId', 'label' => '/'.$this->landings->firstWhere('id', $this->landingId)?->slug] : null,
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
            'statusFilter' => $this->statusFilter = 'all',
            'channelFilter' => $this->channelFilter = 'all',
            'landingId' => $this->landingId = null,
            default => null,
        };

        $this->resetPage();
    }

    public function clearAll(): void
    {
        $this->reset('search', 'statusFilter', 'channelFilter', 'landingId', 'sortBy', 'sortDirection');
        $this->resetPage();
    }

    /**
     * A status sorts in the order a lead walks through it, not alphabetically.
     */
    protected function statusWorkflowOrder(): string
    {
        $cases = collect(LeadStatus::cases())
            ->map(fn (LeadStatus $status, int $position): string => "WHEN '{$status->value}' THEN {$position}")
            ->implode(' ');

        return "CASE status {$cases} END";
    }
};
?>

<div class="space-y-6 p-8">
    <x-admin.page-header>Contactos</x-admin.page-header>

    <x-admin.filter-bar :chips="$this->chips" reset="clearAll" clear-all="clearAll">
        <x-admin.search-input wire:model.live.debounce.300ms="search" placeholder="Buscar por email, nombre o teléfono" clearable />

        <x-slot:advanced>
            <flux:select variant="listbox" wire:model.live="statusFilter" label="Estado">
                <flux:select.option value="all">Todos</flux:select.option>
                @foreach ($this->statuses as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select variant="listbox" wire:model.live="channelFilter" label="Canal">
                <flux:select.option value="all">Todos</flux:select.option>
                @foreach ($this->channels as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select variant="listbox" searchable clearable wire:model.live="landingId" label="Landing" placeholder="Todas">
                @foreach ($this->landings as $landing)
                    <flux:select.option :value="$landing->id">/{{ $landing->slug }}</flux:select.option>
                @endforeach
            </flux:select>
        </x-slot:advanced>
    </x-admin.filter-bar>

    <flux:table>
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">Nombre</flux:table.column>
            <flux:table.column>Email</flux:table.column>
            <flux:table.column>Teléfono</flux:table.column>
            <flux:table.column>Landing</flux:table.column>
            <flux:table.column>Canal</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')">Estado</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection" wire:click="sort('created_at')">Fecha</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->leads as $lead)
                <flux:table.row wire:key="lead-{{ $lead->id }}">
                    <flux:table.cell class="max-w-64">
                        <x-admin.record-link :href="route('admin.leads.show', $lead)" class="block truncate">{{ $lead->name }}</x-admin.record-link>
                    </flux:table.cell>
                    <flux:table.cell class="max-w-64 truncate">
                        <a href="mailto:{{ $lead->email }}" class="hover:underline">{{ $lead->email }}</a>
                    </flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">{{ $lead->phone ?? '—' }}</flux:table.cell>
                    <flux:table.cell class="max-w-48 truncate">
                        @if ($lead->landing)
                            <a href="{{ url('/'.$lead->landing->slug) }}" target="_blank" class="hover:underline">/{{ $lead->landing->slug }}</a>
                        @else
                            <span class="text-zinc-400">—</span>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($lead->channel)
                            <flux:badge size="sm" :color="$lead->channel->color()">{{ $lead->channel->label() }}</flux:badge>
                        @else
                            <span class="text-zinc-400">—</span>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$lead->status->color()">{{ $lead->status->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap text-zinc-500">{{ $lead->created_at?->format('d/m/Y H:i') }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="text-center text-zinc-500">No hay contactos con esos filtros.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{ $this->leads->links() }}
</div>
