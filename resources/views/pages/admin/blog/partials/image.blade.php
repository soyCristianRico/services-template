<section class="space-y-6">
    <flux:heading size="lg">Imagen destacada</flux:heading>

    @if ($this->hero)
        <div class="space-y-3">
            <x-toolkit::image :media="$this->hero" alt="Imagen destacada" sizes="(min-width: 1024px) 768px, 100vw" class="aspect-[16/9] w-full rounded-lg object-cover" />
            <flux:button wire:click="deleteHero" wire:confirm="¿Eliminar la imagen destacada?" variant="ghost" size="sm" icon="trash">
                Eliminar imagen
            </flux:button>
        </div>
    @else
        <flux:text>Sin imagen destacada todavía.</flux:text>
    @endif

    <div>
        <input
            type="file"
            wire:model="newHero"
            accept="image/jpeg,image/png,image/webp"
            class="block w-full text-sm text-zinc-700 file:mr-4 file:rounded file:border-0 file:bg-zinc-900 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-zinc-800">
        <flux:error name="newHero" />
        <div wire:loading wire:target="newHero" class="mt-2 text-sm text-zinc-500">Subiendo…</div>
    </div>
</section>
