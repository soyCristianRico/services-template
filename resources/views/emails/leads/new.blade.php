<x-mail::message>
# Nuevo lead recibido

**Nombre:** {{ $lead->name }}
**Email:** {{ $lead->email }}
@if ($lead->phone)
**Teléfono:** {{ $lead->phone }}
@endif

@if ($lead->message)
**Mensaje:**

{{ $lead->message }}
@endif

@if ($lead->landing)
**Página:** [{{ $lead->landing->title ?? $lead->landing->slug }}]({{ url('/'.$lead->landing->slug) }})
@elseif ($lead->source_url)
**Origen:** {{ $lead->source_url }}
@endif

@if (! empty($lead->payload))
**Datos extra:**
@foreach ($lead->payload as $key => $value)
- **{{ $key }}:** {{ is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE) }}
@endforeach
@endif

<x-mail::button :url="url('/admin/leads/'.$lead->id)">
Ver en el admin
</x-mail::button>

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>
