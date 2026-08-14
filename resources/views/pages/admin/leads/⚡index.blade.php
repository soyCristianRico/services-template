<?php

declare(strict_types=1);

use App\Enums\LeadChannel;
use App\Enums\LeadStatus;
use App\Models\Landing;
use App\Models\Lead;
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
    public string $statusFilter = 'all';

    #[Url(as: 'landing')]
    public ?int $landingId = null;

    #[Url(as: 'canal')]
    public string $channelFilter = 'all';

    public function updating(): void
    {
        $this->resetPage();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Landing>
     */
    #[Computed]
    public function landings(): \Illuminate\Support\Collection
    {
        return Landing::orderBy('slug')->get(['id', 'slug']);
    }

    #[Computed]
    public function leads(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return Lead::query()
            ->with('landing:id,slug')
            ->when($this->search !== '', fn ($q) => $q->where(function ($q) {
                $q->where('email', 'like', "%{$this->search}%")
                    ->orWhere('name', 'like', "%{$this->search}%")
                    ->orWhere('phone', 'like', "%{$this->search}%");
            }))
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->landingId, fn ($q) => $q->where('landing_id', $this->landingId))
            // «sin-canal» son los que nacieron fuera de una visita.
            ->when($this->channelFilter === 'none', fn ($q) => $q->whereNull('channel'))
            ->when(
                LeadChannel::tryFrom($this->channelFilter) !== null,
                fn ($q) => $q->where('channel', $this->channelFilter)
            )
            ->orderByDesc('created_at')
            ->paginate(25);
    }
};
?>

<div class="space-y-6 p-8">
    <flux:heading size="xl">Leads</flux:heading>

    <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-4">
        <flux:input wire:model.live.debounce.300ms="search" type="search" placeholder="Buscar por email, nombre o teléfono…" icon="magnifying-glass" />
        <flux:select wire:model.live="statusFilter">
            <flux:select.option value="all">Todos los estados</flux:select.option>
            @foreach (LeadStatus::cases() as $status)
                <flux:select.option value="{{ $status->value }}">{{ $status->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="landingId" placeholder="Todas las landings">
            <flux:select.option value="">Todas las landings</flux:select.option>
            @foreach ($this->landings as $landing)
                <flux:select.option value="{{ $landing->id }}">/{{ $landing->slug }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="channelFilter">
            <flux:select.option value="all">Todos los canales</flux:select.option>
            @foreach (LeadChannel::cases() as $channel)
                <flux:select.option value="{{ $channel->value }}">{{ $channel->label() }}</flux:select.option>
            @endforeach
            <flux:select.option value="none">Sin canal</flux:select.option>
        </flux:select>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Fecha</flux:table.column>
            <flux:table.column>Nombre</flux:table.column>
            <flux:table.column>Email</flux:table.column>
            <flux:table.column>Teléfono</flux:table.column>
            <flux:table.column>Landing</flux:table.column>
            <flux:table.column>Canal</flux:table.column>
            <flux:table.column>Estado</flux:table.column>
            <flux:table.column>Acciones</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->leads as $lead)
                <flux:table.row wire:key="lead-{{ $lead->id }}">
                    <flux:table.cell>{{ $lead->created_at?->format('d/m/Y H:i') }}</flux:table.cell>
                    <flux:table.cell>{{ $lead->name }}</flux:table.cell>
                    <flux:table.cell>
                        <a href="mailto:{{ $lead->email }}" class="text-blue-600 hover:underline">{{ $lead->email }}</a>
                    </flux:table.cell>
                    <flux:table.cell>{{ $lead->phone ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($lead->landing)
                            <a href="{{ url('/'.$lead->landing->slug) }}" target="_blank" class="text-blue-600 hover:underline">
                                /{{ $lead->landing->slug }}
                            </a>
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
                        <flux:badge size="sm" :color="match ($lead->status) {
                            App\Enums\LeadStatus::New => 'blue',
                            App\Enums\LeadStatus::Contacted => 'amber',
                            App\Enums\LeadStatus::Qualified => 'green',
                            App\Enums\LeadStatus::Lost => 'red',
                        }">{{ $lead->status->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:button :href="route('admin.leads.show', $lead)" size="xs" variant="ghost" icon="eye" />
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="8" class="text-center text-zinc-500">
                        No hay leads con esos filtros.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{ $this->leads->links() }}
</div>
