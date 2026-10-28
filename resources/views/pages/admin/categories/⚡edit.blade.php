<?php

declare(strict_types=1);

use App\Livewire\Forms\Catalog\CategoryForm;
use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.admin')]
#[Title('Categoría')]
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
        $category = $this->form->save();

        if ($this->category) {
            $this->category->refresh();
            Flux::toast(text: 'Categoría guardada.', variant: 'success');

            return;
        }

        session()->flash('status', 'Categoría creada.');

        $this->redirectRoute('admin.categories.edit', $category);
    }

    public function rename(): void
    {
        $this->form->rename();
        $this->category?->refresh();

        Flux::toast(text: 'Nombre actualizado.', variant: 'success');
    }

    /**
     * @return Collection<int, array{id: int, label: string}>
     */
    #[Computed]
    public function parentOptions(): Collection
    {
        return Category::query()
            ->when($this->form->id, fn ($q) => $q->where('id', '!=', $this->form->id))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Category $c): array => ['id' => $c->id, 'label' => $c->name]);
    }

    /**
     * Deleting the category takes its services with it (see Category::booted),
     * so the confirmation has to say how many before anyone clicks.
     */
    #[Computed]
    public function servicesCount(): int
    {
        return $this->category?->services()->count() ?? 0;
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

<div class="mx-auto max-w-2xl space-y-10 p-8 pb-28">
    <x-admin.page-header :breadcrumbs="[
        ['label' => 'Categorías', 'href' => route('admin.categories.index')],
        ['label' => $category?->name ?? 'Nueva categoría'],
    ]">
        @if ($category)
            <input
                type="text"
                wire:model="form.name"
                wire:change="rename"
                aria-label="Nombre"
                class="[font:inherit] -mx-2 w-full rounded-md border border-transparent bg-transparent px-2 py-1 hover:border-zinc-300 focus:border-zinc-400 focus:outline-none"
            />
        @else
            Nueva categoría
        @endif

        @if ($category)
            <x-slot:actions>
                <x-admin.record-menu>
                    <flux:menu.item
                        wire:click="delete"
                        wire:confirm="¿Eliminar {{ $category->name }}?{{ $this->servicesCount > 0 ? ' Se borran también sus '.$this->servicesCount.' servicios, con sus fotos.' : '' }} Sus subcategorías se quedan sin padre."
                        icon="trash" variant="danger">
                        Borrar
                    </flux:menu.item>
                </x-admin.record-menu>
            </x-slot:actions>
        @endif
    </x-admin.page-header>

    @if ($category)
        <flux:error name="form.name" />
    @endif

    <section class="space-y-6">
        <flux:heading size="lg">Datos</flux:heading>

        @unless ($category)
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

        <flux:field>
            <flux:label badge="Opcional">Padre</flux:label>
            <flux:select variant="listbox" searchable clearable wire:model="form.parent_id" placeholder="Sin padre (raíz)">
                @foreach ($this->parentOptions as $option)
                    <flux:select.option :value="$option['id']">{{ $option['label'] }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="form.parent_id" />
        </flux:field>

        <div class="grid gap-6 md:grid-cols-2">
            <flux:field>
                <flux:label badge="Opcional">Icono (Heroicon)</flux:label>
                <flux:input wire:model="form.icon" placeholder="bolt" />
                <flux:description>Nombre de un icono de Heroicons (ej. <code>bolt</code>, <code>truck</code>).</flux:description>
                <flux:error name="form.icon" />
            </flux:field>

            <flux:field>
                <flux:label>Orden</flux:label>
                <flux:input type="number" min="0" wire:model="form.position" class="max-w-32" />
                <flux:description>Menor número aparece antes. Deja <code>0</code> para el orden alfabético.</flux:description>
                <flux:error name="form.position" />
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
            <flux:button :href="route('admin.categories.index')" variant="ghost">Cancelar</flux:button>
            <flux:button wire:click="save" variant="primary">{{ $category ? 'Guardar' : 'Crear categoría' }}</flux:button>
        </div>
    </div>
</div>
