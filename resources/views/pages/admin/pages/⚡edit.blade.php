<?php

declare(strict_types=1);

use App\Livewire\Forms\Catalog\PageForm;
use App\Models\Page;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new
#[Layout('layouts.admin')]
#[Title('Página')]
class extends Component
{
    public PageForm $form;

    public ?Page $page = null;

    #[Url(as: 'tab')]
    public string $tab = 'content';

    public bool $slugManuallyEdited = false;

    public function mount(?Page $page = null): void
    {
        if ($page?->exists) {
            $this->page = $page;
            $this->form->setPage($page);
            $this->slugManuallyEdited = true;
        }

        if (session()->has('status')) {
            Flux::toast(text: session('status'), variant: 'success');
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
        try {
            $page = $this->form->save();
        } catch (ValidationException $exception) {
            $this->tab = $this->tabForField((string) array_key_first($exception->errors()));

            throw $exception;
        }

        if ($this->page) {
            $this->page->refresh();
            Flux::toast(text: 'Página guardada.', variant: 'success');

            return;
        }

        session()->flash('status', 'Página creada.');

        $this->redirectRoute('admin.pages.edit', $page);
    }

    public function rename(): void
    {
        $this->form->rename();
        $this->page?->refresh();

        Flux::toast(text: 'Título actualizado.', variant: 'success');
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

    protected function tabForField(string $field): string
    {
        return match (explode('.', $field)[1] ?? $field) {
            'meta_title', 'meta_description' => 'seo',
            default => 'content',
        };
    }
};
?>

<div class="mx-auto max-w-3xl space-y-10 p-8 pb-28">
    <x-admin.page-header :breadcrumbs="[
        ['label' => 'Páginas', 'href' => route('admin.pages.index')],
        ['label' => $page?->title ?? 'Nueva página'],
    ]">
        @if ($page)
            <input
                type="text"
                wire:model="form.title"
                wire:change="rename"
                aria-label="Título"
                class="[font:inherit] -mx-2 w-full rounded-md border border-transparent bg-transparent px-2 py-1 hover:border-zinc-300 focus:border-zinc-400 focus:outline-none"
            />
        @else
            Nueva página
        @endif

        @if ($page)
            <x-slot:actions>
                <flux:badge :color="$page->is_active ? 'green' : 'zinc'">{{ $page->is_active ? 'Activa' : 'Inactiva' }}</flux:badge>
                <x-admin.record-menu>
                    <flux:menu.item :href="url('/'.$page->slug)" target="_blank" icon="arrow-top-right-on-square">
                        Ver en la web
                    </flux:menu.item>
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

    @if ($page)
        <flux:error name="form.title" />

        <flux:tab.group>
            <flux:tabs wire:model="tab" scrollable scrollable:scrollbar="hide">
                <flux:tab name="content">Contenido</flux:tab>
                <flux:tab name="seo">SEO</flux:tab>
            </flux:tabs>

            <flux:tab.panel name="content" class="space-y-10">@include('pages.admin.pages.partials.content')</flux:tab.panel>
            <flux:tab.panel name="seo" class="space-y-10">@include('pages.admin.pages.partials.seo')</flux:tab.panel>
        </flux:tab.group>
    @else
        @include('pages.admin.pages.partials.content')
        @include('pages.admin.pages.partials.seo')
    @endif

    <div class="fixed inset-x-0 bottom-0 z-10 border-t border-zinc-200 bg-zinc-50 lg:start-64">
        <div class="mx-auto flex max-w-3xl justify-end gap-3 px-8 py-4">
            <flux:button :href="route('admin.pages.index')" variant="ghost">Cancelar</flux:button>
            <flux:button wire:click="save" variant="primary">{{ $page ? 'Guardar' : 'Crear página' }}</flux:button>
        </div>
    </div>
</div>
