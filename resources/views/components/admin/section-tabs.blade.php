{{--
    Pestañas entre pantallas hermanas de una misma sección del menú: las
    preguntas frecuentes y sus categorías, por ejemplo. Lo que se gestiona junto
    se encuentra junto, y el menú lateral no crece con cada tabla auxiliar.

    `tabs`: lista de [etiqueta, nombre de ruta]. Cada pestaña marca como suya
    cualquier ruta que cuelgue de su mismo tronco (`admin.faqs.*`).
--}}

@props(['tabs'])

<flux:navbar class="-mb-px border-b border-zinc-200">
    @foreach ($tabs as [$label, $route])
        <flux:navbar.item :href="route($route)" :current="request()->routeIs(Str::beforeLast($route, '.').'.*')" wire:navigate>
            {{ $label }}
        </flux:navbar.item>
    @endforeach
</flux:navbar>
