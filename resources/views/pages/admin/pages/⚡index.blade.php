<?php

declare(strict_types=1);

use App\Models\Page;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts.admin')]
class extends Component
{
    public string $search = '';

    public function toggleActive(int $id): void
    {
        $page = Page::findOrFail($id);
        $page->update(['is_active' => ! $page->is_active]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Page>
     */
    #[Computed]
    public function pages(): \Illuminate\Support\Collection
    {
        return Page::query()
            ->when($this->search !== '', fn ($q) => $q->where(function ($q) {
                $q->where('title', 'like', "%{$this->search}%")
                    ->orWhere('slug', 'like', "%{$this->search}%");
            }))
            ->orderBy('title')
            ->get();
    }
};
?>

<div class="space-y-6 p-8">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">Páginas</flux:heading>
        <flux:button :href="route('admin.pages.create')" variant="primary" icon="plus">Nueva página</flux:button>
    </div>

    <flux:text class="text-zinc-600">
        Páginas estáticas con vista compartida: aviso legal, política de privacidad, contacto-gracias, sobre nosotros…
        Para páginas con diseño custom (home, hero del servicio…), edita el Blade directamente.
    </flux:text>

    <flux:input wire:model.live.debounce.300ms="search" type="search" placeholder="Buscar por título o slug…" icon="magnifying-glass" class="max-w-md" />

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Título</flux:table.column>
            <flux:table.column>URL</flux:table.column>
            <flux:table.column>Estado</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->pages as $page)
                <flux:table.row wire:key="page-{{ $page->id }}">
                    <flux:table.cell>
                        <x-admin.record-link :href="route('admin.pages.edit', $page)">{{ $page->title }}</x-admin.record-link>
                    </flux:table.cell>
                    <flux:table.cell>
                        <a href="{{ url('/'.$page->slug) }}" target="_blank" class="text-blue-600 hover:underline">
                            /{{ $page->slug }}
                        </a>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:switch wire:click="toggleActive({{ $page->id }})" :checked="$page->is_active" />
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="3" class="text-center text-zinc-500">
                        No hay páginas. <a href="{{ route('admin.pages.create') }}" class="underline">Crea la primera</a>.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
