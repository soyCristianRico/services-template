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

    `breadcrumbs` (Sección > este registro) es la alternativa a `back`: su primer
    tramo lleva siempre al listado, así que hace el trabajo del botón sin ocupar una
    línea aparte. Con `breadcrumbs` no se pasa `back`.

    @param array<int, array{label: string, href?: string}>|null $breadcrumbs
--}}

@props(['back' => null, 'backLabel' => 'Volver', 'breadcrumbs' => null])

<div class="space-y-4">
    @if ($breadcrumbs)
        <flux:breadcrumbs>
            @foreach ($breadcrumbs as $crumb)
                <flux:breadcrumbs.item :href="$crumb['href'] ?? null">{{ $crumb['label'] }}</flux:breadcrumbs.item>
            @endforeach
        </flux:breadcrumbs>
    @elseif ($back)
        <flux:button :href="$back" variant="ghost" size="sm" icon="arrow-left" class="-ms-3">
            {{ $backLabel }}
        </flux:button>
    @endif

    <div class="flex items-start justify-between gap-4">
        <flux:heading size="xl" class="min-w-0 flex-1">{{ $slot }}</flux:heading>

        @isset($actions)
            <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
        @endisset
    </div>
</div>
