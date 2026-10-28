<?php

declare(strict_types=1);

use App\Enums\LocationType;
use App\Models\Location;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new
#[Layout('layouts.admin')]
#[Title('Ubicaciones')]
class extends Component
{
    /** @var list<string> */
    protected const SORTABLE = ['name', 'population'];

    #[Url(as: 's')]
    public string $search = '';

    #[Url(as: 'tipo')]
    public string $type = '';

    /** Empty keeps the tree: each place under the one that contains it. */
    #[Url(as: 'orden')]
    public string $sortBy = '';

    #[Url(as: 'sentido')]
    public string $sortDirection = 'asc';

    /**
     * The tree, depth-first from each root. A search, a filter or a sort flattens it.
     *
     * @return array<int, array{location: Location, depth: int}>
     */
    #[Computed]
    public function tree(): array
    {
        if ($this->search !== '' || $this->type !== '' || $this->sortBy !== '') {
            return $this->flatList();
        }

        $byParent = Location::query()->orderBy('name')->get()->groupBy('parent_id');
        $rows = [];

        $walk = function (?int $parentId, int $depth) use (&$walk, $byParent, &$rows): void {
            foreach ($byParent->get((string) $parentId, collect()) as $node) {
                $rows[] = ['location' => $node, 'depth' => $depth];
                $walk($node->id, $depth + 1);
            }
        };

        $walk(null, 0);

        return $rows;
    }

    /**
     * @return list<LocationType>
     */
    public function types(): array
    {
        return LocationType::cases();
    }

    /**
     * @return Collection<int, array{key: string, label: string}>
     */
    #[Computed]
    public function chips(): Collection
    {
        return collect([
            $this->type !== '' ? ['key' => 'type', 'label' => (string) LocationType::tryFrom($this->type)?->label()] : null,
        ])->filter(fn ($chip) => $chip !== null && $chip['label'] !== '')->values();
    }

    public function sort(string $column): void
    {
        if (! in_array($column, self::SORTABLE, true)) {
            return;
        }

        $this->sortDirection = $this->sortBy === $column && $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->sortBy = $column;
    }

    public function clearFilter(string $key): void
    {
        if ($key === 'type') {
            $this->type = '';
        }
    }

    public function clearAll(): void
    {
        $this->reset('search', 'type', 'sortBy', 'sortDirection');
    }

    /**
     * @return array<int, array{location: Location, depth: int}>
     */
    protected function flatList(): array
    {
        $direction = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        return Location::query()
            ->when($this->search !== '', fn ($q) => $q->where(function ($q): void {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('slug', 'like', "%{$this->search}%");
            }))
            ->when(LocationType::tryFrom($this->type), fn ($q, LocationType $type) => $q->where('type', $type))
            ->when(
                in_array($this->sortBy, self::SORTABLE, true),
                fn ($q) => $q->orderBy($this->sortBy, $direction),
            )
            ->orderBy('name')
            ->get()
            ->map(fn (Location $location): array => ['location' => $location, 'depth' => 0])
            ->all();
    }
};
?>

<div class="space-y-6 p-8">
    <x-admin.page-header>
        Ubicaciones

        <x-slot:actions>
            <flux:button :href="route('admin.locations.create')" variant="primary" icon="plus">Nueva ubicación</flux:button>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar :chips="$this->chips" reset="clearAll" clear-all="clearAll">
        <x-admin.search-input wire:model.live.debounce.300ms="search" placeholder="Buscar por nombre o slug" clearable />

        <x-slot:advanced>
            <flux:select variant="listbox" clearable wire:model.live="type" label="Tipo" placeholder="Todos">
                @foreach ($this->types() as $option)
                    <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </x-slot:advanced>
    </x-admin.filter-bar>

    <flux:table>
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">Nombre</flux:table.column>
            <flux:table.column>Slug</flux:table.column>
            <flux:table.column>Tipo</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'population'" :direction="$sortDirection" wire:click="sort('population')">Población</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->tree as $row)
                <flux:table.row wire:key="location-{{ $row['location']->id }}">
                    <flux:table.cell class="max-w-96">
                        <div class="flex min-w-0 items-center gap-1" style="padding-left: {{ $row['depth'] * 20 }}px">
                            @if ($row['depth'] > 0)
                                <span class="shrink-0 text-zinc-300">└</span>
                            @endif
                            <x-admin.record-link :href="route('admin.locations.edit', $row['location'])" class="block truncate">
                                {{ $row['location']->name }}
                            </x-admin.record-link>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell class="max-w-64 truncate"><code class="text-xs">{{ $row['location']->slug }}</code></flux:table.cell>
                    <flux:table.cell><flux:badge size="sm">{{ $row['location']->type->label() }}</flux:badge></flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">{{ $row['location']->population ? number_format($row['location']->population, 0, ',', '.') : '—' }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4" class="text-center text-zinc-500">
                        @if ($search !== '' || $type !== '')
                            No hay ubicaciones con esos filtros.
                        @else
                            No hay ubicaciones. <a href="{{ route('admin.locations.create') }}" class="underline">Crea la primera</a>.
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
