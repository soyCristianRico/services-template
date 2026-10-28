<?php

declare(strict_types=1);

use App\Livewire\Forms\Blog\BlogPostForm;
use App\Models\BlogPost;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Toolkit\Images\ImagePreparer;

new
#[Layout('layouts.admin')]
#[Title('Artículo')]
class extends Component
{
    use WithFileUploads;

    public BlogPostForm $form;

    public ?BlogPost $post = null;

    #[Url(as: 'tab')]
    public string $tab = 'content';

    public bool $slugManuallyEdited = false;

    public mixed $newHero = null;

    public function mount(?BlogPost $post = null): void
    {
        if ($post?->exists) {
            $this->post = $post;
            $this->form->setPost($post);
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

    public function updatedNewHero(ImagePreparer $preparer): void
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

        $prepared = $preparer->prepare($tmp, $this->newHero->getClientOriginalName(), $this->post->title);

        $this->post
            ->addMedia($prepared['path'])
            ->usingFileName($prepared['name'])
            ->toMediaCollection('hero');

        $this->newHero = null;
        $this->post->refresh();
        unset($this->hero);
    }

    public function deleteHero(): void
    {
        $this->post?->getFirstMedia('hero')?->delete();
        $this->post?->refresh();
        unset($this->hero);
    }

    #[Computed]
    public function hero(): ?Media
    {
        return $this->post?->getFirstMedia('hero');
    }

    public function save(): void
    {
        try {
            $post = $this->form->save();
        } catch (ValidationException $exception) {
            $this->tab = $this->tabForField((string) array_key_first($exception->errors()));

            throw $exception;
        }

        if ($this->post) {
            $this->post->refresh();
            Flux::toast(text: 'Artículo guardado.', variant: 'success');

            return;
        }

        session()->flash('status', 'Artículo creado.');

        $this->redirectRoute('admin.blog.edit', $post);
    }

    public function rename(): void
    {
        $this->form->rename();
        $this->post?->refresh();

        Flux::toast(text: 'Título actualizado.', variant: 'success');
    }

    /**
     * What the public sees of the post, derived from its activation and its date.
     *
     * @return array{label: string, color: string}
     */
    #[Computed]
    public function state(): array
    {
        return match (true) {
            $this->post === null => ['label' => '', 'color' => 'zinc'],
            ! $this->post->is_active => ['label' => 'Inactivo', 'color' => 'zinc'],
            $this->post->published_at === null => ['label' => 'Borrador', 'color' => 'amber'],
            $this->post->published_at->isFuture() => ['label' => 'Programado', 'color' => 'blue'],
            default => ['label' => 'Publicado', 'color' => 'green'],
        };
    }

    protected function tabForField(string $field): string
    {
        return match (explode('.', $field)[1] ?? $field) {
            'published_at', 'is_active', 'author_name', 'tagsCsv' => 'publication',
            'meta_title', 'meta_description' => 'seo',
            default => 'content',
        };
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

<div class="mx-auto max-w-3xl space-y-10 p-8 pb-28">
    <x-admin.page-header :breadcrumbs="[
        ['label' => 'Blog', 'href' => route('admin.blog.index')],
        ['label' => $post?->title ?? 'Nuevo artículo'],
    ]">
        @if ($post)
            <input
                type="text"
                wire:model="form.title"
                wire:change="rename"
                aria-label="Título"
                class="[font:inherit] -mx-2 w-full rounded-md border border-transparent bg-transparent px-2 py-1 hover:border-zinc-300 focus:border-zinc-400 focus:outline-none"
            />
        @else
            Nuevo artículo
        @endif

        @if ($post)
            <x-slot:actions>
                <flux:badge :color="$this->state['color']">{{ $this->state['label'] }}</flux:badge>
                <x-admin.record-menu>
                    <flux:menu.item :href="url('/blog/'.$post->slug)" target="_blank" icon="arrow-top-right-on-square">
                        Ver en la web
                    </flux:menu.item>
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

    @if ($post)
        <flux:error name="form.title" />

        <flux:tab.group>
            <flux:tabs wire:model="tab" scrollable scrollable:scrollbar="hide">
                <flux:tab name="content">Contenido</flux:tab>
                <flux:tab name="publication">Publicación</flux:tab>
                <flux:tab name="image">Imagen</flux:tab>
                <flux:tab name="seo">SEO</flux:tab>
            </flux:tabs>

            <flux:tab.panel name="content" class="space-y-10">@include('pages.admin.blog.partials.content')</flux:tab.panel>
            <flux:tab.panel name="publication" class="space-y-10">@include('pages.admin.blog.partials.publication')</flux:tab.panel>
            <flux:tab.panel name="image" class="space-y-10">@include('pages.admin.blog.partials.image')</flux:tab.panel>
            <flux:tab.panel name="seo" class="space-y-10">@include('pages.admin.blog.partials.seo')</flux:tab.panel>
        </flux:tab.group>
    @else
        @include('pages.admin.blog.partials.content')
        @include('pages.admin.blog.partials.publication')
        @include('pages.admin.blog.partials.seo')

        <flux:callout icon="information-circle" color="zinc">
            Crea el artículo para poder subir la imagen destacada.
        </flux:callout>
    @endif

    <div class="fixed inset-x-0 bottom-0 z-10 border-t border-zinc-200 bg-zinc-50 lg:start-64">
        <div class="mx-auto flex max-w-3xl justify-end gap-3 px-8 py-4">
            <flux:button :href="route('admin.blog.index')" variant="ghost">Cancelar</flux:button>
            <flux:button wire:click="save" variant="primary">{{ $post ? 'Guardar' : 'Crear artículo' }}</flux:button>
        </div>
    </div>
</div>
