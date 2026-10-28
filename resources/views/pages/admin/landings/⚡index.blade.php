<?php

declare(strict_types=1);

use App\Enums\LandingStatus;
use App\Models\Category;
use App\Models\Landing;
use App\Models\Location;
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
#[Title('Landings')]
class extends Component
{
    use WithPagination;

    /** @var list<string> */
    protected const SORTABLE = ['slug', 'publish_at', 'updated_at'];

    #[Url(as: 's')]
    public string $search = '';

    #[Url(as: 'category')]
    public ?int $categoryId = null;

    #[Url(as: 'location')]
    public ?int $locationId = null;

    #[Url(as: 'status')]
    public string $status = 'all'; // all | draft | scheduled | published

    /** Empty keeps the most recently edited first. */
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
     * @return Collection<int, Category>
     */
    #[Computed]
    public function categories(): Collection
    {
        return Category::orderBy('name')->get(['id', 'name']);
    }

    /**
     * @return Collection<int, Location>
     */
    #[Computed]
    public function locations(): Collection
    {
        return Location::orderBy('name')->get(['id', 'name']);
    }

    /**
     * @return list<LandingStatus>
     */
    public function statuses(): array
    {
        return LandingStatus::cases();
    }

    #[Computed]
    public function landings(): LengthAwarePaginator
    {
        $direction = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        return Landing::query()
            ->with(['category:id,name,slug', 'location:id,name,slug'])
            ->when($this->search !== '', fn ($q) => $q->where('slug', 'like', "%{$this->search}%"))
            ->when($this->categoryId, fn ($q) => $q->where('category_id', $this->categoryId))
            ->when($this->locationId, fn ($q) => $q->where('location_id', $this->locationId))
            ->when(LandingStatus::tryFrom($this->status), fn ($q, LandingStatus $status) => $q->where('status', $status))
            ->when(
                in_array($this->sortBy, self::SORTABLE, true),
                fn ($q) => $q->orderBy($this->sortBy, $direction),
            )
            ->orderByDesc('updated_at')
            ->paginate(25);
    }

    /**
     * @return Collection<int, array{key: string, label: string}>
     */
    #[Computed]
    public function chips(): Collection
    {
        return collect([
            $this->categoryId ? ['key' => 'categoryId', 'label' => (string) $this->categories->firstWhere('id', $this->categoryId)?->name] : null,
            $this->locationId ? ['key' => 'locationId', 'label' => (string) $this->locations->firstWhere('id', $this->locationId)?->name] : null,
            $this->status !== 'all' ? ['key' => 'status', 'label' => (string) LandingStatus::tryFrom($this->status)?->label()] : null,
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
            'categoryId' => $this->categoryId = null,
            'locationId' => $this->locationId = null,
            'status' => $this->status = 'all',
            default => null,
        };

        $this->resetPage();
    }

    public function clearAll(): void
    {
        $this->reset('search', 'categoryId', 'locationId', 'status', 'sortBy', 'sortDirection');
        $this->resetPage();
    }
};
?>

<div class="space-y-6 p-8">
    <x-admin.page-header>
        Landings

        <x-slot:actions>
            <flux:button :href="route('admin.landings.matrix')" variant="ghost" icon="squares-2x2">Matriz</flux:button>
            <flux:button :href="route('admin.landings.create')" variant="primary" icon="plus">Nueva landing</flux:button>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar :chips="$this->chips" reset="clearAll" clear-all="clearAll">
        <x-admin.search-input wire:model.live.debounce.300ms="search" placeholder="Buscar por slug" clearable />

        <x-slot:advanced>
            <flux:select variant="listbox" searchable clearable wire:model.live="categoryId" label="Categoría" placeholder="Todas">
                @foreach ($this->categories as $category)
                    <flux:select.option :value="$category->id">{{ $category->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select variant="listbox" searchable clearable wire:model.live="locationId" label="Ubicación" placeholder="Todas">
                @foreach ($this->locations as $location)
                    <flux:select.option :value="$location->id">{{ $location->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select variant="listbox" wire:model.live="status" label="Estado">
                <flux:select.option value="all">Todos</flux:select.option>
                @foreach ($this->statuses() as $option)
                    <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </x-slot:advanced>
    </x-admin.filter-bar>

    <flux:table>
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'slug'" :direction="$sortDirection" wire:click="sort('slug')">URL</flux:table.column>
            <flux:table.column>Categoría</flux:table.column>
            <flux:table.column>Ubicación</flux:table.column>
            <flux:table.column>Estado</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'publish_at'" :direction="$sortDirection" wire:click="sort('publish_at')">Publicación</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'updated_at'" :direction="$sortDirection" wire:click="sort('updated_at')">Actualizada</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->landings as $landing)
                <flux:table.row wire:key="landing-{{ $landing->id }}">
                    <flux:table.cell class="max-w-96">
                        <div class="flex min-w-0 items-center gap-2">
                            <x-admin.record-link :href="route('admin.landings.edit', $landing)" class="block truncate">/{{ $landing->slug }}</x-admin.record-link>
                            <a href="{{ url('/'.$landing->slug) }}" target="_blank" class="shrink-0 text-zinc-400 hover:text-zinc-600" aria-label="Ver en la web">
                                <flux:icon name="arrow-top-right-on-square" variant="micro" />
                            </a>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell class="max-w-48 truncate">{{ $landing->category?->name ?? '—' }}</flux:table.cell>
                    <flux:table.cell class="max-w-48 truncate">{{ $landing->location?->name ?? 'sólo categoría' }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge :color="$landing->status->color()" size="sm">{{ $landing->status->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap text-zinc-500">{{ $landing->publish_at?->format('d/m/Y') ?? '—' }}</flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap text-zinc-500">{{ $landing->updated_at?->format('d/m/Y') }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center text-zinc-500">
                        No hay landings con esos filtros.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{ $this->landings->links() }}
</div>
