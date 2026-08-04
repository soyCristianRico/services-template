<?php

declare(strict_types=1);

use App\Models\Category;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts.admin')]
class extends Component
{
    public string $search = '';

    /**
     * @return array<int, array{category: Category, depth: int}>
     */
    #[Computed]
    public function tree(): array
    {
        $categories = Category::ordered()->get();

        if ($this->search !== '') {
            $needle = mb_strtolower($this->search);
            $matches = $categories->filter(fn (Category $c) => str_contains(mb_strtolower($c->name), $needle));

            return $matches->map(fn (Category $c) => ['category' => $c, 'depth' => 0])->values()->all();
        }

        $byParent = $categories->groupBy('parent_id');
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
};
?>

<div class="space-y-6 p-8">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">Categorías</flux:heading>
        <flux:button :href="route('admin.categories.create')" variant="primary" icon="plus">Nueva categoría</flux:button>
    </div>

    <flux:input wire:model.live.debounce.300ms="search" type="search" placeholder="Buscar por nombre…" icon="magnifying-glass" class="max-w-md" />

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Nombre</flux:table.column>
            <flux:table.column>Slug</flux:table.column>
            <flux:table.column>Icono</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->tree as $row)
                @php($category = $row['category'])
                @php($depth = $row['depth'])
                <flux:table.row wire:key="category-{{ $category->id }}">
                    <flux:table.cell>
                        <x-admin.record-link :href="route('admin.categories.edit', $category)">
                            <span style="padding-left: {{ $depth * 20 }}px">
                        </x-admin.record-link>
                            @if ($depth > 0) <span class="text-zinc-300">└ </span> @endif
                            {{ $category->name }}
                        </span>
                    </flux:table.cell>
                    <flux:table.cell><code class="text-xs">{{ $category->slug }}</code></flux:table.cell>
                    <flux:table.cell>
                        @if ($category->icon)
                            <flux:icon :name="$category->icon" class="h-4 w-4 text-zinc-500" />
                        @else
                            <span class="text-zinc-300">—</span>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="3" class="text-center text-zinc-500">
                        No hay categorías. <a href="{{ route('admin.categories.create') }}" class="underline">Crea la primera</a>.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
