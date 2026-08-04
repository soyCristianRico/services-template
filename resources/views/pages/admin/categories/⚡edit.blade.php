<?php

declare(strict_types=1);

use App\Livewire\Forms\Catalog\CategoryForm;
use App\Models\Category;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts.admin')]
class extends Component
{
    public CategoryForm $form;

    public ?Category $category = null;

    public bool $slugManuallyEdited = false;

    public function mount(?Category $category = null): void
    {
        if ($category?->exists) {
            $this->category = $category;
            $this->form->setCategory($category);
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
        $category = $this->form->save();

        session()->flash('status', $this->category ? 'Categoría actualizada.' : 'Categoría creada.');

        $this->redirectRoute('admin.categories.edit', $category);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{id: int, label: string}>
     */
    #[Computed]
    public function parentOptions(): \Illuminate\Support\Collection
    {
        return Category::query()
            ->when($this->form->id, fn ($q) => $q->where('id', '!=', $this->form->id))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Category $c): array => ['id' => $c->id, 'label' => $c->name]);
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
        $this->category?->delete();

        $this->redirectRoute('admin.categories.index', navigate: true);
    }
};
?>

<div class="mx-auto max-w-3xl space-y-6 p-8">
    <x-admin.page-header :back="route('admin.categories.index')">
            {{ $category ? 'Editar categoría: '.$category->name : 'Nueva categoría' }}

        @if ($category)
            <x-slot:actions>
                <x-admin.record-menu>
                    <flux:menu.item
                        wire:click="delete"
                        wire:confirm="¿Eliminar {{ $category->name }}? Sus hijos se quedan sin padre."
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
                <flux:label>Padre</flux:label>
                <flux:select wire:model="form.parent_id" placeholder="— Sin padre (raíz) —">
                    <flux:select.option value="">— Sin padre (raíz) —</flux:select.option>
                    @foreach ($this->parentOptions as $option)
                        <flux:select.option value="{{ $option['id'] }}">{{ $option['label'] }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="form.parent_id" />
            </flux:field>

            <flux:field>
                <flux:label>Icono (Heroicon)</flux:label>
                <flux:input wire:model="form.icon" placeholder="bolt" />
                <flux:description>Nombre de un icono de Heroicons (ej. <code>bolt</code>, <code>truck</code>).</flux:description>
                <flux:error name="form.icon" />
            </flux:field>
        </div>

        <flux:field>
            <flux:label>Orden</flux:label>
            <flux:input type="number" min="0" wire:model="form.position" class="max-w-32" />
            <flux:description>Menor número aparece antes. Deja <code>0</code> para mantener el orden alfabético por defecto.</flux:description>
            <flux:error name="form.position" />
        </flux:field>

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
            <flux:button :href="route('admin.categories.index')" variant="ghost">Cancelar</flux:button>
            <flux:button type="submit" variant="primary">
                {{ $category ? 'Guardar cambios' : 'Crear categoría' }}
            </flux:button>
        </div>
    </form>
</div>
