@props(['placeholder' => 'Buscar…'])

{{-- Ancho fijo (18 rem) en todos los listados: el buscador no se estira ni se encoge según la pantalla. --}}
<div class="w-72">
    <flux:input type="search" icon="magnifying-glass" :placeholder="$placeholder" {{ $attributes }} />
</div>
