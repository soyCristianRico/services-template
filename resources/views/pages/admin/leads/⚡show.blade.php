<?php

declare(strict_types=1);

use App\Enums\LeadStatus;
use App\Models\Lead;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.admin')]
#[Title('Contacto')]
class extends Component
{
    public Lead $lead;

    public string $status = '';

    public function mount(Lead $lead): void
    {
        $this->lead = $lead->load('landing.category', 'landing.location');
        $this->status = $lead->status->value;
    }

    /**
     * The status is the screen's main action: picking one saves it.
     */
    public function updatedStatus(): void
    {
        $this->updateStatus();
    }

    public function updateStatus(): void
    {
        $this->validate([
            'status' => ['required', Rule::enum(LeadStatus::class)],
        ]);

        $this->lead->update(['status' => LeadStatus::from($this->status)]);
        $this->lead->refresh();

        Flux::toast(text: 'Estado actualizado a '.$this->lead->status->label().'.', variant: 'success');
    }

    public function deleteLead(): void
    {
        $this->lead->delete();

        session()->flash('status', 'Contacto eliminado.');

        $this->redirectRoute('admin.leads.index', navigate: true);
    }

    /**
     * @return list<LeadStatus>
     */
    public function statuses(): array
    {
        return LeadStatus::cases();
    }

    #[Computed]
    public function payloadJson(): string
    {
        return (string) json_encode($this->lead->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
};
?>

<div class="mx-auto max-w-3xl space-y-10 p-8">
    <x-admin.page-header :breadcrumbs="[
        ['label' => 'Contactos', 'href' => route('admin.leads.index')],
        ['label' => $lead->name],
    ]">
        {{ $lead->name }}

        <x-slot:actions>
            <div class="w-40">
                <flux:select variant="listbox" wire:model.live="status" aria-label="Estado">
                    @foreach ($this->statuses() as $option)
                        <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <x-admin.record-menu>
                <flux:menu.item
                    wire:click="deleteLead"
                    wire:confirm="¿Eliminar este contacto? Acción irreversible."
                    icon="trash" variant="danger">
                    Borrar
                </flux:menu.item>
            </x-admin.record-menu>
        </x-slot:actions>
    </x-admin.page-header>

    <flux:error name="status" />

    <section class="space-y-4">
        <flux:heading size="lg">Datos de contacto</flux:heading>

        <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-zinc-500">Email</dt>
                <dd><a href="mailto:{{ $lead->email }}" class="font-medium text-blue-600 hover:underline">{{ $lead->email }}</a></dd>
            </div>
            <div>
                <dt class="text-zinc-500">Teléfono</dt>
                <dd class="font-medium">{{ $lead->phone ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-zinc-500">Fecha</dt>
                <dd class="font-medium">{{ $lead->created_at?->format('d/m/Y H:i') }}</dd>
            </div>
        </dl>

        @if ($lead->message)
            <div class="text-sm">
                <div class="text-zinc-500">Mensaje</div>
                <p class="mt-1 whitespace-pre-line">{{ $lead->message }}</p>
            </div>
        @endif
    </section>

    @if ($lead->landing)
        <section class="space-y-4">
            <flux:heading size="lg">Landing de origen</flux:heading>

            <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <dt class="text-zinc-500">URL</dt>
                    <dd><a href="{{ url('/'.$lead->landing->slug) }}" target="_blank" class="break-all text-blue-600 hover:underline">{{ url('/'.$lead->landing->slug) }}</a></dd>
                </div>
                <div><dt class="text-zinc-500">Categoría</dt><dd>{{ $lead->landing->category?->name ?? '—' }}</dd></div>
                <div><dt class="text-zinc-500">Ubicación</dt><dd>{{ $lead->landing->location?->name ?? '— (sólo servicio)' }}</dd></div>
            </dl>
        </section>
    @elseif ($lead->source_url)
        <section class="space-y-4">
            <flux:heading size="lg">Origen</flux:heading>

            <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <dt class="text-zinc-500">Página</dt>
                    <dd><a href="{{ $lead->source_url }}" target="_blank" class="break-all text-blue-600 hover:underline">{{ $lead->source_url }}</a></dd>
                </div>
            </dl>
        </section>
    @endif

    {{-- Cómo llegó a la web, que no es lo mismo que en qué formulario escribió.
         Un lead nacido fuera de una visita no tiene sesión detrás y no trae
         nada de esto: la sección no se pinta. --}}
    @if ($lead->channel)
        <section class="space-y-4">
            <div class="flex items-center gap-3">
                <flux:heading size="lg">Atribución</flux:heading>
                <flux:badge :color="$lead->channel->color()" size="sm">{{ $lead->channel->label() }}</flux:badge>
            </div>

            <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                @if ($lead->utm_source || $lead->utm_medium || $lead->utm_campaign)
                    <div><dt class="text-zinc-500">Fuente / medio</dt><dd>{{ $lead->utm_source ?? '—' }} / {{ $lead->utm_medium ?? '—' }}</dd></div>
                    @if ($lead->utm_campaign)
                        <div><dt class="text-zinc-500">Campaña</dt><dd>{{ $lead->utm_campaign }}</dd></div>
                    @endif
                    @if ($lead->utm_term || $lead->utm_content)
                        <div><dt class="text-zinc-500">Término / contenido</dt><dd>{{ $lead->utm_term ?? '—' }} / {{ $lead->utm_content ?? '—' }}</dd></div>
                    @endif
                @endif

                @if ($lead->click_id)
                    <div class="sm:col-span-2"><dt class="text-zinc-500">ID de clic</dt><dd><code class="break-all text-xs">{{ $lead->click_id }}</code></dd></div>
                @endif

                <div class="sm:col-span-2">
                    <dt class="text-zinc-500">Venía de</dt>
                    <dd>
                        @if ($lead->referrer)
                            <a href="{{ $lead->referrer }}" target="_blank" rel="noopener noreferrer nofollow" class="break-all text-blue-600 hover:underline">{{ $lead->referrer }}</a>
                        @else
                            <span class="text-zinc-400">sin referrer (directo, o una app que no lo manda)</span>
                        @endif
                    </dd>
                </div>

                @if ($lead->landing_url)
                    <div class="sm:col-span-2">
                        <dt class="text-zinc-500">Entró por</dt>
                        <dd><a href="{{ $lead->landing_url }}" target="_blank" class="break-all text-blue-600 hover:underline">{{ $lead->landing_url }}</a></dd>
                    </div>
                @endif
            </dl>
        </section>
    @endif

    @if (! empty($lead->payload))
        <section class="space-y-4">
            <flux:heading size="lg">Datos extra</flux:heading>
            <pre class="overflow-x-auto rounded-lg bg-zinc-50 p-3 text-xs">{{ $this->payloadJson }}</pre>
        </section>
    @endif

    <section class="space-y-4">
        <flux:heading size="lg">Detalles técnicos</flux:heading>

        <dl class="space-y-2 text-xs text-zinc-600">
            <div><dt class="inline text-zinc-400">IP:</dt> <dd class="inline"><code>{{ $lead->ip ?? '—' }}</code></dd></div>
            <div><dt class="inline text-zinc-400">User-Agent:</dt> <dd class="inline"><code class="break-all">{{ $lead->user_agent ?? '—' }}</code></dd></div>
        </dl>
    </section>
</div>
