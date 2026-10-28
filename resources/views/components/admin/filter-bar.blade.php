{{--
    Barra de filtros de un listado.

    El slot por defecto lleva lo que va siempre a la vista (buscador y, si lo hay,
    un único estado). `advanced` lleva el resto, en un panel lateral abierto con el
    botón «Filtros» y su contador. Cada filtro activo sale como un chip con su ×.

    Props: `chips` — colección de ['key' => ..., 'label' => ...]; `reset` y
    `clearAll` — nombre del método Livewire de «Limpiar filtros» / «Limpiar todo».
    El componente Livewire debe tener `clearFilter(string $key)`.
--}}

@props(['chips' => collect(), 'name' => 'filters', 'reset' => null, 'clearAll' => null])

<div class="space-y-3">
    <div class="flex flex-wrap items-center gap-3">
        <div class="flex min-w-0 flex-1 flex-wrap items-center gap-3">
            {{ $slot }}
        </div>

        @isset($advanced)
            <flux:modal.trigger :name="$name">
                <flux:button variant="subtle" icon="funnel">
                    Filtros
                    @if ($chips->isNotEmpty())
                        <flux:badge size="sm" color="zinc">{{ $chips->count() }}</flux:badge>
                    @endif
                </flux:button>
            </flux:modal.trigger>

            <flux:modal :name="$name" variant="flyout" class="w-full max-w-sm">
                <div class="flex h-full flex-col gap-6">
                    <flux:heading size="lg">Filtros</flux:heading>

                    <div class="flex-1 space-y-5">
                        {{ $advanced }}
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        @if ($reset)
                            <flux:button variant="ghost" wire:click="{{ $reset }}">Limpiar filtros</flux:button>
                        @else
                            <span></span>
                        @endif
                        <flux:modal.close>
                            <flux:button variant="primary">Ver resultados</flux:button>
                        </flux:modal.close>
                    </div>
                </div>
            </flux:modal>
        @endisset
    </div>

    @if ($chips->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2">
            @foreach ($chips as $chip)
                <button
                    type="button"
                    wire:click="clearFilter('{{ $chip['key'] }}')"
                    class="inline-flex items-center gap-1.5 rounded-full border border-zinc-200 bg-white px-3 py-1 text-sm text-zinc-700 hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-200"
                >
                    {{ $chip['label'] }}
                    <flux:icon name="x-mark" variant="micro" class="size-3.5 text-zinc-400" />
                </button>
            @endforeach

            @if ($clearAll)
                <flux:button size="sm" variant="ghost" wire:click="{{ $clearAll }}">Limpiar todo</flux:button>
            @endif
        </div>
    @endif
</div>
