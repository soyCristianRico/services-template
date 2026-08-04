{{--
    Los tres puntos de una ficha del admin.

    Aquí viven las acciones que NO son «guardar»: borrar, y lo que venga después.
    Están dentro de la ficha y no en la fila del listado a propósito — borrar un
    registro desde una tabla es un clic de más al lado de un clic de menos, y
    quien borra ya suele venir de haber abierto la ficha para mirarla.

    El contenido es el slot para que cada pantalla ponga sus propias entradas con
    su `wire:click` y su texto de confirmación.
--}}

<flux:dropdown position="bottom" align="end">
    <flux:button variant="ghost" icon="ellipsis-vertical" aria-label="Más acciones" />

    <flux:menu>{{ $slot }}</flux:menu>
</flux:dropdown>
