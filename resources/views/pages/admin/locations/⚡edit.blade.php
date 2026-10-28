<?php

declare(strict_types=1);

use App\Enums\LocationType;
use App\Livewire\Forms\Catalog\LocationForm;
use App\Models\Location;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.admin')]
#[Title('Ubicación')]
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

        if (session()->has('status')) {
            Flux::toast(text: session('status'), variant: 'success');
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

        if ($this->location) {
            $this->location->refresh();
            Flux::toast(text: 'Ubicación guardada.', variant: 'success');

            return;
        }

        session()->flash('status', 'Ubicación creada.');

        $this->redirectRoute('admin.locations.edit', $location);
    }

    public function rename(): void
    {
        $this->form->rename();
        $this->location?->refresh();

        Flux::toast(text: 'Nombre actualizado.', variant: 'success');
    }

    /**
     * @return list<LocationType>
     */
    public function types(): array
    {
        return LocationType::cases();
    }

    /**
     * @return Collection<int, array{id: int, label: string}>
     */
    #[Computed]
    public function parentOptions(): Collection
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

<div class="mx-auto max-w-2xl space-y-10 p-8 pb-28">
    <x-admin.page-header :breadcrumbs="[
        ['label' => 'Ubicaciones', 'href' => route('admin.locations.index')],
        ['label' => $location?->name ?? 'Nueva ubicación'],
    ]">
        @if ($location)
            <input
                type="text"
                wire:model="form.name"
                wire:change="rename"
                aria-label="Nombre"
                class="[font:inherit] -mx-2 w-full rounded-md border border-transparent bg-transparent px-2 py-1 hover:border-zinc-300 focus:border-zinc-400 focus:outline-none"
            />
        @else
            Nueva ubicación
        @endif

        @if ($location)
            <x-slot:actions>
                <flux:badge>{{ $location->type->label() }}</flux:badge>
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

    @if ($location)
        <flux:error name="form.name" />
    @endif

    <section class="space-y-6">
        <flux:heading size="lg">Datos</flux:heading>

        @unless ($location)
            <flux:field>
                <flux:label>Nombre</flux:label>
                <flux:input wire:model.live.debounce.300ms="form.name" required />
                <flux:error name="form.name" />
            </flux:field>
        @endunless

        <flux:field>
            <flux:label>Slug</flux:label>
            <flux:input wire:model="form.slug" required />
            <flux:description>Sólo minúsculas, números y guiones. Aparece en la URL.</flux:description>
            <flux:error name="form.slug" />
        </flux:field>

        <div class="grid gap-6 md:grid-cols-2">
            <flux:field>
                <flux:label>Tipo</flux:label>
                <flux:select variant="listbox" wire:model="form.type">
                    @foreach ($this->types() as $option)
                        <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="form.type" />
            </flux:field>

            <flux:field>
                <flux:label badge="Opcional">Padre</flux:label>
                <flux:select variant="listbox" searchable clearable wire:model="form.parent_id" placeholder="Sin padre (raíz)">
                    @foreach ($this->parentOptions as $option)
                        <flux:select.option :value="$option['id']">{{ $option['label'] }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="form.parent_id" />
            </flux:field>
        </div>

        <div class="grid gap-6 md:grid-cols-3">
            <flux:field>
                <flux:label badge="Opcional">Población</flux:label>
                <flux:input type="number" wire:model="form.population" min="0" />
                <flux:error name="form.population" />
            </flux:field>

            <flux:field>
                <flux:label badge="Opcional">Latitud</flux:label>
                <flux:input wire:model="form.latitude" placeholder="40.4168" />
                <flux:error name="form.latitude" />
            </flux:field>

            <flux:field>
                <flux:label badge="Opcional">Longitud</flux:label>
                <flux:input wire:model="form.longitude" placeholder="-3.7038" />
                <flux:error name="form.longitude" />
            </flux:field>
        </div>
    </section>

    <section class="space-y-6">
        <flux:heading size="lg">SEO</flux:heading>

        <flux:field>
            <flux:label badge="Opcional">Meta título</flux:label>
            <flux:input wire:model="form.meta_title" placeholder="Título mostrado en Google" />
            <flux:error name="form.meta_title" />
        </flux:field>

        <flux:field>
            <flux:label badge="Opcional">Meta descripción</flux:label>
            <flux:textarea wire:model="form.meta_description" rows="3" placeholder="150-160 caracteres" />
            <flux:error name="form.meta_description" />
        </flux:field>
    </section>

    <div class="fixed inset-x-0 bottom-0 z-10 border-t border-zinc-200 bg-zinc-50 lg:start-64">
        <div class="mx-auto flex max-w-2xl justify-end gap-3 px-8 py-4">
            <flux:button :href="route('admin.locations.index')" variant="ghost">Cancelar</flux:button>
            <flux:button wire:click="save" variant="primary">{{ $location ? 'Guardar' : 'Crear ubicación' }}</flux:button>
        </div>
    </div>
</div>
