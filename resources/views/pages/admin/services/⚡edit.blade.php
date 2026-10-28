<?php

declare(strict_types=1);

use App\Enums\AdminSection;
use App\Livewire\Forms\Catalog\ServiceForm;
use App\Models\Category;
use App\Models\Service;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Toolkit\Images\ImagePreparer;

new
#[Layout('layouts.admin')]
#[Title('Servicio')]
class extends Component
{
    use WithFileUploads;

    public ServiceForm $form;

    public ?Service $service = null;

    #[Url(as: 'tab')]
    public string $tab = 'general';

    public bool $slugManuallyEdited = false;

    /** @var array<int, TemporaryUploadedFile> */
    public array $newImages = [];

    public function mount(?Service $service = null): void
    {
        if ($service?->exists) {
            $this->service = $service;
            $this->form->setService($service);
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

    public function updatedNewImages(ImagePreparer $preparer): void
    {
        $this->validate([
            'newImages.*' => ['image', 'max:5120'],
        ], [
            'newImages.*.image' => 'Cada archivo debe ser una imagen.',
            'newImages.*.max' => 'Cada imagen debe pesar como máximo 5 MB.',
        ]);

        if ($this->service === null) {
            return;
        }

        foreach ($this->newImages as $image) {
            // Copy first so we hand Spatie a stable, owned file path. Livewire
            // can shuffle the validated temp file out from under us between
            // validate() and addMedia(), so working from a fresh copy avoids
            // the "file does not exist" race. The copy is also shrunk to web
            // size, and a camera name ("IMG_2034") is swapped for the service's.
            $tmpPath = tempnam(sys_get_temp_dir(), 'media_upload_');
            copy($image->getRealPath(), $tmpPath);

            $prepared = $preparer->prepare($tmpPath, $image->getClientOriginalName(), $this->service->name);

            $this->service
                ->addMedia($prepared['path'])
                ->usingFileName($prepared['name'])
                ->toMediaCollection('gallery');
        }

        $this->newImages = [];
        $this->service->refresh();
    }

    /**
     * @return Collection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media>
     */
    #[Computed]
    public function gallery(): Collection
    {
        return $this->service?->getMedia('gallery') ?? collect();
    }

    public function deleteImage(int $mediaId): void
    {
        if ($this->service === null) {
            return;
        }

        $media = $this->service->getMedia('gallery')->firstWhere('id', $mediaId);
        $media?->delete();

        $this->service->refresh();
    }

    public function save(): void
    {
        try {
            $service = $this->form->save();
        } catch (ValidationException $exception) {
            $this->tab = $this->tabForField((string) array_key_first($exception->errors()));

            throw $exception;
        }

        if ($this->service) {
            $this->service->refresh();
            Flux::toast(text: 'Servicio guardado.', variant: 'success');

            return;
        }

        session()->flash('status', 'Servicio creado.');

        $this->redirectRoute('admin.services.edit', $service);
    }

    public function rename(): void
    {
        $this->form->rename();
        $this->service?->refresh();

        Flux::toast(text: 'Nombre actualizado.', variant: 'success');
    }

    protected function tabForField(string $field): string
    {
        return match (explode('.', $field)[1] ?? $field) {
            'customFieldsJson' => 'data',
            default => 'general',
        };
    }

    /**
     * @return Collection<int, Category>
     */
    #[Computed]
    public function categories(): Collection
    {
        return Category::orderBy('name')->get(['id', 'name']);
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
        $this->service?->delete();

        $this->redirectRoute('admin.services.index', navigate: true);
    }
};
?>

<div class="mx-auto max-w-3xl space-y-10 p-8 pb-28">
    <x-admin.page-header :breadcrumbs="[
        ['label' => AdminSection::Services->label(), 'href' => route('admin.services.index')],
        ['label' => $service?->name ?? 'Nuevo servicio'],
    ]">
        @if ($service)
            <input
                type="text"
                wire:model="form.name"
                wire:change="rename"
                aria-label="Nombre"
                class="[font:inherit] -mx-2 w-full rounded-md border border-transparent bg-transparent px-2 py-1 hover:border-zinc-300 focus:border-zinc-400 focus:outline-none"
            />
        @else
            Nuevo servicio
        @endif

        @if ($service)
            <x-slot:actions>
                <flux:badge :color="$service->is_active ? 'green' : 'zinc'">{{ $service->is_active ? 'Activo' : 'Inactivo' }}</flux:badge>
                <x-admin.record-menu>
                    <flux:menu.item
                        wire:click="delete"
                        wire:confirm="¿Eliminar el servicio {{ $service->name }}?"
                        icon="trash" variant="danger">
                        Borrar
                    </flux:menu.item>
                </x-admin.record-menu>
            </x-slot:actions>
        @endif
    </x-admin.page-header>

    @if ($service)
        <flux:error name="form.name" />

        <flux:tab.group>
            <flux:tabs wire:model="tab" scrollable scrollable:scrollbar="hide">
                <flux:tab name="general">General</flux:tab>
                <flux:tab name="data">Campos custom</flux:tab>
                <flux:tab name="gallery">Galería</flux:tab>
            </flux:tabs>

            <flux:tab.panel name="general" class="space-y-10">@include('pages.admin.services.partials.general')</flux:tab.panel>
            <flux:tab.panel name="data" class="space-y-10">@include('pages.admin.services.partials.data')</flux:tab.panel>
            <flux:tab.panel name="gallery">@include('pages.admin.services.partials.gallery')</flux:tab.panel>
        </flux:tab.group>
    @else
        @include('pages.admin.services.partials.general')
        @include('pages.admin.services.partials.data')

        <flux:callout icon="information-circle" color="zinc">
            Guarda el servicio antes para poder subir imágenes.
        </flux:callout>
    @endif

    <div class="fixed inset-x-0 bottom-0 z-10 border-t border-zinc-200 bg-zinc-50 lg:start-64">
        <div class="mx-auto flex max-w-3xl justify-end gap-3 px-8 py-4">
            <flux:button :href="route('admin.services.index')" variant="ghost">Cancelar</flux:button>
            <flux:button wire:click="save" variant="primary">{{ $service ? 'Guardar' : 'Crear servicio' }}</flux:button>
        </div>
    </div>
</div>
