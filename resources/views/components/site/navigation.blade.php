@props(['items'])

@if ($items->isNotEmpty())
    <nav {{ $attributes->merge(['class' => 'flex items-center gap-6 text-sm']) }}>
        @foreach ($items as $item)
            <div wire:key="nav-{{ $item->id }}" class="relative" @if ($item->children->isNotEmpty()) x-data="{ open: false }" x-on:mouseleave="open = false" @endif>
                @if ($item->children->isEmpty())
                    <a href="{{ $item->url }}" wire:navigate
                        class="text-muted-foreground transition hover:text-foreground">{{ $item->label }}</a>
                @else
                    <button type="button" x-on:mouseenter="open = true" x-on:click="open = ! open"
                        x-bind:aria-expanded="open ? 'true' : 'false'"
                        class="flex items-center gap-1 text-muted-foreground transition hover:text-foreground">
                        {{ $item->label }}
                        <flux:icon.chevron-down class="size-4" />
                    </button>

                    <div x-cloak x-show="open" x-transition.opacity
                        class="absolute left-0 top-full z-20 mt-2 min-w-48 rounded-lg border border-border bg-surface py-2 shadow-lg">
                        @if ($item->url)
                            <a href="{{ $item->url }}" wire:navigate
                                class="block px-4 py-2 text-muted-foreground transition hover:bg-surface-muted hover:text-foreground">{{ $item->label }}</a>
                        @endif

                        @foreach ($item->children as $child)
                            <a href="{{ $child->url }}" wire:navigate wire:key="nav-{{ $child->id }}"
                                class="block px-4 py-2 text-muted-foreground transition hover:bg-surface-muted hover:text-foreground">{{ $child->label }}</a>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </nav>
@endif
