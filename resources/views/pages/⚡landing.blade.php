<?php

declare(strict_types=1);

use App\Models\Landing;
use App\Models\Page;
use App\Services\Seo\SchemaBuilder;
use App\Services\Admin\AdminBar;
use App\Services\Seo\SeoService;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts.public')]
class extends Component
{
    public ?Page $page = null;

    public ?Landing $landing = null;

    public function mount(string $slug, SeoService $seo, SchemaBuilder $schema, AdminBar $bar): void
    {
        // Page wins over Landing when both share a slug — Pages are the editable
        // singletons (legal, sobre nosotros, gracias) and they should never be
        // shadowed by a programmatic landing.
        $page = Page::active()->where('slug', $slug)->first();
        if ($page !== null) {
            $this->page = $page;
            $bar->editing($page);
            $this->seoForPage($page, $seo);

            return;
        }

        $this->landing = Landing::published()
            ->with(['category.parent', 'location.parent'])
            ->where('slug', $slug)
            ->firstOrFail();

        $bar->editing($this->landing);

        $this->seoForLanding($this->landing, $seo, $schema);
    }

    protected function seoForPage(Page $page, SeoService $seo): void
    {
        $seo->setSEO(
            title: $page->meta_title ?? $page->title,
            description: $page->meta_description ?? Str::limit(strip_tags((string) $page->body), 160),
            url: url('/'.$page->slug),
        );
    }

    protected function seoForLanding(Landing $landing, SeoService $seo, SchemaBuilder $schema): void
    {
        $title = $landing->title ?? $landing->category->name
            .($landing->location ? ' en '.$landing->location->name : '');

        $description = $landing->meta_description
            ?? 'Solicita presupuesto sin compromiso. Respuesta en menos de 15 minutos.';

        $url = url('/'.$landing->slug);

        $seo->setSEO(
            title: $title,
            description: $description,
            url: $url,
            structuredData: [
                $schema->service($landing->category, $url, $landing->location),
                $schema->breadcrumbsForLanding($landing, $url),
            ],
        );
    }

    public function with(): array
    {
        return [
            'categoryCrumbs' => $this->landing?->category->ancestors()->reverse()->values() ?? collect(),
            'locationCrumbs' => $this->landing?->location?->ancestors()->reverse()->values() ?? collect(),
        ];
    }
};
?>

<div>
    @if ($page)
        <article class="mx-auto max-w-3xl px-6 py-16">
            <flux:heading level="1">{{ $page->title }}</flux:heading>

            @if ($page->body)
                <div class="prose prose-zinc mt-8 max-w-none">
                    {!! $page->body !!}
                </div>
            @endif
        </article>
    @else
        <div class="mx-auto max-w-3xl px-6 py-16">
            <nav class="mb-8 text-sm text-zinc-500" aria-label="Breadcrumb">
                <a href="{{ url('/') }}" class="hover:text-zinc-900">Inicio</a>
                @foreach ($categoryCrumbs as $crumb)
                    <span class="mx-2">/</span>
                    <span>{{ $crumb->name }}</span>
                @endforeach
                <span class="mx-2">/</span>
                <span class="text-zinc-900">{{ $landing->category->name }}</span>
                @if ($landing->location)
                    <span class="mx-2">·</span>
                    <span class="text-zinc-900">{{ $landing->location->name }}</span>
                @endif
            </nav>

            <flux:heading level="1">
                {{ $landing->title ?? $landing->category->name . ($landing->location ? ' en ' . $landing->location->name : '') }}
            </flux:heading>

            @if ($landing->meta_description)
                <flux:text class="mt-4 text-lg text-zinc-600">{{ $landing->meta_description }}</flux:text>
            @endif

            @if (! empty($landing->content))
                <div class="mt-12 space-y-12">
                    {{-- Each site reinterprets the sections in $landing->content its own way. --}}
                    @foreach ($landing->content as $sectionKey => $sectionData)
                        <section data-section="{{ $sectionKey }}">
                            @if (is_array($sectionData) && isset($sectionData['title']))
                                <flux:heading level="3">{{ $sectionData['title'] }}</flux:heading>
                            @endif
                            @if (is_array($sectionData) && isset($sectionData['body']))
                                <flux:text class="mt-2">{{ $sectionData['body'] }}</flux:text>
                            @endif
                        </section>
                    @endforeach
                </div>
            @endif

            <div class="mt-12">
                <livewire:lead-form :landing="$landing" />
            </div>
        </div>
    @endif
</div>
