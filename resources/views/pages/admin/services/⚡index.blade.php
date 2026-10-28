<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Service;
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
#[Title('Servicios')]
class extends Component
{
    use WithPagination;

    /** @var list<string> */
    protected const SORTABLE = ['name', 'position', 'updated_at'];

    #[Url(as: 's')]
    public string $search = '';

    #[Url(as: 'category')]
    public ?int $categoryId = null;

    #[Url(as: 'status')]
    public string $status = 'all';

    /** Empty keeps the catalogue's own order: position, then name. */
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

    public function toggleActive(int $id): void
    {
        $service = Service::findOrFail($id);
        $service->update(['is_active' => ! $service->is_active]);
    }

    /**
     * @return Collection<int, Category>
     */
    #[Computed]
    public function categories(): Collection
    {
        return Category::orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function services(): LengthAwarePaginator
    {
        $direction = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        return Service::query()
            ->with('category:id,name,slug')
            ->when($this->search !== '', fn ($q) => $q->where(function ($q): void {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('slug', 'like', "%{$this->search}%");
            }))
            ->when($this->categoryId, fn ($q) => $q->where('category_id', $this->categoryId))
            ->when($this->status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when(
                in_array($this->sortBy, self::SORTABLE, true),
                fn ($q) => $q->orderBy($this->sortBy, $direction),
            )
            ->ordered()
            ->paginate(25);
    }

    /** @return array<string, string> */
    #[Computed]
    public function statuses(): array
    {
        return ['active' => 'Activos', 'inactive' => 'Inactivos'];
    }

    /**
     * @return Collection<int, array{key: string, label: string}>
     */
    #[Computed]
    public function chips(): Collection
    {
        return collect([
            $this->categoryId ? ['key' => 'categoryId', 'label' => (string) $this->categories->firstWhere('id', $this->categoryId)?->name] : null,
            $this->status !== 'all' ? ['key' => 'status', 'label' => $this->statuses[$this->status] ?? ''] : null,
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
            'status' => $this->status = 'all',
            default => null,
        };

        $this->resetPage();
    }

    public function clearAll(): void
    {
        $this->reset('search', 'categoryId', 'status', 'sortBy', 'sortDirection');
        $this->resetPage();
    }
};
?>

<div class="space-y-6 p-8">
    <x-admin.page-header>
        Servicios

        <x-slot:actions>
            <flux:button :href="route('admin.services.create')" variant="primary" icon="plus">Nuevo servicio</flux:button>
        </x-slot:actions>
    </x-admin.page-header>

    <flux:text>
        Las fichas del catálogo, cada una en su categoría. Desactivada, una ficha deja de salir en la web
        pero se conserva aquí.
    </flux:text>

    <x-admin.filter-bar :chips="$this->chips" reset="clearAll" clear-all="clearAll">
        <x-admin.search-input wire:model.live.debounce.300ms="search" placeholder="Buscar por nombre o slug" clearable />

        <x-slot:advanced>
            <flux:select variant="listbox" searchable clearable wire:model.live="categoryId" label="Categoría" placeholder="Todas">
                @foreach ($this->categories as $category)
                    <flux:select.option :value="$category->id">{{ $category->name }}</flux:select.option>
                @endforeach
            </flux:select>

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
            <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">Nombre</flux:table.column>
            <flux:table.column>Categoría</flux:table.column>
            <flux:table.column>Slug</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'position'" :direction="$sortDirection" wire:click="sort('position')">Posición</flux:table.column>
            <flux:table.column>Activo</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'updated_at'" :direction="$sortDirection" wire:click="sort('updated_at')">Actualizado</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->services as $service)
                <flux:table.row wire:key="service-{{ $service->id }}">
                    <flux:table.cell class="max-w-96">
                        <x-admin.record-link :href="route('admin.services.edit', $service)" class="block truncate">{{ $service->name }}</x-admin.record-link>
                    </flux:table.cell>
                    <flux:table.cell class="max-w-48 truncate">{{ $service->category?->name ?? '—' }}</flux:table.cell>
                    <flux:table.cell class="max-w-64 truncate"><code class="text-xs">{{ $service->slug }}</code></flux:table.cell>
                    <flux:table.cell>{{ $service->position }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:switch wire:click="toggleActive({{ $service->id }})" :checked="$service->is_active" aria-label="Activo" />
                    </flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap text-zinc-500">{{ $service->updated_at?->format('d/m/Y') }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center text-zinc-500">
                        No hay servicios con esos filtros.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{ $this->services->links() }}
</div>
