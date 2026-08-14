{{--
    La barra que sólo ve quien tiene sesión abierta, encima de todo lo demás.

    Va en el flujo y no fijada: al bajar por la página desaparece con el resto
    de la cabecera y la web se lee entera sin nada por encima. Para volver a
    usarla se sube, que es donde ya estaba.

    De lo que hay aquí, lo que importa es «Editar …»: el resto acompaña. Ese
    botón sale sólo si la pantalla que estás viendo ha dicho qué registro pinta;
    si no, la barra sigue ahí con lo demás.

    Para un visitante no se imprime nada de esto: sin sesión no hay barra, así
    que el HTML público no lleva ni una etiqueta de más ni menciona el panel.
--}}

@inject('adminBar', App\Services\Admin\AdminBar::class)

@if ($adminBar->visible())
    @php
        $editUrl = $adminBar->editUrl();
        $creatable = $adminBar->creatable();
        $user = auth()->user();
        $link = 'inline-flex items-center gap-1.5 rounded px-2 py-1 font-medium text-zinc-300 transition hover:bg-white/10 hover:text-white';
    @endphp

    <div data-admin-bar class="bg-zinc-900 text-[0.8125rem] print:hidden">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-1 px-6 py-0">
            @if ($editUrl)
                <a href="{{ $editUrl }}" class="{{ $link }} bg-white/10 text-white">
                    <flux:icon name="pencil-square" variant="micro" class="size-4" />
                    {{ $adminBar->editLabel() }}
                </a>
            @endif

            <a href="{{ route('admin.dashboard') }}" class="{{ $link }}">
                <flux:icon name="squares-2x2" variant="micro" class="size-4" />
                Panel
            </a>

            @if ($creatable)
                <flux:dropdown position="bottom" align="start">
                    <button type="button" class="{{ $link }}">
                        <flux:icon name="plus" variant="micro" class="size-4" />
                        Crear
                        <flux:icon name="chevron-down" variant="micro" class="size-3" />
                    </button>

                    <flux:menu>
                        @foreach ($creatable as $item)
                            <flux:menu.item href="{{ $item['url'] }}">{{ $item['label'] }}</flux:menu.item>
                        @endforeach
                    </flux:menu>
                </flux:dropdown>
            @endif

            <div class="ms-auto flex items-center gap-1">
                <flux:dropdown position="bottom" align="end">
                    <button type="button" class="{{ $link }}">
                        {{ $user?->name }}
                        <flux:icon name="chevron-down" variant="micro" class="size-3" />
                    </button>

                    <flux:menu>
                        <flux:menu.item href="{{ route('admin.profile') }}" icon="user">Perfil</flux:menu.item>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" variant="danger">
                                Cerrar sesión
                            </flux:menu.item>
                        </form>
                    </flux:menu>
                </flux:dropdown>
            </div>
        </div>
    </div>
@endif
