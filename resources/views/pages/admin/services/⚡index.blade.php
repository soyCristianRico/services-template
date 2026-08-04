<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Service;
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

    #[Url(as: 'category')]
    public ?int $categoryId = null;

    #[Url(as: 'status')]
    public string $status = 'all';

    public function updating(): void
    {
        $this->resetPage();
    }

    public function toggleActive(int $id): void
    {
        $service = Service::findOrFail($id);
        $service->update(['is_active' => ! $service->is_active]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Category>
     */
    #[Computed]
    public function categories(): \Illuminate\Support\Collection
    {
        return Category::orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function services(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return Service::query()
            ->with('category:id,name,slug')
            ->when($this->search !== '', fn ($q) => $q->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('slug', 'like', "%{$this->search}%");
            }))
            ->when($this->categoryId, fn ($q) => $q->where('category_id', $this->categoryId))
            ->when($this->status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->ordered()
            ->paginate(25);
    }
};
?>

<div class="space-y-6 p-8">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">Servicios</flux:heading>
        <flux:button :href="route('admin.services.create')" variant="primary" icon="plus">Nuevo servicio</flux:button>
    </div>

    <flux:text class="text-zinc-600">
        Catálogo de equipos. Opcional — el template no muestra servicios en la landing pública por defecto;
        cada sitio decide si los renderiza (con <code>x-service-grid :category="$landing->category" /></code>).
    </flux:text>

    <div class="grid gap-3 md:grid-cols-3">
        <flux:input wire:model.live.debounce.300ms="search" type="search" placeholder="Buscar por nombre o slug…" icon="magnifying-glass" />
        <flux:select wire:model.live="categoryId" placeholder="Todas las categorías">
            <flux:select.option value="">Todas las categorías</flux:select.option>
            @foreach ($this->categories as $category)
                <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="status">
            <flux:select.option value="all">Todos</flux:select.option>
            <flux:select.option value="active">Sólo activos</flux:select.option>
            <flux:select.option value="inactive">Sólo inactivos</flux:select.option>
        </flux:select>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Nombre</flux:table.column>
            <flux:table.column>Categoría</flux:table.column>
            <flux:table.column>Slug</flux:table.column>
            <flux:table.column>Posición</flux:table.column>
            <flux:table.column>Estado</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->services as $service)
                <flux:table.row wire:key="service-{{ $service->id }}">
                    <flux:table.cell>
                        <x-admin.record-link :href="route('admin.services.edit', $service)">{{ $service->name }}</x-admin.record-link>
                    </flux:table.cell>
                    <flux:table.cell>{{ $service->category?->name ?? '—' }}</flux:table.cell>
                    <flux:table.cell><code class="text-xs">{{ $service->slug }}</code></flux:table.cell>
                    <flux:table.cell>{{ $service->position }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:switch wire:click="toggleActive({{ $service->id }})" :checked="$service->is_active" />
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="text-center text-zinc-500">
                        No hay servicios con esos filtros.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{ $this->services->links() }}
</div>
