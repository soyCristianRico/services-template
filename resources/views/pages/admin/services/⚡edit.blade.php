<?php

declare(strict_types=1);

use App\Livewire\Forms\Catalog\ServiceForm;
use App\Models\Category;
use App\Models\Service;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new
#[Layout('layouts.admin')]
class extends Component
{
    use WithFileUploads;

    public ServiceForm $form;

    public ?Service $service = null;

    public bool $slugManuallyEdited = false;

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $newImages = [];

    public function mount(?Service $service = null): void
    {
        if ($service?->exists) {
            $this->service = $service;
            $this->form->setService($service);
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

    public function updatedNewImages(): void
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
            // the "file does not exist" race.
            $tmpPath = tempnam(sys_get_temp_dir(), 'media_upload_');
            copy($image->getRealPath(), $tmpPath);

            $this->service
                ->addMedia($tmpPath)
                ->usingFileName($image->getClientOriginalName())
                ->toMediaCollection('gallery');
        }

        $this->newImages = [];
        $this->service->refresh();
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
        $service = $this->form->save();

        session()->flash('status', $this->service ? 'Servicio actualizado.' : 'Servicio creado.');

        $this->redirectRoute('admin.services.edit', $service);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Category>
     */
    #[Computed]
    public function categories(): \Illuminate\Support\Collection
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

<div class="mx-auto max-w-3xl space-y-6 p-8">
    <x-admin.page-header :back="route('admin.services.index')">
            {{ $service ? 'Editar servicio: '.$service->name : 'Nuevo servicio' }}

        @if ($service)
            <x-slot:actions>
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

    @session('status')
        <flux:callout icon="check-circle" color="green">{{ $value }}</flux:callout>
    @endsession

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>Categoría</flux:label>
            <flux:select wire:model="form.category_id" required>
                <flux:select.option value="">— Selecciona —</flux:select.option>
                @foreach ($this->categories as $category)
                    <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="form.category_id" />
        </flux:field>

        <flux:field>
            <flux:label>Categorías adicionales</flux:label>
            <flux:select variant="listbox" searchable multiple wire:model="form.additional_category_ids" placeholder="— Ninguna —">
                @foreach ($this->categories as $category)
                    <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:description>El servicio también aparecerá en estas categorías, además de la principal.</flux:description>
            <flux:error name="form.additional_category_ids" />
        </flux:field>

        <flux:field>
            <flux:label>Nombre</flux:label>
            <flux:input wire:model.live.debounce.300ms="form.name" required />
            <flux:error name="form.name" />
        </flux:field>

        <flux:field>
            <flux:label>Slug</flux:label>
            <flux:input wire:model="form.slug" required />
            <flux:description>Sólo minúsculas, números y guiones.</flux:description>
            <flux:error name="form.slug" />
        </flux:field>

        <flux:field>
            <flux:label>Descripción corta</flux:label>
            <flux:input wire:model="form.short_description" placeholder="Una línea (255 caracteres)" />
            <flux:error name="form.short_description" />
        </flux:field>

        <flux:field>
            <flux:label>Descripción larga</flux:label>
            <flux:textarea wire:model="form.description" rows="6" />
            <flux:error name="form.description" />
        </flux:field>

        <div class="grid gap-4 md:grid-cols-2">
            <flux:field>
                <flux:label>Activo</flux:label>
                <flux:switch wire:model="form.is_active" />
            </flux:field>

            <flux:field>
                <flux:label>Posición (orden)</flux:label>
                <flux:input type="number" wire:model="form.position" min="0" />
                <flux:description>Más bajo aparece antes en el listado.</flux:description>
                <flux:error name="form.position" />
            </flux:field>
        </div>

        <flux:separator />
        <flux:heading size="lg">Campos custom (JSON)</flux:heading>

        <flux:field>
            <flux:label>Specs / atributos</flux:label>
            <flux:textarea wire:model="form.customFieldsJson" rows="10" class="font-mono text-sm" placeholder='{"power_kva": 100, "fuel": "diesel", "autonomy_h": 14}' />
            <flux:description>
                JSON con los campos específicos del servicio. Cada sitio define su propio esquema (kVA/dB/fuel para generadores; m³/lockable para contenedores; …).
            </flux:description>
            <flux:error name="form.customFieldsJson" />
        </flux:field>

        <div class="flex justify-end gap-3">
            <flux:button :href="route('admin.services.index')" variant="ghost">Cancelar</flux:button>
            <flux:button type="submit" variant="primary">
                {{ $service ? 'Guardar cambios' : 'Crear servicio' }}
            </flux:button>
        </div>
    </form>

    @if ($service)
        <flux:separator />
        <flux:heading size="lg">Galería</flux:heading>
        <flux:text class="text-zinc-600">JPG, PNG, WEBP o GIF, hasta 5 MB por imagen.</flux:text>

        <div>
            <input
                type="file"
                wire:model="newImages"
                multiple
                accept="image/*"
                class="block w-full text-sm text-zinc-700 file:mr-4 file:rounded file:border-0 file:bg-zinc-900 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-zinc-800">
            <flux:error name="newImages.*" />

            <div wire:loading wire:target="newImages" class="mt-2 text-sm text-zinc-500">Subiendo…</div>
        </div>

        @if ($service->getMedia('gallery')->isNotEmpty())
            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                @foreach ($service->getMedia('gallery') as $media)
                    <div wire:key="media-{{ $media->id }}" class="group relative overflow-hidden rounded-lg border border-zinc-200 bg-white">
                        <img src="{{ $media->getUrl() }}" alt="{{ $media->name }}" class="aspect-square w-full object-cover">
                        <button
                            type="button"
                            wire:click="deleteImage({{ $media->id }})"
                            wire:confirm="¿Eliminar esta imagen?"
                            class="absolute right-2 top-2 rounded-full bg-white/90 p-1 text-zinc-700 opacity-0 shadow transition-opacity hover:bg-red-600 hover:text-white group-hover:opacity-100"
                            aria-label="Eliminar imagen">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                @endforeach
            </div>
        @else
            <flux:callout icon="photo" color="zinc">Aún no hay imágenes. Sube la primera arriba.</flux:callout>
        @endif
    @else
        <flux:callout icon="information-circle" color="zinc">
            Guarda el servicio antes para poder subir imágenes.
        </flux:callout>
    @endif
</div>
