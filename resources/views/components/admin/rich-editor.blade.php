{{--
    El campo de texto rico del admin: los cuerpos y las descripciones que se
    guardan como HTML y se pintan con `{!! !!}` en la web pública.

    La barra de formato no va fija arriba, va flotando sobre la selección
    —`floating-toolbar.js` la coloca—, así que el campo se lee como un texto
    normal mientras escribes. El borde del campo se queda: esto sigue siendo una
    casilla de un formulario y tiene que parecerlo.

    La lista de botones es corta a propósito. Sólo lo que la tipografía de la
    web sabe peinar: encabezados, negrita, cursiva, subrayado, listas y enlaces.
    Lo que no está en esa hoja —tablas, alineaciones, colores— saldría en la web
    sin estilo ninguno.

    Es el único sitio del admin donde se declara un editor, y hay un test que
    falla si alguien mete un `<flux:editor>` suelto en una pantalla: la gracia
    está en que todos los campos de escribir se comporten igual.
--}}

@props([
    'toolbar' => 'heading | bold italic underline | bullet ordered | link',
])

<flux:editor {{ $attributes }}>
    <flux:editor.toolbar :items="$toolbar" class="floating-toolbar" />

    <flux:editor.content />
</flux:editor>
