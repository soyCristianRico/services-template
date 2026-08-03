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
    public MenuItemForm $form;

    public ?MenuItem $menuItem = null;

    public function mount(?MenuItem $menuItem = null): void
    {
        if ($menuItem?->exists) {
            $this->menuItem = $menuItem;
            $this->form->setMenuItem($menuItem);

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

        session()->flash('status', $this->menuItem ? 'Ítem actualizado.' : 'Ítem creado.');

        $this->redirectRoute('admin.menus.edit', $menuItem);
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
};
?>

<div class="mx-auto max-w-3xl space-y-6 p-8">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">
            {{ $menuItem ? 'Editar ítem: '.$menuItem->label : 'Nuevo ítem de menú' }}
        </flux:heading>
        <flux:button :href="route('admin.menus.index')" variant="ghost" icon="arrow-left">Volver</flux:button>
    </div>

    @session('status')
        <flux:callout icon="check-circle" color="green">{{ $value }}</flux:callout>
    @endsession

    <form wire:submit="save" class="space-y-6">
        <div class="grid gap-4 md:grid-cols-2">
            <flux:field>
                <flux:label>Menú</flux:label>
                <flux:select wire:model.live="form.location">
                    @foreach (MenuLocation::cases() as $location)
                        <flux:select.option value="{{ $location->value }}">{{ $location->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="form.location" />
            </flux:field>

            <flux:field>
                <flux:label>Cuelga de</flux:label>
                <flux:select wire:model="form.parent_id" :disabled="! $this->canHaveParent">
                    <flux:select.option value="">— Primer nivel —</flux:select.option>
                    @foreach ($this->parentOptions as $option)
                        <flux:select.option value="{{ $option->id }}">{{ $option->label }}</flux:select.option>
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
        </div>

        <flux:field>
            <flux:label>Rótulo</flux:label>
            <flux:input wire:model="form.label" required />
            <flux:description>El texto que se lee en el menú.</flux:description>
            <flux:error name="form.label" />
        </flux:field>

        <flux:field>
            <flux:label>Tipo</flux:label>
            <flux:select wire:model.live="form.type">
                @foreach (MenuItemType::cases() as $type)
                    @if ($type !== MenuItemType::DynamicBlock || MenuItemForm::blocks() !== [])
                        <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
                    @endif
                @endforeach
            </flux:select>
            <flux:description>
                Un enlace lleva a una dirección; un bloque dinámico pinta un listado que se actualiza solo.
            </flux:description>
            <flux:error name="form.type" />
        </flux:field>

        @if ($form->type === MenuItemType::DynamicBlock->value)
            <flux:field>
                <flux:label>Bloque</flux:label>
                <flux:select wire:model="form.source" placeholder="Elige un bloque…">
                    @foreach (MenuItemForm::blocks() as $key => $label)
                        <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:description>Lo pinta la plantilla; su contenido sale del catálogo, no de aquí.</flux:description>
                <flux:error name="form.source" />
            </flux:field>
        @else
            <flux:field>
                <flux:label>Dirección</flux:label>
                <flux:input wire:model="form.url" placeholder="/servicios/" />
                <flux:description>
                    Interna con barra (<code>/servicios/</code>) o completa para otro sitio
                    (<code>https://…</code>). Déjala vacía si el ítem sólo es el título de una columna.
                </flux:description>
                <flux:error name="form.url" />
            </flux:field>
        @endif

        <div class="grid gap-4 md:grid-cols-2">
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

        <div class="flex justify-end gap-3">
            <flux:button :href="route('admin.menus.index')" variant="ghost">Cancelar</flux:button>
            <flux:button type="submit" variant="primary">
                {{ $menuItem ? 'Guardar cambios' : 'Crear ítem' }}
            </flux:button>
        </div>
    </form>
</div>
