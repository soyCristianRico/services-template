---
name: services-map-source
description: Dimensionar y documentar una web de servicios publicada — qué páginas hay y qué secciones tiene cada una — en un documento revisable más un inventario JSON. Primer paso (global) para clonar una web existente.
disable-model-invocation: true
allowed-tools: WebFetch, Bash, Read, Write, Glob, Grep, mcp__playwright__browser_navigate, mcp__playwright__browser_snapshot, mcp__playwright__browser_evaluate, mcp__playwright__browser_take_screenshot
---

# Services · Dimensionar web origen

Levantar el mapa completo de la web origen: inventario de páginas y, por cada una,
sus secciones. Solo documenta; no crea estructura (eso es
`/services-scaffold-structure`), no crea entidades nuevas (eso es
`/services-model-entities`) ni clona páginas (eso es `/services-clone-page`).

Entrada: URL de la web origen. Salidas:
- `storage/app/clone/mapa.md` — documento legible para revisar (páginas + secciones).
- `storage/app/clone/inventory.json` — mismo mapa en datos, para las skills siguientes.
- `storage/app/clone/preguntas.md` — cuestionario listo para enviar a quien pueda
  responderlo (cliente o equipo), con lo que no se puede resolver investigando.

## Modelo destino

Clasificar cada página en una entidad del template: `categories`, `services`
(atributos propios → `custom_fields`), `locations` (árbol país → región → provincia
→ ciudad → distrito), `landings` (categoría×ubicación), `blog`, `pages` (estáticas).
La home y sus secciones son Blade en git: se documentan, no son entidad de BD.

Lo que **no encaje** en esas entidades no se fuerza: va a `fuera_de_modelo` (ver paso 5).

## Proceso

### 0 — Reconocimiento del origen (antes de rastrear nada)

Sondear el origen desde fuera. Barato, y ahorra más trabajo que ningún otro paso.
Todo lo que responda se anota:

1. **CMS y versión** — cabeceras, `generator`, rutas típicas.
2. **Índice de sitemaps** — los nombres cantan los tipos de contenido
   (`curso-sitemap.xml` → tipo `curso`). Alimenta directamente `fuera_de_modelo`.
3. **`robots.txt`** — rutas excluidas y sitemaps alternativos.
4. **WordPress** (el caso más frecuente):
   - **`/wp-json/wp/v2/types`** — devuelve **todos los tipos de contenido con sus
     taxonomías y `rest_base`**. Es la vía genérica más rentable: da la lista completa
     de entidades sin acceso a nada privado.
   - **`/wp-json/`** — *namespaces*. Los propios del cliente (`/wp-json/{algo}/v1/`)
     suelen servir en JSON limpio justo lo que la página hidrata por JS.
   - `/wp-json/wp/v2/{rest_base}` — las fichas. Si `meta` viene poblado, ahí está el
     esquema de campos; si viene `null`, los campos no están expuestos y hay que
     sacarlos del HTML (paso 3).
   - Si los *permalinks* bloquean `/wp-json/`, probar `?rest_route=/wp/v2/types`.

**Si además hay código fuente accesible** (plugin a medida, tema hijo, repo hermano —
`ls -d ../*/`), leerlo: es la fuente autoritativa y gana al scraping, porque incluye
campos vacíos u ocultos en las páginas muestreadas. Pero es un **acelerador
oportunista, no un requisito**: lo normal es clonar sin acceso al código, y el mapa
tiene que salir igual de completo por la vía de arriba más el paso 3.

**Y si se puede pedir un volcado de la base de datos, pedirlo.** Es la vía definitiva:
el código da el *esquema*, el volcado da los *valores por registro* y, sobre todo, lo
que no viaja al navegador — páginas `noindex`, configuración de formularios, fechas y
autorías reales. Qué pedir en WordPress: `posts`, `postmeta`, `terms`,
`term_taxonomy`, `term_relationships`. Formato `.sql` (mysqldump) mejor que el export
XML, que se deja campos. También sirve un `.wpress` (All-in-One WP Migration): es un
concatenado con cabeceras de 4377 bytes del que se puede extraer solo `database.sql`
sin descomprimir los gigas de imágenes.

> **Datos personales.** Un volcado lleva usuarios, hashes de contraseña, leads y
> comentarios. Pedir **solo las tablas necesarias**, nunca `users` completa, no copiar
> el fichero dentro del repo y no volcar su contenido crudo por pantalla. Trabajar
> sobre él en un directorio temporal y extraer únicamente los campos que documenta el
> inventario.

> Si hay código y documentación del propio origen y se contradicen, manda el código.
> Un `.md` de diseño puede decir «8 campos» y la implementación tener 79.

### 1 — Reunir URLs
Leer `origen/sitemap.xml` (y sub-sitemaps). Si no hay, rastrear desde la home por
enlaces internos. HTML plano → `WebFetch` o `curl`. Ver paso 3 para cuándo hace falta
Playwright (menos de lo que parece).

> **El sitemap NO es el inventario completo.** Los plugins de SEO excluyen las páginas
> `noindex`, y ahí viven justo las del embudo de conversión: gracias, confirmación de
> compra, descarga protegida, páginas de campaña. Son imprescindibles para clonar,
> porque sin ellas el formulario no tiene a dónde ir.
>
> Cómo encontrarlas, en orden de fiabilidad: **volcado de BD** (todas las de tipo
> `page` publicadas), **configuración de los formularios** (su URL de redirección),
> enlaces internos desde páginas ya rastreadas, y probar rutas típicas
> (`/gracias/`, `/thank-you/`). Probar rutas a ciegas es lo último y lo peor: los
> nombres reales pueden ser `/gracias-contacto/`, `/gracias-newsletter/`…

### 2 — Clasificar y detectar patrón
Etiquetar cada URL con su entidad. Detectar el patrón de landing:
`A) carpetas /{categoría}/{ubicación}`, `B) slug plano /{categoría}-{ubicación}`,
o `C) sin ubicaciones`. Anotar cuál aplica.

Cuidado con un patrón frecuente: una **taxonomía jerárquica que comparte base de
rewrite con su CPT** hace que `/x/{término}/` y `/x/{ficha}/` convivan en la misma
carpeta. Son entidades distintas aunque la URL se parezca.

### 3 — Documentar secciones por página
Para cada página: título, meta, H1, y la **lista ordenada de secciones** (hero,
bloques de servicios, prueba social, FAQ, CTA, footer…) con su copy y las imágenes.
Atributos de servicio (kVA, m³, dB…) → `custom_fields`. No inventar: sin verificar,
`null`.

**Unión sobre todas las instancias, no una de muestra.** Descargar *todas* las páginas
de un tipo y quedarse con la **unión** de secciones, campos y facetas observadas,
anotando en cuántas aparece cada una (`11/14`). Es barato — un `curl` por URL — y es lo
único que detecta:

- campos **opcionales** que solo están rellenos en algunas fichas,
- secciones que faltan en parte del conjunto (`12/14` → la sección es opcional),
- variantes de plantilla que un solo ejemplar no revela.

Una sola página de muestra da la *forma* de la plantilla; solo la unión da su *esquema*.

**Tres reglas que evitan los errores habituales:**

- **Enlaces: extraer el `href`, nunca deducir la URL del texto.** Del menú y del footer
  se saca el par `(href, texto)`. Un enlace solo se reporta como roto si su `href`
  **extraído** devuelve error al comprobarlo. Contempla destinos fuera del sitemap:
  subdominios, dominios externos, anclas, enlaces con query (`?filtro=x`).
- **Ignorar lo que no se ve.** Descartar bloques con `display:none` / `hidden` /
  `aria-hidden`. Los estados vacíos («No se encontraron resultados…») son
  *placeholders* ocultos, no contenido, y hacen parecer desierta una página llena.
- **Nunca declarar una página «vacía» a partir del HTML estático.** Si aparece un
  contenedor vacío (`<div class="…__items"></div>`) con atributos tipo `data-rest-url`,
  `data-nonce` o `data-ajax`, es un listado **hidratado por JS**: localiza su endpoint
  y trae los datos de ahí. Playwright es el último recurso, no el primero — solo si no
  hay API ni fuente de datos alcanzable.

### 4 — Registrar comportamiento, no implementación
Cuando una página tenga listado filtrable, paginación, buscador o calendario, documentar
**qué hace y con qué facetas** (campos de filtro, valores posibles, orden, tamaño de
página), no *cómo* lo hace el origen. Que el origen lo resuelva con AJAX o REST es un
detalle suyo; en el destino puede ser Livewire, una consulta Eloquent o lo que toque.
El mapa fija el requisito funcional y deja la técnica abierta.

### 5 — Separar lo que no cabe en el modelo
Todo tipo de contenido del origen sin entidad equivalente en el template va a
`fuera_de_modelo`, **estructurado**, porque es la entrada de `/services-model-entities`:

```
fuera_de_modelo: {
  <entidad>: {
    n_paginas, url_patron, campos: [ {nombre, tipo, requerido, valores} ],
    taxonomias: [...], relaciones: [...], fuente: <código | API | scraping>,
    paginas: [...]
  }
}
```

Nunca forzar una entidad ajena dentro de `services` «para que quepa».

### 6 — Escribir salidas
`mapa.md` agrupado por tipo de página, con las secciones de cada una en orden, para
que sea revisable de un vistazo. `inventory.json` con las entidades, slugs en
minúscula-con-guiones y relaciones resueltas (servicio→categoría, landing→categoría
+ubicación).

Distinguir dos listas, sin mezclarlas:
- **`incidencias`** — problemas reales del origen (datos contradictorios, contenido
  que falta, roturas). Requieren decisión humana.
- **`observaciones`** — cómo está hecho el origen y qué se ha inferido o aproximado.
  Informan, no bloquean.

### 7 — Preparar las preguntas abiertas
Tercera salida: **`storage/app/clone/preguntas.md`**, un cuestionario que se pueda
mandar tal cual a quien tenga la respuesta. No es un bloque de dudas sueltas: es un
entregable con contexto, opciones y hueco para responder.

Cada pregunta lleva: **qué hemos visto** (con el dato concreto), **qué opciones hay**,
**qué recomendamos** y **por qué no lo podemos resolver solos**. Agrupadas por
destinatario:

- **Cliente / negocio** — en lenguaje llano, sin jerga técnica. Decisiones de
  contenido y de negocio.
- **Equipo técnico** — modelado, integraciones, esquema.

**El caso que más se repite: el dominio de un selector.** Scrapeando solo se ven los
valores **en uso**, nunca la lista completa de opciones. Si un `tipo_evento` tiene 7
valores definidos y los datos solo usan 2, desde fuera parece un enum de 2. Por eso,
para cada campo de tipo select: listar los valores observados, decir en cuántos
registros aparece cada uno, y preguntar **si la lista está completa**. Lo mismo para
categorías, estados y taxonomías.

Con acceso al código, el dominio se lee y la pregunta se convierte en confirmación
(«están definidos estos 7, solo se usan 2 — ¿mantenemos los otros 5?»).

### 8 — Reportar y encadenar
Conteo por entidad, patrón de landing (A/B/C), páginas sin clasificar y el bloque
`fuera_de_modelo`. La suma de entidades tiene que cuadrar con el total de URLs.
Cerrar diciendo cuántas preguntas abiertas hay y a quién van dirigidas.

Encadenado, según lo que haya salido:

- **`fuera_de_modelo` vacío y sin preguntas que afecten al esquema** →
  `/services-scaffold-structure`.
- **`fuera_de_modelo` con entidades** → mandar `preguntas.md` a quien corresponda y,
  con las respuestas, `/services-model-entities`. El mapeo **no espera** a las
  respuestas: se cierra aquí. Quien modela es quien las necesita.

Después del esquema, y antes de clonar páginas, va `/services-scaffold-structure`, que
siembra la estructura **y los menús** de `chrome`. El admin de las entidades nuevas
(`/services-admin-panel`) va más tarde, cuando ya se sepa qué campos se usan de verdad.

## Qué preguntar y qué no

Preguntar poco, al final, y nunca a mitad del rastreo. La regla:

- **Investigar, nunca preguntar** — todo lo verificable contra el origen: URLs, campos
  en uso, secciones, facetas, destinos de enlaces. Si se puede comprobar, se comprueba.
- **Proponer con recomendación** — decisiones de modelado con un default defendible
  (entidad propia vs `custom_field`, compartir taxonomía o no). Se plantean como
  decisión con opción recomendada, no como pregunta abierta. La mayoría van a
  `/services-model-entities`, no aquí.
- **Preguntar** — solo lo **inaverigüable desde fuera** *y* que cambia el resultado:
  - **Dominios de selects, categorías y estados** — ver arriba. El más frecuente.
  - **Capacidades sin datos**: el origen soporta algo que ninguna página usa hoy
    (un segundo bloque de convocatoria, un estado que nadie tiene). Invisible por
    definición; solo el cliente sabe si piensa usarlo.
  - **Qué pasa al enviar un formulario.** Se ven los campos; **no** se ve el efecto:
    redirección o mensaje en línea, página de gracias, correo de aviso interno, correo
    al usuario, alta en CRM. Nada de eso viaja al navegador y **enviar el formulario
    para averiguarlo no es opción** — genera un registro real. Documentar los campos
    y preguntar el efecto, uno por uno y por cada formulario.
  - **Lógica detrás de integraciones**: pasarelas de pago, CRM, descargas protegidas,
    matriculación externa. Se ve el enlace, no la regla de negocio. Ojo: que exista una
    integración no significa que mande todos los campos del formulario — verificar qué
    se envía de verdad antes de darlo por hecho.
  - **Datos contradictorios en origen** — dos sitios que dicen cosas distintas del
    mismo hecho. No se elige por nuestra cuenta.
  - **Contenido que el origen no publica** — fechas, autorías, categorías vacías. El
    cliente puede tenerlo aunque la web no lo enseñe.
  - **Qué hacer con las páginas fuera del sitemap.** Aparecen `noindex` y sin enlazar,
    y su papel no se deduce del contenido: unas son del embudo y hay que clonarlas,
    otras son destinos de campañas de pago que siguen recibiendo tráfico, y otras son
    restos que conviene no arrastrar. Listarlas una a una con su URL y lo que se ve en
    ellas, y preguntar cuáles entran. Nunca decidirlo por cuenta propia: una página de
    campaña borrada rompe anuncios que están corriendo.

## Cuándo parar

Cuando el sitemap esté agotado, una ronda no aporte tipos de página nuevos y la unión
del paso 3 no revele campos ni secciones nuevas.

Lo que queda fuera del alcance por diseño: campos **definidos en el origen pero sin
datos en ninguna página**. No son scrapeables ni hacen falta para clonar —no hay
contenido que copiar—; solo importan si el cliente va a usarlos, y eso se pregunta.

## Guardarraíles

- Solo documenta. No crea tablas, ni modelos, ni registros, ni ficheros del proyecto
  fuera de `storage/app/clone/`.
- No inventar: URL no extraída, dato no verificado o campo no visto → `null`, y se dice.
- **Precedencia de fuentes, de más a menos autoritativa:** respuesta del cliente →
  volcado de BD → código fuente → API pública → HTML renderizado. Ante discrepancia
  manda la de más arriba, y la diferencia se anota como observación.
- **No presentar una inferencia con tono de hecho verificado.** Lo deducido se marca
  como deducido, con la fuente al lado. Es el error que más caro sale, porque una
  inferencia bien redactada no se vuelve a comprobar.
