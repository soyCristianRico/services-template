<?php

declare(strict_types=1);

use App\Livewire\Forms\Catalog\PageForm;
use App\Models\Page;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts.admin')]
class extends Component
{
    public PageForm $form;

    public ?Page $page = null;

    public bool $slugManuallyEdited = false;

    public function mount(?Page $page = null): void
    {
        if ($page?->exists) {
            $this->page = $page;
            $this->form->setPage($page);
            $this->slugManuallyEdited = true;
        }
    }

    public function updatedFormTitle(string $value): void
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
        $page = $this->form->save();

        session()->flash('status', $this->page ? 'Página actualizada.' : 'Página creada.');

        $this->redirectRoute('admin.pages.edit', $page);
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
        $this->page?->delete();

        $this->redirectRoute('admin.pages.index', navigate: true);
    }
};
?>

<div class="mx-auto max-w-3xl space-y-6 p-8">
    <x-admin.page-header :back="route('admin.pages.index')">
            {{ $page ? 'Editar página: '.$page->title : 'Nueva página' }}

        @if ($page)
            <x-slot:actions>
                <x-admin.record-menu>
                    <flux:menu.item
                        wire:click="delete"
                        wire:confirm="¿Eliminar la página /{{ $page->slug }}? La URL devolverá 404."
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

    @if ($page)
        <flux:callout icon="link" color="zinc">
            <flux:callout.text>
                <flux:callout.link :href="url('/'.$page->slug)" external>{{ url('/'.$page->slug) }}</flux:callout.link>
            </flux:callout.text>
        </flux:callout>
    @endif

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>Título</flux:label>
            <flux:input wire:model.live.debounce.300ms="form.title" required />
            <flux:error name="form.title" />
        </flux:field>

        <flux:field>
            <flux:label>Slug</flux:label>
            <flux:input wire:model="form.slug" required />
            <flux:description>Sólo minúsculas, números y guiones. Aparece como <code>/{slug}</code>.</flux:description>
            <flux:error name="form.slug" />
        </flux:field>

        <flux:field>
            <flux:label>Activa</flux:label>
            <flux:switch wire:model="form.is_active" />
            <flux:description>Si está inactiva, la URL devuelve 404 y no sale en el sitemap.</flux:description>
        </flux:field>

        <flux:separator />
        <flux:heading size="lg">Contenido</flux:heading>

        <flux:field>
            <flux:label>Cuerpo</flux:label>
            <flux:editor wire:model="form.body" />
            <flux:description>HTML enriquecido. Se renderiza tal cual en la página pública dentro del wrapper <code>.prose</code>.</flux:description>
            <flux:error name="form.body" />
        </flux:field>

        <flux:separator />
        <flux:heading size="lg">SEO</flux:heading>

        <flux:field>
            <flux:label>Meta título (override)</flux:label>
            <flux:input wire:model="form.meta_title" placeholder="Vacío = usa el título" />
            <flux:error name="form.meta_title" />
        </flux:field>

        <flux:field>
            <flux:label>Meta descripción</flux:label>
            <flux:textarea wire:model="form.meta_description" rows="3" placeholder="150-160 caracteres" />
            <flux:error name="form.meta_description" />
        </flux:field>

        <div class="flex justify-end gap-3">
            <flux:button :href="route('admin.pages.index')" variant="ghost">Cancelar</flux:button>
            <flux:button type="submit" variant="primary">
                {{ $page ? 'Guardar cambios' : 'Crear página' }}
            </flux:button>
        </div>
    </form>
</div>
