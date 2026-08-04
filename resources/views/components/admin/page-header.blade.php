{{--
    Cabecera de una ficha del admin.

    El botón de volver va ARRIBA y a la IZQUIERDA, en su propia línea sobre el
    título: es el patrón que la gente ya trae aprendido de cualquier panel, y
    apunta hacia donde está el listado. Antes vivía a la derecha, compitiendo por
    el sitio con las acciones de la ficha, que es justo donde no hay que buscarlo.

    El `-ms-3` come el relleno propio del botón fantasma para que su texto quede
    a plomo con el título, no sangrado respecto a él.

    Las acciones de la ficha —el menú de tres puntos con «Borrar»— entran por el
    slot `actions` y se quedan a la derecha, a la altura del título.
--}}

@props(['back' => null, 'backLabel' => 'Volver'])

<div class="space-y-4">
    @if ($back)
        <flux:button :href="$back" variant="ghost" size="sm" icon="arrow-left" class="-ms-3">
            {{ $backLabel }}
        </flux:button>
    @endif

    <div class="flex items-start justify-between gap-4">
        <flux:heading size="xl">{{ $slot }}</flux:heading>

        @isset($actions)
            <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
        @endisset
    </div>
</div>
