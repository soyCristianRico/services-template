<?php

declare(strict_types=1);

use App\Models\BlogPost;
use App\Models\Lead;
use App\Models\Service;
use App\Services\Admin\DashboardMetrics;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * What the client has to look at today. Each site swaps the cards for its own
 * (its entities, what is missing on them); the catalog cards only show where
 * the site has a catalog.
 */
new
#[Layout('layouts.admin')]
#[Title('Resumen')]
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

    /**
     * @return list<array{week: string, leads: int}>
     */
    #[Computed]
    public function leadsPerWeek(): array
    {
        return app(DashboardMetrics::class)->leadsPerWeek();
    }

    /**
     * Whole-number ticks for the count axis: Flux picks halves when the counts
     * are small, and there is no such thing as half a contact.
     */
    public function countTicks(): string
    {
        $max = max(1, ...array_column($this->leadsPerWeek, 'leads'));
        $step = (int) ceil($max / 4);

        return json_encode(range(0, (int) ceil($max / $step) * $step, $step));
    }

    /**
     * Contacts nobody has answered yet, the oldest first: those are going cold.
     *
     * @return Collection<int, Lead>
     */
    #[Computed]
    public function unansweredLeads(): Collection
    {
        return Lead::new()->oldest()->limit(8)->get();
    }

    #[Computed]
    public function publishedPosts(): int
    {
        return BlogPost::published()->count();
    }
};
?>

<div class="space-y-10 p-8">
    <div class="space-y-1">
        <x-admin.page-header>Hola{{ auth()->user()?->name ? ', '.auth()->user()->name : '' }}</x-admin.page-header>
        <flux:text>Lo que hay que mirar hoy en la web.</flux:text>
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

    <section class="space-y-4">
        <flux:heading size="lg">Contactos que entran, por semana</flux:heading>

        <div class="rounded-xl border border-zinc-200 bg-white p-4">
            <flux:chart :value="$this->leadsPerWeek" class="h-56">
                <flux:chart.svg>
                    <flux:chart.bar field="leads" class="text-accent" radius="4 0" width="60%" />

                    <flux:chart.axis axis="x" field="week">
                        <flux:chart.axis.tick />
                        <flux:chart.axis.line class="text-zinc-200" />
                    </flux:chart.axis>

                    <flux:chart.axis axis="y" :tick-values="$this->countTicks()">
                        <flux:chart.axis.grid class="text-zinc-100" />
                        <flux:chart.axis.tick />
                    </flux:chart.axis>

                    <flux:chart.cursor type="area" />
                </flux:chart.svg>

                <flux:chart.tooltip>
                    <flux:chart.tooltip.heading field="week" />
                    <flux:chart.tooltip.value field="leads" label="Contactos" />
                </flux:chart.tooltip>
            </flux:chart>
        </div>
    </section>

    @if ($this->unansweredLeads->isNotEmpty())
        <section class="space-y-4">
            <div class="flex items-center justify-between gap-4">
                <flux:heading size="lg">Contactos sin responder</flux:heading>
                <flux:button :href="route('admin.leads.index')" variant="ghost" size="sm" icon:trailing="arrow-right">Ver todos</flux:button>
            </div>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Nombre</flux:table.column>
                    <flux:table.column>Correo</flux:table.column>
                    <flux:table.column>Llegó</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->unansweredLeads as $lead)
                        <flux:table.row wire:key="lead-{{ $lead->id }}">
                            <flux:table.cell class="max-w-72">
                                <x-admin.record-link :href="route('admin.leads.show', $lead)" class="block truncate">{{ $lead->name }}</x-admin.record-link>
                            </flux:table.cell>
                            <flux:table.cell class="max-w-64 truncate">{{ $lead->email }}</flux:table.cell>
                            <flux:table.cell class="whitespace-nowrap text-zinc-500">{{ $lead->created_at?->diffForHumans() }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </section>
    @endif

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
