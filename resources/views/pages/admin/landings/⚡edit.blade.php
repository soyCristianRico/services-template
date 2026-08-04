<?php

declare(strict_types=1);

use App\Enums\LandingStatus;
use App\Livewire\Forms\Catalog\LandingForm;
use App\Models\Category;
use App\Models\Location;
use App\Models\Landing;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts.admin')]
class extends Component
{
    public LandingForm $form;

    public ?Landing $landing = null;

    public bool $slugManuallyEdited = false;

    public function mount(?Landing $landing = null): void
    {
        if ($landing?->exists) {
            $this->landing = $landing;
            $this->form->setLanding($landing);
            $this->slugManuallyEdited = true;
        }
    }

    public function updatedFormCategoryId(): void
    {
        $this->autofillSlug();
    }

    public function updatedFormLocationId(): void
    {
        $this->autofillSlug();
    }

    public function updatedFormSlug(): void
    {
        $this->slugManuallyEdited = true;
    }

    public function updatedFormPublishAt(): void
    {
        $this->form->syncStatusFromDate();
    }

    public function updatedFormStatus(): void
    {
        $this->form->syncDateFromStatus();
    }

    protected function autofillSlug(): void
    {
        if ($this->slugManuallyEdited || ! $this->form->category_id) {
            return;
        }

        $category = Category::find($this->form->category_id);
        $location = $this->form->location_id ? Location::find($this->form->location_id) : null;
        if ($category) {
            $separator = (string) config('seo.landing_slug_separator', '');
            if ($location) {
                $this->form->slug = $separator !== ''
                    ? "{$category->slug}-{$separator}-{$location->slug}"
                    : "{$category->slug}-{$location->slug}";
            } else {
                $this->form->slug = (string) $category->slug;
            }
        }
    }

    public function save(): void
    {
        $landing = $this->form->save();

        session()->flash('status', $this->landing ? 'Landing actualizada.' : 'Landing creada.');

        $this->redirectRoute('admin.landings.edit', $landing);
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
     * @return \Illuminate\Support\Collection<int, Location>
     */
    #[Computed]
    public function locations(): \Illuminate\Support\Collection
    {
        return Location::orderBy('name')->get(['id', 'name']);
    }

    public function unpublish(): void
    {
        $this->landing?->update([
            'status' => LandingStatus::Draft,
            'publish_at' => null,
        ]);

        $this->landing?->refresh();
    }

    public function publishNow(): void
    {
        $this->landing?->update([
            'status' => LandingStatus::Published,
            'publish_at' => null,
        ]);

        $this->landing?->refresh();
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
        $this->landing?->delete();

        $this->redirectRoute('admin.landings.index', navigate: true);
    }
};
?>

<div class="mx-auto max-w-3xl space-y-6 p-8">
    <x-admin.page-header :back="route('admin.landings.index')">
            {{ $landing ? 'Editar landing' : 'Nueva landing' }}

        @if ($landing)
            <x-slot:actions>
                <x-admin.record-menu>
                    @if ($landing->status === \App\Enums\LandingStatus::Published)
                        <flux:menu.item wire:click="unpublish" icon="eye-slash">Despublicar</flux:menu.item>
                    @else
                        <flux:menu.item wire:click="publishNow" icon="rocket-launch">Publicar ahora</flux:menu.item>
                    @endif
                    <flux:menu.item
                        wire:click="delete"
                        wire:confirm="¿Eliminar la landing /{{ $landing->slug }}? La URL devolverá 404."
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

    @if ($landing)
        <flux:callout icon="link" color="zinc">
            <flux:callout.text>
                <flux:callout.link :href="url('/'.$landing->slug)" external>{{ url('/'.$landing->slug) }}</flux:callout.link>
            </flux:callout.text>
        </flux:callout>
    @endif

    <form wire:submit="save" class="space-y-6">
        <div class="grid gap-4 md:grid-cols-2">
            <flux:field>
                <flux:label>Categoría</flux:label>
                <flux:select variant="listbox" searchable wire:model.live="form.category_id" placeholder="— Selecciona categoría —">
                    @foreach ($this->categories as $category)
                        <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="form.category_id" />
            </flux:field>

            <flux:field>
                <flux:label>Ubicación (opcional)</flux:label>
                <flux:select variant="listbox" searchable clearable wire:model.live="form.location_id" placeholder="— Sin ubicación (sólo categoría) —">
                    @foreach ($this->locations as $location)
                        <flux:select.option value="{{ $location->id }}">{{ $location->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="form.location_id" />
            </flux:field>
        </div>

        <flux:field>
            <flux:label>Slug</flux:label>
            <flux:input wire:model="form.slug" required />
            <flux:description>
                Se autocompleta como <code>{categoria}-{ubicacion}</code> (o con separador "en" según config). Editable para casos especiales.
            </flux:description>
            <flux:error name="form.slug" />
        </flux:field>

        <div class="grid gap-4 md:grid-cols-2">
            <flux:field>
                <flux:label>Estado</flux:label>
                <flux:select wire:model.live="form.status">
                    @foreach (LandingStatus::cases() as $status)
                        <flux:select.option value="{{ $status->value }}">{{ $status->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:description>Sólo las publicadas responden 200 y salen en el sitemap.</flux:description>
                <flux:error name="form.status" />
            </flux:field>

            <flux:field>
                <flux:label>Fecha de publicación</flux:label>
                <flux:date-picker wire:model.live="form.publish_at" />
                <flux:description>Al fijar una fecha, la landing pasa a "Programada"; al quitarla, vuelve a "Borrador".</flux:description>
                <flux:error name="form.publish_at" />
            </flux:field>
        </div>

        <flux:separator />
        <flux:heading size="lg">SEO</flux:heading>

        <flux:field>
            <flux:label>Title (override)</flux:label>
            <flux:input wire:model="form.title" placeholder="Vacío = se autogenera de categoría + ubicación" />
            <flux:error name="form.title" />
        </flux:field>

        <flux:field>
            <flux:label>Meta descripción</flux:label>
            <flux:textarea wire:model="form.meta_description" rows="3" placeholder="150-160 caracteres" />
            <flux:error name="form.meta_description" />
        </flux:field>

        <flux:separator />
        <flux:heading size="lg">Contenido (JSON)</flux:heading>

        <flux:field>
            <flux:label>Secciones</flux:label>
            <flux:textarea wire:model="form.contentJson" rows="14" class="font-mono text-sm" placeholder='{"hero": {"title": "...", "body": "..."}}' />
            <flux:description>
                JSON con las secciones que la vista pública interpreta. Cada sitio define su propio esquema.
            </flux:description>
            <flux:error name="form.contentJson" />
        </flux:field>

        <div class="flex justify-end gap-3">
            <flux:button :href="route('admin.landings.index')" variant="ghost">Cancelar</flux:button>
            <flux:button type="submit" variant="primary">
                {{ $landing ? 'Guardar cambios' : 'Crear landing' }}
            </flux:button>
        </div>
    </form>
</div>
