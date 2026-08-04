{{--
    La primera columna de un listado del admin: el nombre del registro ES el
    enlace a su ficha. Antes había que cruzar la fila entera hasta un lápiz al
    final, con la mira puesta en un icono de 12px; ahora el destino es lo que ya
    estabas leyendo.

    Va en `font-medium` y en el color del texto fuerte —no en azul de enlace—
    porque es el título de la fila, no una referencia dentro de una frase: el
    subrayado al pasar por encima es lo que declara que se puede pulsar.
--}}

@props(['href'])

<a href="{{ $href }}" {{ $attributes->class('font-medium text-zinc-800 hover:underline') }}>
    {{ $slot }}
</a>
