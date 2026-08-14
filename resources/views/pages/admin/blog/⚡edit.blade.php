<?php

declare(strict_types=1);

use App\Livewire\Forms\Blog\BlogPostForm;
use App\Models\BlogPost;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new
#[Layout('layouts.admin')]
class extends Component
{
    use WithFileUploads;

    public BlogPostForm $form;

    public ?BlogPost $post = null;

    public bool $slugManuallyEdited = false;

    public mixed $newHero = null;

    public function mount(?BlogPost $post = null): void
    {
        if ($post?->exists) {
            $this->post = $post;
            $this->form->setPost($post);
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

    public function updatedNewHero(): void
    {
        $this->validate(['newHero' => ['image', 'max:5120']], [
            'newHero.image' => 'El archivo debe ser una imagen.',
            'newHero.max' => 'La imagen debe pesar como máximo 5 MB.',
        ]);

        if ($this->post === null) {
            return;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'media_upload_');
        copy($this->newHero->getRealPath(), $tmp);

        $this->post
            ->addMedia($tmp)
            ->usingFileName($this->newHero->getClientOriginalName())
            ->toMediaCollection('hero');

        $this->newHero = null;
        $this->post->refresh();
    }

    public function deleteHero(): void
    {
        $this->post?->getFirstMedia('hero')?->delete();
        $this->post?->refresh();
    }

    public function save(): void
    {
        $post = $this->form->save();

        session()->flash('status', $this->post ? 'Artículo actualizado.' : 'Artículo creado.');

        $this->redirectRoute('admin.blog.edit', $post);
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
        $this->post?->delete();

        $this->redirectRoute('admin.blog.index', navigate: true);
    }
};
?>

<div class="mx-auto max-w-3xl space-y-6 p-8">
    <x-admin.page-header :back="route('admin.blog.index')">
            {{ $post ? 'Editar artículo: '.$post->title : 'Nuevo artículo' }}

        @if ($post)
            <x-slot:actions>
                <x-admin.record-menu>
                    <flux:menu.item
                        wire:click="delete"
                        wire:confirm="¿Eliminar el artículo {{ $post->title }}?"
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

    @if ($post)
        <flux:callout icon="link" color="zinc">
            <flux:callout.text>
                <flux:callout.link :href="url('/blog/'.$post->slug)" external>{{ url('/blog/'.$post->slug) }}</flux:callout.link>
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
            <flux:description>URL: /blog/{slug}. Sólo minúsculas, números y guiones.</flux:description>
            <flux:error name="form.slug" />
        </flux:field>

        <flux:field>
            <flux:label>Excerpt</flux:label>
            <flux:textarea wire:model="form.excerpt" rows="2" placeholder="Una entradilla corta (320 chars)" />
            <flux:error name="form.excerpt" />
        </flux:field>

        <flux:field>
            <flux:label>Cuerpo (HTML)</flux:label>
            <x-admin.rich-editor wire:model="form.body" />
            <flux:error name="form.body" />
        </flux:field>

        <div class="grid gap-4 md:grid-cols-2">
            <flux:field>
                <flux:label>Autor</flux:label>
                <flux:input wire:model="form.author_name" placeholder="Nombre que aparece bajo el título" />
                <flux:error name="form.author_name" />
            </flux:field>

            <flux:field>
                <flux:label>Tags (separados por coma)</flux:label>
                <flux:input wire:model="form.tagsCsv" placeholder="seo, generadores, comparativa" />
                <flux:error name="form.tagsCsv" />
            </flux:field>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <flux:field>
                <flux:label>Publicado en</flux:label>
                <flux:input type="datetime-local" wire:model="form.published_at" />
                <flux:description>Vacío = borrador. Futuro = programado. Pasado = público.</flux:description>
                <flux:error name="form.published_at" />
            </flux:field>

            <flux:field>
                <flux:label>Activo</flux:label>
                <flux:switch wire:model="form.is_active" />
                <flux:description>Si está inactivo, no sale aunque tenga fecha.</flux:description>
            </flux:field>
        </div>

        <flux:separator />
        <flux:heading size="lg">SEO</flux:heading>

        <flux:field>
            <flux:label>Meta título (override)</flux:label>
            <flux:input wire:model="form.meta_title" placeholder="Vacío = usa el título" />
            <flux:error name="form.meta_title" />
        </flux:field>

        <flux:field>
            <flux:label>Meta descripción</flux:label>
            <flux:textarea wire:model="form.meta_description" rows="3" placeholder="Vacío = usa el excerpt" />
            <flux:error name="form.meta_description" />
        </flux:field>

        <div class="flex justify-end gap-3">
            <flux:button :href="route('admin.blog.index')" variant="ghost">Cancelar</flux:button>
            <flux:button type="submit" variant="primary">
                {{ $post ? 'Guardar cambios' : 'Crear artículo' }}
            </flux:button>
        </div>
    </form>

    @if ($post)
        <flux:separator />
        <flux:heading size="lg">Imagen destacada</flux:heading>

        @if ($post->getFirstMediaUrl('hero'))
            <div class="space-y-3">
                <img src="{{ $post->getFirstMediaUrl('hero') }}" alt="Hero" class="aspect-[16/9] w-full rounded-lg object-cover">
                <flux:button wire:click="deleteHero" wire:confirm="¿Eliminar la imagen destacada?" variant="ghost" size="sm" icon="trash">
                    Eliminar hero
                </flux:button>
            </div>
        @else
            <flux:text class="text-zinc-600">Sin imagen destacada todavía.</flux:text>
        @endif

        <div>
            <input
                type="file"
                wire:model="newHero"
                accept="image/jpeg,image/png,image/webp"
                class="block w-full text-sm text-zinc-700 file:mr-4 file:rounded file:border-0 file:bg-zinc-900 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-zinc-800">
            <flux:error name="newHero" />
            <div wire:loading wire:target="newHero" class="mt-2 text-sm text-zinc-500">Subiendo…</div>
        </div>
    @else
        <flux:callout icon="information-circle" color="zinc">
            Guarda el artículo para poder subir la imagen destacada.
        </flux:callout>
    @endif
</div>
