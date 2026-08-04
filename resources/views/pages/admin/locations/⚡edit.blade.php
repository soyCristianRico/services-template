<?php

declare(strict_types=1);

use App\Enums\LocationType;
use App\Livewire\Forms\Catalog\LocationForm;
use App\Models\Location;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts.admin')]
class extends Component
{
    public LocationForm $form;

    public ?Location $location = null;

    public bool $slugManuallyEdited = false;

    public function mount(?Location $location = null): void
    {
        if ($location?->exists) {
            $this->location = $location;
            $this->form->setLocation($location);
            $this->slugManuallyEdited = true;
        }
    }

    public function updatedFormName(string $value): void
    {
        if (! $this->slugManuallyEdited) {
            $this->form->slug = Str::slug($value);
        }
    }

    public function updatedFormSlug(): void
    {
        $this->slugManuallyEdited = true;
    }

    public function save(): void
    {
        $location = $this->form->save();

        session()->flash('status', $this->location ? 'Ubicación actualizada.' : 'Ubicación creada.');

        $this->redirectRoute('admin.locations.edit', $location);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{id: int, label: string}>
     */
    #[Computed]
    public function parentOptions(): \Illuminate\Support\Collection
    {
        return Location::query()
            ->when($this->form->id, fn ($q) => $q->where('id', '!=', $this->form->id))
            ->orderBy('name')
            ->get(['id', 'name', 'type'])
            ->map(fn (Location $c): array => ['id' => $c->id, 'label' => $c->name.' ('.$c->type->label().')']);
    }

    /**
     * Borra el registro y vuelve al listado, que es de donde se venía.
     *
     * Vive aquí y no en el índice porque borrar desde una fila deja el
     * puntero a un clic del lápiz de al lado; quien borra ya ha abierto
     * la ficha.
     */
    public function delete(): void
    {
        $this->location?->delete();

        $this->redirectRoute('admin.locations.index', navigate: true);
    }
};
?>

<div class="mx-auto max-w-3xl space-y-6 p-8">
    <x-admin.page-header :back="route('admin.locations.index')">
            {{ $location ? 'Editar ubicación: '.$location->name : 'Nueva ubicación' }}

        @if ($location)
            <x-slot:actions>
                <x-admin.record-menu>
                    <flux:menu.item
                        wire:click="delete"
                        wire:confirm="¿Eliminar {{ $location->name }}? Sus hijos se quedan sin padre."
                        icon="trash" variant="danger">
                        Borrar
                    </flux:menu.item>
                </x-admin.record-menu>
            </x-slot:actions>
        @endif
    </x-admin.page-header>

    @session('status')
        <flux:callout icon="check-circle" color="green">{{ $value }}</flux:callout>
    @endsession

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>Nombre</flux:label>
            <flux:input wire:model.live.debounce.300ms="form.name" required />
            <flux:error name="form.name" />
        </flux:field>

        <flux:field>
            <flux:label>Slug</flux:label>
            <flux:input wire:model="form.slug" required />
            <flux:description>Sólo minúsculas, números y guiones. Aparece en la URL.</flux:description>
            <flux:error name="form.slug" />
        </flux:field>

        <div class="grid gap-4 md:grid-cols-2">
            <flux:field>
                <flux:label>Tipo</flux:label>
                <flux:select wire:model="form.type">
                    @foreach (LocationType::cases() as $option)
                        <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="form.type" />
            </flux:field>

            <flux:field>
                <flux:label>Padre</flux:label>
                <flux:select wire:model="form.parent_id" placeholder="— Sin padre (raíz) —">
                    <flux:select.option value="">— Sin padre (raíz) —</flux:select.option>
                    @foreach ($this->parentOptions as $option)
                        <flux:select.option value="{{ $option['id'] }}">{{ $option['label'] }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="form.parent_id" />
            </flux:field>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <flux:field>
                <flux:label>Población</flux:label>
                <flux:input type="number" wire:model="form.population" min="0" />
                <flux:error name="form.population" />
            </flux:field>

            <flux:field>
                <flux:label>Latitud</flux:label>
                <flux:input wire:model="form.latitude" placeholder="40.4168" />
                <flux:error name="form.latitude" />
            </flux:field>

            <flux:field>
                <flux:label>Longitud</flux:label>
                <flux:input wire:model="form.longitude" placeholder="-3.7038" />
                <flux:error name="form.longitude" />
            </flux:field>
        </div>

        <flux:separator />
        <flux:heading size="lg">SEO</flux:heading>

        <flux:field>
            <flux:label>Meta título</flux:label>
            <flux:input wire:model="form.meta_title" placeholder="Título mostrado en Google" />
            <flux:error name="form.meta_title" />
        </flux:field>

        <flux:field>
            <flux:label>Meta descripción</flux:label>
            <flux:textarea wire:model="form.meta_description" rows="3" placeholder="150-160 caracteres" />
            <flux:error name="form.meta_description" />
        </flux:field>

        <div class="flex justify-end gap-3">
            <flux:button :href="route('admin.locations.index')" variant="ghost">Cancelar</flux:button>
            <flux:button type="submit" variant="primary">
                {{ $location ? 'Guardar cambios' : 'Crear ubicación' }}
            </flux:button>
        </div>
    </form>
</div>
