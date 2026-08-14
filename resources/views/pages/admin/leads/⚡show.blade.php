<?php

declare(strict_types=1);

use App\Enums\LeadStatus;
use App\Models\Lead;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts.admin')]
class extends Component
{
    public Lead $lead;

    public string $status = '';

    public function mount(Lead $lead): void
    {
        $this->lead = $lead->load('landing.category', 'landing.location');
        $this->status = $lead->status->value;
    }

    public function updateStatus(): void
    {
        $this->validate([
            'status' => ['required', \Illuminate\Validation\Rule::enum(LeadStatus::class)],
        ]);

        $this->lead->update(['status' => LeadStatus::from($this->status)]);
        $this->lead->refresh();
        session()->flash('status', 'Estado actualizado a '.$this->lead->status->label().'.');
    }

    public function deleteLead(): void
    {
        $this->lead->delete();
        $this->redirectRoute('admin.leads.index');
    }
};
?>

<div class="mx-auto max-w-3xl space-y-6 p-8">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">Lead #{{ $lead->id }}</flux:heading>
        <flux:button :href="route('admin.leads.index')" variant="ghost" icon="arrow-left">Volver</flux:button>
    </div>

    @session('status')
        <flux:callout icon="check-circle" color="green">{{ $value }}</flux:callout>
    @endsession

    <flux:card>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <flux:text class="text-xs uppercase tracking-wide text-zinc-500">Nombre</flux:text>
                <flux:text class="font-medium">{{ $lead->name }}</flux:text>
            </div>
            <div>
                <flux:text class="text-xs uppercase tracking-wide text-zinc-500">Email</flux:text>
                <a href="mailto:{{ $lead->email }}" class="font-medium text-blue-600 hover:underline">{{ $lead->email }}</a>
            </div>
            <div>
                <flux:text class="text-xs uppercase tracking-wide text-zinc-500">Teléfono</flux:text>
                <flux:text class="font-medium">{{ $lead->phone ?? '—' }}</flux:text>
            </div>
            <div>
                <flux:text class="text-xs uppercase tracking-wide text-zinc-500">Fecha</flux:text>
                <flux:text class="font-medium">{{ $lead->created_at?->format('d/m/Y H:i') }}</flux:text>
            </div>
        </div>

        @if ($lead->message)
            <div class="mt-6">
                <flux:text class="text-xs uppercase tracking-wide text-zinc-500">Mensaje</flux:text>
                <flux:text class="mt-1 whitespace-pre-line">{{ $lead->message }}</flux:text>
            </div>
        @endif
    </flux:card>

    @if ($lead->landing)
        <flux:card>
            <flux:heading size="lg">Landing de origen</flux:heading>
            <div class="mt-3 space-y-2 text-sm">
                <div>
                    <span class="text-zinc-500">URL:</span>
                    <a href="{{ url('/'.$lead->landing->slug) }}" target="_blank" class="text-blue-600 hover:underline">
                        {{ url('/'.$lead->landing->slug) }}
                    </a>
                </div>
                <div><span class="text-zinc-500">Categoría:</span> {{ $lead->landing->category?->name ?? '—' }}</div>
                <div><span class="text-zinc-500">Ubicación:</span> {{ $lead->landing->location?->name ?? '— (sólo servicio)' }}</div>
            </div>
        </flux:card>
    @elseif ($lead->source_url)
        <flux:card>
            <flux:heading size="lg">Origen</flux:heading>
            <flux:text class="mt-2 text-sm">
                <a href="{{ $lead->source_url }}" target="_blank" class="text-blue-600 hover:underline">
                    {{ $lead->source_url }}
                </a>
            </flux:text>
        </flux:card>
    @endif

    {{-- Cómo llegó a la web, que no es lo mismo que en qué formulario escribió.
         Un lead nacido fuera de una visita no tiene sesión detrás y no trae
         nada de esto: la tarjeta no se pinta. --}}
    @if ($lead->channel)
        <flux:card>
            <div class="flex items-center gap-3">
                <flux:heading size="lg">Atribución</flux:heading>
                <flux:badge :color="$lead->channel->color()" size="sm">{{ $lead->channel->label() }}</flux:badge>
            </div>

            <div class="mt-3 space-y-2 text-sm">
                @if ($lead->utm_source || $lead->utm_medium || $lead->utm_campaign)
                    <div><span class="text-zinc-500">Fuente / medio:</span> {{ $lead->utm_source ?? '—' }} / {{ $lead->utm_medium ?? '—' }}</div>
                    @if ($lead->utm_campaign)
                        <div><span class="text-zinc-500">Campaña:</span> {{ $lead->utm_campaign }}</div>
                    @endif
                    @if ($lead->utm_term || $lead->utm_content)
                        <div><span class="text-zinc-500">Término / contenido:</span> {{ $lead->utm_term ?? '—' }} / {{ $lead->utm_content ?? '—' }}</div>
                    @endif
                @endif

                @if ($lead->click_id)
                    <div><span class="text-zinc-500">ID de clic:</span> <code class="break-all text-xs">{{ $lead->click_id }}</code></div>
                @endif

                <div>
                    <span class="text-zinc-500">Venía de:</span>
                    @if ($lead->referrer)
                        <a href="{{ $lead->referrer }}" target="_blank" rel="noopener noreferrer nofollow" class="break-all text-blue-600 hover:underline">
                            {{ $lead->referrer }}
                        </a>
                    @else
                        <span class="text-zinc-400">sin referrer (directo, o una app que no lo manda)</span>
                    @endif
                </div>

                @if ($lead->landing_url)
                    <div>
                        <span class="text-zinc-500">Entró por:</span>
                        <a href="{{ $lead->landing_url }}" target="_blank" class="break-all text-blue-600 hover:underline">
                            {{ $lead->landing_url }}
                        </a>
                    </div>
                @endif
            </div>
        </flux:card>
    @endif

    @if (! empty($lead->payload))
        <flux:card>
            <flux:heading size="lg">Datos extra</flux:heading>
            <pre class="mt-2 overflow-x-auto rounded bg-zinc-50 p-3 text-xs">{{ json_encode($lead->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </flux:card>
    @endif

    <flux:card>
        <flux:heading size="lg">Cambiar estado</flux:heading>
        <form wire:submit="updateStatus" class="mt-4 flex items-end gap-3">
            <flux:field class="flex-1">
                <flux:select wire:model="status">
                    @foreach (LeadStatus::cases() as $option)
                        <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="status" />
            </flux:field>
            <flux:button type="submit" variant="primary">Guardar</flux:button>
        </form>
    </flux:card>

    <details class="rounded-lg border border-zinc-200 p-4">
        <summary class="cursor-pointer text-sm font-medium text-zinc-700">Detalles técnicos</summary>
        <div class="mt-3 space-y-1 text-xs text-zinc-600">
            <div><span class="text-zinc-400">IP:</span> <code>{{ $lead->ip ?? '—' }}</code></div>
            <div><span class="text-zinc-400">User-Agent:</span> <code class="break-all">{{ $lead->user_agent ?? '—' }}</code></div>
        </div>
    </details>

    <div class="flex justify-end">
        <flux:button
            wire:click="deleteLead"
            wire:confirm="¿Eliminar este lead? Acción irreversible."
            variant="danger"
            icon="trash">
            Eliminar lead
        </flux:button>
    </div>
</div>
