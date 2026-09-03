<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Seo\PublicPageDirectory;
use Illuminate\Http\Response;
use Toolkit\AgentMarkdown\LlmsTxtBuilder;

class LlmsTxtController
{
    public function index(PublicPageDirectory $directory): Response
    {
        return LlmsTxtBuilder::make((string) config('app.name'), (string) config('site.tagline'))
            ->url(route('home'))
            ->note('Cada página también está disponible en Markdown: pide la misma URL con la cabecera `Accept: text/markdown`.')
            ->section('Páginas', $directory->pages())
            ->section('Aterrizajes', $directory->landings())
            ->section('Blog', $directory->blogPosts())
            ->toResponse();
    }
}
