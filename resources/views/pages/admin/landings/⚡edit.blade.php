<?php

declare(strict_types=1);

use App\Enums\AdminSection;
use App\Enums\LandingStatus;
use App\Livewire\Forms\Catalog\LandingForm;
use App\Models\Category;
use App\Models\Landing;
use App\Models\Location;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.admin')]
#[Title('Landing')]
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

        if (session()->has('status')) {
            Flux::toast(text: session('status'), variant: 'success');
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

        if ($this->landing) {
            $this->landing->refresh();
            Flux::toast(text: 'Landing guardada.', variant: 'success');

            return;
        }

        session()->flash('status', 'Landing creada.');

        $this->redirectRoute('admin.landings.edit', $landing);
    }

    /**
     * @return list<LandingStatus>
     */
    public function statuses(): array
    {
        return LandingStatus::cases();
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
     * @return Collection<int, Location>
     */
    #[Computed]
    public function locations(): Collection
    {
        return Location::orderBy('name')->get(['id', 'name']);
    }

    public function unpublish(): void
    {
        $this->landing?->update([
            'status' => LandingStatus::Draft,
            'publish_at' => null,
        ]);

        $this->refreshLanding();

        Flux::toast(text: 'Landing despublicada.', variant: 'success');
    }

    public function publishNow(): void
    {
        $this->landing?->update([
            'status' => LandingStatus::Published,
            'publish_at' => null,
        ]);

        $this->refreshLanding();

        Flux::toast(text: 'Landing publicada.', variant: 'success');
    }

    /**
     * Reloads the record and keeps the form's status and date in step with it,
     * so a later Guardar does not undo a publish from the menu.
     */
    protected function refreshLanding(): void
    {
        if ($this->landing === null) {
            return;
        }

        $this->landing->refresh();
        $this->form->status = $this->landing->status;
        $this->form->publish_at = $this->landing->publish_at?->format('Y-m-d');
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

<div class="mx-auto max-w-2xl space-y-10 p-8 pb-28">
    <x-admin.page-header :breadcrumbs="[
        ['label' => AdminSection::Landings->label(), 'href' => route('admin.landings.index')],
        ['label' => $landing ? '/'.$landing->slug : 'Nueva landing'],
    ]">
        {{ $landing ? '/'.$landing->slug : 'Nueva landing' }}

        @if ($landing)
            <x-slot:actions>
                <flux:badge :color="$landing->status->color()">{{ $landing->status->label() }}</flux:badge>
                <flux:button :href="url('/'.$landing->slug)" target="_blank" variant="ghost" size="sm" icon="arrow-top-right-on-square" aria-label="Ver en la web" />
                <x-admin.record-menu>
                    @if ($landing->isPublished())
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

    <section class="space-y-6">
        <flux:heading size="lg">Página</flux:heading>

        <div class="grid gap-6 md:grid-cols-2">
            <flux:field>
                <flux:label>Categoría</flux:label>
                <flux:select variant="listbox" searchable wire:model.live="form.category_id" placeholder="Elige la categoría">
                    @foreach ($this->categories as $category)
                        <flux:select.option :value="$category->id">{{ $category->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="form.category_id" />
            </flux:field>

            <flux:field>
                <flux:label badge="Opcional">Ubicación</flux:label>
                <flux:select variant="listbox" searchable clearable wire:model.live="form.location_id" placeholder="Sin ubicación (sólo categoría)">
                    @foreach ($this->locations as $location)
                        <flux:select.option :value="$location->id">{{ $location->name }}</flux:select.option>
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
    </section>

    <section class="space-y-6">
        <flux:heading size="lg">Publicación</flux:heading>

        <div class="grid gap-6 md:grid-cols-2">
            <flux:field>
                <flux:label>Estado</flux:label>
                <flux:select variant="listbox" wire:model.live="form.status">
                    @foreach ($this->statuses() as $status)
                        <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:description>Sólo las publicadas responden 200 y salen en el sitemap.</flux:description>
                <flux:error name="form.status" />
            </flux:field>

            <flux:field>
                <flux:label badge="Opcional">Fecha de publicación</flux:label>
                <flux:input type="date" wire:model.live="form.publish_at" />
                <flux:description>Al fijar una fecha, la landing pasa a "Programada"; al quitarla, vuelve a "Borrador".</flux:description>
                <flux:error name="form.publish_at" />
            </flux:field>
        </div>
    </section>

    <section class="space-y-6">
        <flux:heading size="lg">SEO</flux:heading>

        <flux:field>
            <flux:label badge="Opcional">Title</flux:label>
            <flux:input wire:model="form.title" placeholder="Vacío = se autogenera de categoría + ubicación" />
            <flux:error name="form.title" />
        </flux:field>

        <flux:field>
            <flux:label badge="Opcional">Meta descripción</flux:label>
            <flux:textarea wire:model="form.meta_description" rows="3" placeholder="150-160 caracteres" />
            <flux:error name="form.meta_description" />
        </flux:field>
    </section>

    <section class="space-y-6">
        <flux:heading size="lg">Contenido (JSON)</flux:heading>

        <flux:field>
            <flux:label badge="Opcional">Secciones</flux:label>
            <flux:textarea wire:model="form.contentJson" rows="14" class="font-mono text-sm" placeholder='{"hero": {"title": "...", "body": "..."}}' />
            <flux:description>
                JSON con las secciones que la vista pública interpreta. Cada sitio define su propio esquema.
            </flux:description>
            <flux:error name="form.contentJson" />
        </flux:field>
    </section>

    <div class="fixed inset-x-0 bottom-0 z-10 border-t border-zinc-200 bg-zinc-50 lg:start-64">
        <div class="mx-auto flex max-w-2xl justify-end gap-3 px-8 py-4">
            <flux:button :href="route('admin.landings.index')" variant="ghost">Cancelar</flux:button>
            <flux:button wire:click="save" variant="primary">{{ $landing ? 'Guardar' : 'Crear landing' }}</flux:button>
        </div>
    </div>
</div>
