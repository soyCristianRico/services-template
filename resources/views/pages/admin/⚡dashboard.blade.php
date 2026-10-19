<?php

declare(strict_types=1);

use App\Models\BlogPost;
use App\Models\Lead;
use App\Models\Service;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * What the client has to look at today. Each site swaps the cards for its own
 * (its entities, what is missing on them); the catalog cards only show where
 * the site has a catalog.
 */
new
#[Layout('layouts.admin')]
class extends Component
{
    #[Computed]
    public function newLeads(): int
    {
        return Lead::new()->count();
    }

    #[Computed]
    public function hasCatalog(): bool
    {
        return (bool) config('site.catalog');
    }

    #[Computed]
    public function activeServices(): int
    {
        return Service::active()->count();
    }

    /**
     * Services with no photo: their card shows empty on the site.
     *
     * @return Collection<int, Service>
     */
    #[Computed]
    public function servicesWithoutPhotos(): Collection
    {
        return Service::query()
            ->whereDoesntHave('media', fn ($query) => $query->where('collection_name', 'gallery'))
            ->ordered()
            ->get(['id', 'name', 'is_active']);
    }

    #[Computed]
    public function publishedPosts(): int
    {
        return BlogPost::published()->count();
    }
};
?>

<div class="space-y-8 p-8">
    <div>
        <flux:heading size="xl">Hola{{ auth()->user()?->name ? ', '.auth()->user()->name : '' }}</flux:heading>
        <flux:text class="mt-1 text-zinc-500">Lo que hay que mirar hoy en la web.</flux:text>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <a href="{{ route('admin.leads.index') }}" wire:navigate @class([
            'rounded-xl border p-6 transition hover:shadow-sm',
            'border-amber-300 bg-amber-50 hover:border-amber-400' => $this->newLeads > 0,
            'border-zinc-200 bg-white hover:border-zinc-300' => $this->newLeads === 0,
        ])>
            <div class="flex items-center gap-2 text-zinc-500">
                <flux:icon.inbox variant="micro" class="size-4" />
                <span class="text-sm">Contactos sin responder</span>
            </div>
            <div class="mt-2 text-3xl font-semibold text-zinc-900">{{ $this->newLeads }}</div>
            <div class="mt-1 text-sm text-zinc-500">{{ $this->newLeads > 0 ? 'Esperan respuesta' : 'Todo respondido' }}</div>
        </a>

        @if ($this->hasCatalog)
            <a href="{{ route('admin.services.index') }}" wire:navigate @class([
                'rounded-xl border p-6 transition hover:shadow-sm',
                'border-amber-300 bg-amber-50 hover:border-amber-400' => $this->servicesWithoutPhotos->isNotEmpty(),
                'border-zinc-200 bg-white hover:border-zinc-300' => $this->servicesWithoutPhotos->isEmpty(),
            ])>
                <div class="flex items-center gap-2 text-zinc-500">
                    <flux:icon.squares-2x2 variant="micro" class="size-4" />
                    <span class="text-sm">Servicios activos</span>
                </div>
                <div class="mt-2 text-3xl font-semibold text-zinc-900">{{ $this->activeServices }}</div>
                <div class="mt-1 text-sm text-zinc-500">
                    {{ $this->servicesWithoutPhotos->isEmpty() ? 'Todos tienen foto' : $this->servicesWithoutPhotos->count().' sin foto' }}
                </div>
            </a>
        @endif

        <a href="{{ route('admin.blog.index') }}" wire:navigate class="rounded-xl border border-zinc-200 bg-white p-6 transition hover:border-zinc-300 hover:shadow-sm">
            <div class="flex items-center gap-2 text-zinc-500">
                <flux:icon.newspaper variant="micro" class="size-4" />
                <span class="text-sm">Entradas publicadas</span>
            </div>
            <div class="mt-2 text-3xl font-semibold text-zinc-900">{{ $this->publishedPosts }}</div>
            <div class="mt-1 text-sm text-zinc-500">{{ $this->publishedPosts === 0 ? 'Aún no hay ninguna' : 'en el blog' }}</div>
        </a>
    </div>

    @if ($this->hasCatalog && $this->servicesWithoutPhotos->isNotEmpty())
        <div class="space-y-4 rounded-xl border border-zinc-200 bg-white p-6">
            <div>
                <flux:heading size="lg">Servicios sin foto</flux:heading>
                <flux:text class="mt-1 text-zinc-500">Sin imagen, su tarjeta sale vacía en la web. Ábrelos y sube una.</flux:text>
            </div>

            <ul class="divide-y divide-zinc-100">
                @foreach ($this->servicesWithoutPhotos as $service)
                    <li wire:key="no-photo-{{ $service->id }}" class="flex items-center justify-between gap-4 py-3">
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-zinc-900">{{ $service->name }}</span>
                            @unless ($service->is_active)
                                <flux:badge size="sm" color="zinc">Inactivo</flux:badge>
                            @endunless
                        </div>
                        <flux:button :href="route('admin.services.edit', $service)" size="sm" variant="ghost" icon="photo">Subir foto</flux:button>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
