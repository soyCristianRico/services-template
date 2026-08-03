<?php

declare(strict_types=1);

use App\Enums\MenuItemType;
use App\Enums\MenuLocation;
use App\Livewire\Forms\Navigation\MenuItemForm;
use App\Models\MenuItem;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts.admin')]
class extends Component
{
    public function toggleActive(int $id): void
    {
        $item = MenuItem::findOrFail($id);
        $item->update(['is_active' => ! $item->is_active]);
    }

    public function deleteItem(int $id): void
    {
        MenuItem::findOrFail($id)->delete();
    }

    public function moveUp(int $id): void
    {
        $this->move($id, -1);
    }

    public function moveDown(int $id): void
    {
        $this->move($id, 1);
    }

    /**
     * Every menu with its items already nested, so the whole navigation reads
     * in one screen instead of one location at a time.
     *
     * @return array<string, Collection<int, MenuItem>>
     */
    #[Computed]
    public function menus(): array
    {
        $menus = [];

        foreach (MenuLocation::cases() as $location) {
            $menus[$location->value] = MenuItem::query()
                ->in($location)
                ->roots()
                ->with('children')
                ->get();
        }

        return $menus;
    }

    /**
     * Swaps an item with the neighbour above or below among its own siblings,
     * then renumbers the lot: positions arrive from the seeder and can repeat,
     * and a swap alone would leave two items sharing a slot.
     */
    protected function move(int $id, int $direction): void
    {
        $item = MenuItem::findOrFail($id);

        $siblings = MenuItem::query()
            ->where('location', $item->location)
            ->where('parent_id', $item->parent_id)
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->all();

        $index = array_search($item->id, array_column($siblings, 'id'), true);
        $target = $index + $direction;

        if ($target < 0 || $target >= count($siblings)) {
            return;
        }

        [$siblings[$index], $siblings[$target]] = [$siblings[$target], $siblings[$index]];

        foreach ($siblings as $slot => $sibling) {
            $sibling->update(['position' => $slot + 1]);
        }
    }
};
?>

<div class="space-y-8 p-8">
    <div class="flex items-center justify-between">
        <div class="space-y-1">
            <flux:heading size="xl">Menús</flux:heading>
            <flux:text class="text-zinc-600">
                Los enlaces de la cabecera y del pie del sitio público. El orden de esta pantalla es el orden que se ve.
            </flux:text>
        </div>
        <flux:button :href="route('admin.menus.create')" variant="primary" icon="plus">Nuevo ítem</flux:button>
    </div>

    @foreach (MenuLocation::cases() as $location)
        @php($items = $this->menus[$location->value])

        <div wire:key="location-{{ $location->value }}" class="space-y-3">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ $location->label() }}</flux:heading>
                <flux:button :href="route('admin.menus.create', ['location' => $location->value])"
                    size="sm" variant="ghost" icon="plus">
                    Añadir aquí
                </flux:button>
            </div>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Rótulo</flux:table.column>
                    <flux:table.column>Destino</flux:table.column>
                    <flux:table.column>Estado</flux:table.column>
                    <flux:table.column>Orden</flux:table.column>
                    <flux:table.column>Acciones</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($items as $item)
                        @foreach ([$item, ...$item->children] as $row)
                            @php($isChild = $row->parent_id !== null)
                            <flux:table.row wire:key="item-{{ $row->id }}">
                                <flux:table.cell>
                                    <span @class(['pl-6' => $isChild, 'inline-flex items-center gap-2'])>
                                        @if ($isChild)
                                            <span class="text-zinc-300">└</span>
                                        @endif
                                        {{ $row->label }}
                                    </span>
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($row->type === MenuItemType::DynamicBlock)
                                        <flux:badge size="sm" color="purple">
                                            {{ MenuItemForm::blocks()[$row->source] ?? $row->source }}
                                        </flux:badge>
                                    @elseif ($row->url)
                                        <code class="text-xs">{{ $row->url }}</code>
                                    @else
                                        <span class="text-zinc-400">— sólo rótulo —</span>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:button
                                        wire:click="toggleActive({{ $row->id }})"
                                        size="xs" variant="ghost"
                                        :icon="$row->is_active ? 'eye' : 'eye-slash'">
                                        {{ $row->is_active ? 'Visible' : 'Oculto' }}
                                    </flux:button>
                                </flux:table.cell>
                                <flux:table.cell class="flex gap-1">
                                    <flux:button wire:click="moveUp({{ $row->id }})" size="xs" variant="ghost"
                                        icon="arrow-up" aria-label="Subir" />
                                    <flux:button wire:click="moveDown({{ $row->id }})" size="xs" variant="ghost"
                                        icon="arrow-down" aria-label="Bajar" />
                                </flux:table.cell>
                                <flux:table.cell class="flex gap-2">
                                    <flux:button :href="route('admin.menus.edit', $row)" size="xs" variant="ghost"
                                        icon="pencil-square" aria-label="Editar" />
                                    <flux:button
                                        wire:click="deleteItem({{ $row->id }})"
                                        wire:confirm="¿Eliminar «{{ $row->label }}»?{{ $row->children->isNotEmpty() ? ' Su desplegable se va con él.' : '' }}"
                                        size="xs" variant="ghost" icon="trash" aria-label="Eliminar" />
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5" class="text-center text-zinc-500">
                                Este menú está vacío.
                                <a href="{{ route('admin.menus.create', ['location' => $location->value]) }}" class="underline">
                                    Añade el primer ítem
                                </a>.
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    @endforeach
</div>
