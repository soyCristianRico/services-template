<?php

declare(strict_types=1);

use App\Models\Category;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new
#[Layout('layouts.admin')]
#[Title('Categorías')]
class extends Component
{
    /** @var list<string> */
    protected const SORTABLE = ['name', 'updated_at'];

    #[Url(as: 's')]
    public string $search = '';

    /** Empty keeps the tree: each categoría under its parent, in its own order. */
    #[Url(as: 'orden')]
    public string $sortBy = '';

    #[Url(as: 'sentido')]
    public string $sortDirection = 'asc';

    /**
     * The tree, depth-first from each root. A search or a sort flattens it.
     *
     * @return array<int, array{category: Category, depth: int}>
     */
    #[Computed]
    public function tree(): array
    {
        if ($this->search !== '' || $this->sortBy !== '') {
            return $this->flatList();
        }

        $byParent = Category::ordered()->get()->groupBy('parent_id');
        $rows = [];

        $walk = function (?int $parentId, int $depth) use (&$walk, $byParent, &$rows): void {
            foreach ($byParent->get((string) $parentId, collect()) as $node) {
                $rows[] = ['category' => $node, 'depth' => $depth];
                $walk($node->id, $depth + 1);
            }
        };

        $walk(null, 0);

        return $rows;
    }

    public function sort(string $column): void
    {
        if (! in_array($column, self::SORTABLE, true)) {
            return;
        }

        $this->sortDirection = $this->sortBy === $column && $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->sortBy = $column;
    }

    /**
     * @return array<int, array{category: Category, depth: int}>
     */
    protected function flatList(): array
    {
        $direction = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        return Category::query()
            ->when($this->search !== '', fn ($q) => $q->where(function ($q): void {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('slug', 'like', "%{$this->search}%");
            }))
            ->when(
                in_array($this->sortBy, self::SORTABLE, true),
                fn ($q) => $q->orderBy($this->sortBy, $direction),
                fn ($q) => $q->ordered(),
            )
            ->get()
            ->map(fn (Category $category): array => ['category' => $category, 'depth' => 0])
            ->all();
    }
};
?>

<div class="space-y-6 p-8">
    <x-admin.page-header>
        Categorías

        <x-slot:actions>
            <flux:button :href="route('admin.categories.create')" variant="primary" icon="plus">Nueva categoría</flux:button>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.search-input wire:model.live.debounce.300ms="search" placeholder="Buscar por nombre o slug" clearable />

    <flux:table>
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">Nombre</flux:table.column>
            <flux:table.column>Slug</flux:table.column>
            <flux:table.column>Icono</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'updated_at'" :direction="$sortDirection" wire:click="sort('updated_at')">Actualizada</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->tree as $row)
                <flux:table.row wire:key="category-{{ $row['category']->id }}">
                    <flux:table.cell class="max-w-96">
                        <div class="flex min-w-0 items-center gap-1" style="padding-left: {{ $row['depth'] * 20 }}px">
                            @if ($row['depth'] > 0)
                                <span class="shrink-0 text-zinc-300">└</span>
                            @endif
                            <x-admin.record-link :href="route('admin.categories.edit', $row['category'])" class="block truncate">
                                {{ $row['category']->name }}
                            </x-admin.record-link>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell class="max-w-64 truncate"><code class="text-xs">{{ $row['category']->slug }}</code></flux:table.cell>
                    <flux:table.cell>
                        @if ($row['category']->icon)
                            <flux:icon :name="$row['category']->icon" class="size-4 text-zinc-500" />
                        @else
                            <span class="text-zinc-400">—</span>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap text-zinc-500">{{ $row['category']->updated_at?->format('d/m/Y') }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4" class="text-center text-zinc-500">
                        @if ($search !== '')
                            No hay categorías con esa búsqueda.
                        @else
                            No hay categorías. <a href="{{ route('admin.categories.create') }}" class="underline">Crea la primera</a>.
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
