{{--
    Icono de información con su texto. Va AL LADO de la etiqueta del campo, nunca
    dentro de `<flux:label>`: dentro, el clic en el icono activa el campo asociado.
--}}

@props(['text'])

<flux:tooltip toggleable position="right">
    <flux:button icon="information-circle" size="xs" variant="subtle" class="mt-[5px]" />
    <flux:tooltip.content class="max-w-[20rem]">
        <p class="text-xs">{{ $text }}</p>
    </flux:tooltip.content>
</flux:tooltip>
