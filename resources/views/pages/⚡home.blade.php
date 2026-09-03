<?php

use App\Services\Seo\SeoService;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts.public')]
class extends Component
{
    public function mount(SeoService $seo): void
    {
        $seo->setSEO(
            title: (string) config('app.name'),
            description: (string) config('site.tagline'),
            appendSiteName: false,
        );
    }
};
?>

<div class="mx-auto max-w-3xl px-6 py-24 text-center">
    <flux:heading level="1">{{ config('app.name') }}</flux:heading>

    <flux:text class="mt-4">
        Página por defecto del services-template. Cada sitio que clona este template la reescribe entera
        (hero, secciones, paleta, fuentes). Lo compartido entre sitios vive en <code>app/Services/Seo</code>,
        <code>app/Models</code>, el sitemap y el admin de Livewire.
    </flux:text>

    <div class="mt-8">
        <flux:button href="{{ route('login') }}" variant="primary">Entrar al admin</flux:button>
    </div>
</div>
