<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Landing;
use App\Models\Page;
use App\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * La franja que sólo ve quien tiene sesión abierta, encima de la web pública.
 *
 * Cada pantalla dice qué registro enseña —`$bar->editing($post)` en su
 * `mount()`— y la barra lo convierte en un enlace. No se adivina nada de la
 * URL: la que no dice nada se queda sin botón y no rompe nada.
 *
 * Va como `scoped` porque quien escribe y quien lee son dos: la pantalla monta
 * y se pinta antes que su layout, y con dos instancias la barra saldría vacía.
 */
class AdminBar
{
    /**
     * Una entrada por tipo, y de ella salen «Editar …» y «Crear …»: así uno no
     * puede estar accesible por un lado y faltar por el otro. El orden es el
     * del menú. La ruta es el tronco; `.edit` y `.create` cuelgan de él.
     *
     * @var array<class-string<Model>, array{route: string, noun: string}>
     */
    protected const RECORDS = [
        Service::class => ['route' => 'admin.services', 'noun' => 'servicio'],
        Category::class => ['route' => 'admin.categories', 'noun' => 'categoría'],
        BlogPost::class => ['route' => 'admin.blog', 'noun' => 'artículo'],
        Page::class => ['route' => 'admin.pages', 'noun' => 'página'],
        Landing::class => ['route' => 'admin.landings', 'noun' => 'landing'],
    ];

    protected ?Model $record = null;

    public function editing(?Model $record): void
    {
        $this->record = $record;
    }

    public function record(): ?Model
    {
        return $this->record;
    }

    public function visible(): bool
    {
        return Auth::check();
    }

    /**
     * El enlace a la pantalla del registro, o null cuando no hay nada que
     * editar aquí o no hay pantalla para ello en este sitio.
     */
    public function editUrl(): ?string
    {
        $entry = $this->entry();

        return $entry === null || ! Route::has($entry['route'].'.edit')
            ? null
            : route($entry['route'].'.edit', $this->record);
    }

    public function editLabel(): ?string
    {
        $entry = $this->entry();

        return $entry !== null && $this->editUrl() !== null
            ? 'Editar '.$entry['noun']
            : null;
    }

    /**
     * @return list<array{label: string, url: string}>
     */
    public function creatable(): array
    {
        $items = [];

        foreach (self::RECORDS as $entry) {
            if (! Route::has($entry['route'].'.create')) {
                continue;
            }

            $items[] = [
                // Str y no `ucfirst`, que cuenta bytes: hay nombres con tilde.
                'label' => Str::ucfirst($entry['noun']),
                'url' => route($entry['route'].'.create'),
            ];
        }

        return $items;
    }

    /**
     * @return array{route: string, noun: string}|null
     */
    protected function entry(): ?array
    {
        return $this->record === null
            ? null
            : (self::RECORDS[$this->record::class] ?? null);
    }
}
