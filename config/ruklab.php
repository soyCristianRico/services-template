<?php

declare(strict_types=1);

use App\Models\BlogPost;
use App\Models\Landing;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Service;
use Ruklab\Connector\Content\ContentType;

return [

    /*
    |--------------------------------------------------------------------------
    | Credencial
    |--------------------------------------------------------------------------
    |
    | El token con el que ruklab.app se identifica ante esta web. Se guarda
    | cifrado en el proyecto correspondiente de ruklab.app y viaja en la
    | cabecera Authorization.
    |
    | Sin token, el conector no expone ninguna ruta: una web sin credencial
    | configurada es una web que nadie ha conectado a propósito.
    |
    */

    'token' => env('RUKLAB_CONNECTOR_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Escritura
    |--------------------------------------------------------------------------
    |
    | Una web conectada empieza siendo de solo lectura y alguien tiene que
    | decir que sí.
    |
    */

    'writes_enabled' => env('RUKLAB_CONNECTOR_WRITES', false),

    /*
    |--------------------------------------------------------------------------
    | Tipos de contenido
    |--------------------------------------------------------------------------
    |
    | Qué modelos de esta web son «contenido» y cómo se llaman sus campos.
    |
    | Esta lista es la única puerta. Un modelo que no esté aquí no se puede
    | leer ni escribir desde Ruk Lab, aunque alguien lo nombre en la petición.
    | Faltan a propósito Lead y User: son datos de personas, no contenido.
    |
    */

    'types' => [

        'post' => ContentType::make(
            model: BlogPost::class,
            label: 'Artículos del blog',
            fields: [
                'title' => 'title',
                'content' => 'body',
                'excerpt' => 'excerpt',
                'slug' => 'slug',
                'meta_title' => 'meta_title',
                'meta_description' => 'meta_description',
                'author' => 'author_name',
                'published_at' => 'published_at',
            ],
            status: 'is_active',
            url: '/blog/{slug}',
            // La imagen no es una columna: es la colección `hero` de
            // medialibrary. Se declara aquí para que Ruk Lab pueda dejarla
            // ahí al publicar.
            media: ['featured' => 'hero'],
        ),

        'page' => ContentType::make(
            model: Page::class,
            label: 'Páginas',
            fields: [
                'title' => 'title',
                'content' => 'body',
                'slug' => 'slug',
                'meta_title' => 'meta_title',
                'meta_description' => 'meta_description',
            ],
            status: 'is_active',
        ),

        // El cuerpo de una landing es un árbol de bloques, no texto: se lee
        // para saber qué dice y no se escribe. Su estado tampoco se toca
        // desde aquí — es un enum de tres valores, no un sí o un no.
        'landing' => ContentType::make(
            model: Landing::class,
            label: 'Landings',
            fields: [
                'title' => 'title',
                'content' => 'content',
                'slug' => 'slug',
                'meta_description' => 'meta_description',
                'published_at' => 'publish_at',
            ],
            readonly: ['slug', 'content'],
            url: '/{slug}',
        ),

        // El slug de un servicio cuelga de enlaces y de campañas: se lee, no
        // se toca. Los campos propios de cada web viven en `custom_fields`,
        // que no está mapeado y por tanto no se puede ni nombrar.
        'servicio' => ContentType::make(
            model: Service::class,
            label: 'Servicios',
            fields: [
                'title' => 'name',
                'excerpt' => 'short_description',
                'content' => 'description',
                'slug' => 'slug',
                'meta_title' => 'meta_title',
                'meta_description' => 'meta_description',
            ],
            status: 'is_active',
            readonly: ['slug'],
        ),

    ],

    /*
    |--------------------------------------------------------------------------
    | Menús
    |--------------------------------------------------------------------------
    */

    'menus' => [
        'model' => MenuItem::class,
        'fields' => [
            'label' => 'label',
            'url' => 'url',
            'parent' => 'parent_id',
            'position' => 'position',
            'location' => 'location',
        ],
        'status' => 'is_active',
    ],

    /*
    |--------------------------------------------------------------------------
    | Copias de seguridad
    |--------------------------------------------------------------------------
    |
    | Cuántos días se guarda el estado anterior de cada registro modificado, y
    | cuántas copias como mucho por registro. Lo que hace que un cambio hecho
    | desde fuera se pueda deshacer.
    |
    */

    'snapshots' => [
        'days' => 30,
        'per_record' => 10,
    ],

];
