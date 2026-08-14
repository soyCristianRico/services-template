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
 * Existe para un movimiento: estás mirando algo en la web y llegas a su
 * pantalla del panel sin ir a buscarla. Lo demás de la barra está colocado
 * alrededor de eso.
 *
 * La pantalla pública dice qué registro está enseñando —`$bar->editing($post)`
 * en su `mount()`, al lado de la llamada de SEO que ya lo tiene cargado— y la
 * barra lo convierte en un enlace. No se adivina nada de la URL, así que una
 * pantalla que no dice nada simplemente se queda sin botón de editar y no se
 * rompe nada.
 *
 * Aquí no hay roles: quien entra al panel lo ve entero, así que la barra
 * enseña lo mismo. Lo que no está enrutado en este sitio desaparece solo.
 *
 * Se registra como `scoped`, para que la instancia en la que escribe la
 * pantalla sea la que lee el layout: un componente Livewire de página entera se
 * monta y se pinta antes que su layout.
 */
class AdminBar
{
    /**
     * Cada tipo de registro y cómo se le llama.
     *
     * Una entrada sirve para tres respuestas: dónde está su pantalla, y la
     * palabra que imprime la barra tanto en «Editar …» como en «Crear …». El
     * nombre de ruta es el tronco: `.edit` y `.create` cuelgan de él.
     *
     * El orden es el del menú «Crear».
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

    /**
     * Qué está enseñando la pantalla pública. Se llama desde su `mount()`.
     */
    public function editing(?Model $record): void
    {
        $this->record = $record;
    }

    public function record(): ?Model
    {
        return $this->record;
    }

    /**
     * Si hay barra que pintar: la ve quien tiene sesión y nadie más.
     */
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

    /**
     * «Editar artículo», «Editar ficha»… — null cuando no hay enlace.
     */
    public function editLabel(): ?string
    {
        $entry = $this->entry();

        return $entry !== null && $this->editUrl() !== null
            ? 'Editar '.$entry['noun']
            : null;
    }

    /**
     * Lo que se puede empezar desde aquí, para el menú «Crear».
     *
     * El mismo registro que el enlace de editar, así que un tipo de registro no
     * puede estar accesible por un lado y faltar por el otro. Lo que no tiene
     * ruta en este sitio se cae solo.
     *
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
     * La entrada del registro que hay en pantalla.
     *
     * @return array{route: string, noun: string}|null
     */
    protected function entry(): ?array
    {
        return $this->record === null
            ? null
            : (self::RECORDS[$this->record::class] ?? null);
    }
}
