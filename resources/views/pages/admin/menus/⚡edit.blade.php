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
#[Title('Ítem de menú')]
class extends Component
{
    public MenuItemForm $form;

    public ?MenuItem $menuItem = null;

    public function mount(?MenuItem $menuItem = null): void
    {
        if ($menuItem?->exists) {
            $this->menuItem = $menuItem;
            $this->form->setMenuItem($menuItem);
        }

        if (session()->has('status')) {
            Flux::toast(text: session('status'), variant: 'success');
        }

        if ($this->menuItem) {
            return;
        }

        // The index links here from each menu's own «añadir aquí», so the new
        // item opens already pointing at the menu the editor was looking at.
        $requested = MenuLocation::tryFrom((string) request()->query('location'));
        $this->form->location = ($requested ?? MenuLocation::Header)->value;
    }

    public function updatedFormLocation(): void
    {
        // A parent from the previous menu would fail validation without saying
        // why, so the field starts over whenever the menu changes.
        $this->form->parent_id = null;
    }

    public function save(): void
    {
        $menuItem = $this->form->save();

        if ($this->menuItem) {
            $this->menuItem->refresh();
            Flux::toast(text: 'Ítem guardado.', variant: 'success');

            return;
        }

        session()->flash('status', 'Ítem creado.');

        $this->redirectRoute('admin.menus.edit', $menuItem);
    }

    public function rename(): void
    {
        $this->form->rename();
        $this->menuItem?->refresh();

        Flux::toast(text: 'Rótulo actualizado.', variant: 'success');
    }

    /**
     * @return list<MenuLocation>
     */
    public function locations(): array
    {
        return MenuLocation::cases();
    }

    /**
     * The types this site can paint: a dynamic block only when the layout declares any.
     *
     * @return list<MenuItemType>
     */
    public function types(): array
    {
        return array_values(array_filter(
            MenuItemType::cases(),
            fn (MenuItemType $type): bool => $type !== MenuItemType::DynamicBlock || MenuItemForm::blocks() !== [],
        ));
    }

    #[Computed]
    public function isDynamicBlock(): bool
    {
        return $this->form->type === MenuItemType::DynamicBlock->value;
    }

    #[Computed]
    public function hasSubmenu(): bool
    {
        return $this->menuItem?->children()->exists() ?? false;
    }

    /**
     * First-level items of the chosen menu: the only things a submenu can hang
     * from, minus the item being edited.
     *
     * @return Collection<int, MenuItem>
     */
    #[Computed]
    public function parentOptions(): Collection
    {
        $location = MenuLocation::tryFrom($this->form->location);

        if ($location === null) {
            return collect();
        }

        return MenuItem::query()
            ->in($location)
            ->roots()
            ->when($this->form->id, fn ($query) => $query->where('id', '!=', $this->form->id))
            ->get();
    }

    /**
     * An item with a submenu of its own cannot become somebody else's submenu:
     * the menu is two levels deep.
     */
    #[Computed]
    public function canHaveParent(): bool
    {
        return $this->menuItem === null || $this->menuItem->children()->doesntExist();
    }

    /**
     * Borrar el ítem, y con él su desplegable.
     *
     * Vive aquí y no en el índice porque borrar desde una fila deja el
     * puntero a un clic del lápiz de al lado; quien borra ya ha abierto
     * la ficha.
     */
    public function delete(): void
    {
        $this->menuItem?->delete();

        $this->redirectRoute('admin.menus.index', navigate: true);
    }
};
?>

<div class="mx-auto max-w-2xl space-y-10 p-8 pb-28">
    <x-admin.page-header :breadcrumbs="[
        ['label' => 'Menús', 'href' => route('admin.menus.index')],
        ['label' => $menuItem?->label ?? 'Nuevo ítem'],
    ]">
        @if ($menuItem)
            <input
                type="text"
                wire:model="form.label"
                wire:change="rename"
                aria-label="Rótulo"
                class="[font:inherit] -mx-2 w-full rounded-md border border-transparent bg-transparent px-2 py-1 hover:border-zinc-300 focus:border-zinc-400 focus:outline-none"
            />
        @else
            Nuevo ítem de menú
        @endif

        @if ($menuItem)
            <x-slot:actions>
                <flux:badge :color="$menuItem->is_active ? 'green' : 'zinc'">{{ $menuItem->is_active ? 'Visible' : 'Oculto' }}</flux:badge>
                <x-admin.record-menu>
                    <flux:menu.item
                        wire:click="delete"
                        wire:confirm="¿Eliminar «{{ $menuItem->label }}»?{{ $this->hasSubmenu ? ' Su desplegable se va con él.' : '' }}"
                        icon="trash" variant="danger">
                        Borrar
                    </flux:menu.item>
                </x-admin.record-menu>
            </x-slot:actions>
        @endif
    </x-admin.page-header>

    @if ($menuItem)
        <flux:error name="form.label" />
    @endif

    <section class="space-y-6">
        <flux:heading size="lg">Destino</flux:heading>

        @unless ($menuItem)
            <flux:field>
                <flux:label>Rótulo</flux:label>
                <flux:input wire:model="form.label" required />
                <flux:description>El texto que se lee en el menú.</flux:description>
                <flux:error name="form.label" />
            </flux:field>
        @endunless

        <flux:field>
            <flux:label>Tipo</flux:label>
            <flux:select variant="listbox" wire:model.live="form.type">
                @foreach ($this->types() as $type)
                    <flux:select.option :value="$type->value">{{ $type->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:description>
                Un enlace lleva a una dirección; un bloque dinámico pinta un listado que se actualiza solo.
            </flux:description>
            <flux:error name="form.type" />
        </flux:field>

        @if ($this->isDynamicBlock)
            <flux:field>
                <flux:label>Bloque</flux:label>
                <flux:select variant="listbox" wire:model="form.source" placeholder="Elige un bloque…">
                    @foreach (MenuItemForm::blocks() as $key => $label)
                        <flux:select.option :value="$key">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:description>Lo pinta la plantilla; su contenido sale del catálogo, no de aquí.</flux:description>
                <flux:error name="form.source" />
            </flux:field>
        @else
            <flux:field>
                <flux:label badge="Opcional">Dirección</flux:label>
                <flux:input wire:model="form.url" placeholder="/servicios/" />
                <flux:description>
                    Interna con barra (<code>/servicios/</code>) o completa para otro sitio
                    (<code>https://…</code>). Déjala vacía si el ítem sólo es el título de una columna.
                </flux:description>
                <flux:error name="form.url" />
            </flux:field>
        @endif
    </section>

    <section class="space-y-6">
        <flux:heading size="lg">Posición</flux:heading>

        <div class="grid gap-6 md:grid-cols-2">
            <flux:field>
                <flux:label>Menú</flux:label>
                <flux:select variant="listbox" wire:model.live="form.location">
                    @foreach ($this->locations() as $location)
                        <flux:select.option :value="$location->value">{{ $location->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="form.location" />
            </flux:field>

            <flux:field>
                <flux:label badge="Opcional">Cuelga de</flux:label>
                <flux:select variant="listbox" clearable wire:model="form.parent_id" placeholder="Primer nivel" :disabled="! $this->canHaveParent">
                    @foreach ($this->parentOptions as $option)
                        <flux:select.option :value="$option->id">{{ $option->label }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:description>
                    @if ($this->canHaveParent)
                        Elegir un padre convierte este ítem en parte de su desplegable.
                    @else
                        Este ítem ya tiene desplegable propio: el menú sólo admite dos niveles.
                    @endif
                </flux:description>
                <flux:error name="form.parent_id" />
            </flux:field>

            <flux:field>
                <flux:label>Orden</flux:label>
                <flux:input type="number" min="0" wire:model="form.position" class="max-w-32" />
                <flux:description>Menor número aparece antes. Deja <code>0</code> para colocarlo al final.</flux:description>
                <flux:error name="form.position" />
            </flux:field>

            <flux:field>
                <flux:label>Visible</flux:label>
                <flux:switch wire:model="form.is_active" />
                <flux:description>Oculto lo saca del menú sin borrarlo.</flux:description>
            </flux:field>
        </div>
    </section>

    <div class="fixed inset-x-0 bottom-0 z-10 border-t border-zinc-200 bg-zinc-50 lg:start-64">
        <div class="mx-auto flex max-w-2xl justify-end gap-3 px-8 py-4">
            <flux:button :href="route('admin.menus.index')" variant="ghost">Cancelar</flux:button>
            <flux:button wire:click="save" variant="primary">{{ $menuItem ? 'Guardar' : 'Crear ítem' }}</flux:button>
        </div>
    </div>
</div>
