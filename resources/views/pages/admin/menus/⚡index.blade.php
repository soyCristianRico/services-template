<?php

declare(strict_types=1);

use App\Enums\MenuItemType;
use App\Enums\MenuLocation;
use App\Livewire\Forms\Navigation\MenuItemForm;
use App\Models\MenuItem;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.admin')]
#[Title('Menús')]
class extends Component
{
    public function toggleActive(int $id): void
    {
        $item = MenuItem::findOrFail($id);
        $item->update(['is_active' => ! $item->is_active]);
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
     * Every menu with its items already flattened in reading order (each parent
     * followed by its submenu), so the whole navigation reads in one screen
     * instead of one location at a time.
     *
     * @return list<array{location: MenuLocation, rows: Collection<int, MenuItem>}>
     */
    #[Computed]
    public function menus(): array
    {
        return collect(MenuLocation::cases())
            ->map(fn (MenuLocation $location): array => [
                'location' => $location,
                'rows' => MenuItem::query()
                    ->in($location)
                    ->roots()
                    ->with('children')
                    ->get()
                    ->flatMap(fn (MenuItem $item): array => [$item, ...$item->children]),
            ])
            ->all();
    }

    /**
     * What a row points at: the block's name, its address, or nothing when it
     * is only a column title.
     */
    public function destinationOf(MenuItem $item): ?string
    {
        if ($item->type === MenuItemType::DynamicBlock) {
            return MenuItemForm::blocks()[$item->source] ?? $item->source;
        }

        return $item->url;
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

<div class="space-y-10 p-8">
    <div class="space-y-2">
        <x-admin.page-header>
            Menús

            <x-slot:actions>
                <flux:button :href="route('admin.menus.create')" variant="primary" icon="plus">Nuevo ítem</flux:button>
            </x-slot:actions>
        </x-admin.page-header>

        <flux:text>
            Los enlaces de la cabecera y del pie del sitio público. El orden de esta pantalla es el orden que se ve.
        </flux:text>
    </div>

    @foreach ($this->menus as $menu)
        <section wire:key="location-{{ $menu['location']->value }}" class="space-y-3">
            <div class="flex items-center justify-between gap-4">
                <flux:heading size="lg">{{ $menu['location']->label() }}</flux:heading>
                <flux:button :href="route('admin.menus.create', ['location' => $menu['location']->value])"
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
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($menu['rows'] as $row)
                        <flux:table.row wire:key="item-{{ $row->id }}">
                            <flux:table.cell class="max-w-96">
                                <span @class(['flex min-w-0 items-center gap-2', 'pl-6' => $row->parent_id !== null])>
                                    @if ($row->parent_id !== null)
                                        <span class="text-zinc-300">└</span>
                                    @endif
                                    <x-admin.record-link :href="route('admin.menus.edit', $row)" class="truncate">{{ $row->label }}</x-admin.record-link>
                                </span>
                            </flux:table.cell>
                            <flux:table.cell class="max-w-72 truncate">
                                @if ($row->type === MenuItemType::DynamicBlock)
                                    <flux:badge size="sm" color="purple">{{ $this->destinationOf($row) }}</flux:badge>
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
                            <flux:table.cell>
                                <div class="flex gap-1">
                                    <flux:button wire:click="moveUp({{ $row->id }})" size="xs" variant="ghost"
                                        icon="arrow-up" aria-label="Subir" />
                                    <flux:button wire:click="moveDown({{ $row->id }})" size="xs" variant="ghost"
                                        icon="arrow-down" aria-label="Bajar" />
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4" class="text-center text-zinc-500">
                                Este menú está vacío.
                                <a href="{{ route('admin.menus.create', ['location' => $menu['location']->value]) }}" class="underline">
                                    Añade el primer ítem
                                </a>.
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </section>
    @endforeach
</div>
