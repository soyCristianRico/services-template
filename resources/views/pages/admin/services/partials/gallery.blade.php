<section class="space-y-6">
    <div class="space-y-1">
        <flux:heading size="lg">Galería</flux:heading>
        <flux:text>JPG, PNG, WEBP o GIF, hasta 5 MB por imagen. Se guardan al subirlas.</flux:text>
    </div>

    <div>
        <input
            type="file"
            wire:model="newImages"
            multiple
            accept="image/*"
            class="block w-full text-sm text-zinc-700 file:mr-4 file:rounded file:border-0 file:bg-zinc-900 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-zinc-800">
        <flux:error name="newImages.*" />

        <div wire:loading wire:target="newImages" class="mt-2 text-sm text-zinc-500">Subiendo…</div>
    </div>

    @if ($this->gallery->isNotEmpty())
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            @foreach ($this->gallery as $media)
                <div wire:key="media-{{ $media->id }}" class="group relative overflow-hidden rounded-lg border border-zinc-200 bg-white">
                    <x-toolkit::image :media="$media" :alt="$media->name" sizes="240px" class="aspect-square w-full object-cover" />
                    <button
                        type="button"
                        wire:click="deleteImage({{ $media->id }})"
                        wire:confirm="¿Eliminar esta imagen?"
                        class="absolute right-2 top-2 rounded-full bg-white/90 p-1 text-zinc-700 opacity-0 shadow transition-opacity hover:bg-red-600 hover:text-white group-hover:opacity-100"
                        aria-label="Eliminar imagen">
                        <flux:icon name="x-mark" variant="micro" />
                    </button>
                </div>
            @endforeach
        </div>
    @else
        <flux:callout icon="photo" color="zinc">Aún no hay imágenes. Sube la primera arriba.</flux:callout>
    @endif
</section>
