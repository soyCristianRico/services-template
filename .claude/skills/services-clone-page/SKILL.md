---
name: services-clone-page
description: Clonar UNA página de la web origen de principio a fin — contenido, diseño y verificación — sobre el registro esqueleto ya creado. Es el motor del bucle 1-a-1: se ejecuta una página cada vez y se valida antes de seguir.
disable-model-invocation: true
allowed-tools: Read, Write, Edit, Bash, WebFetch, mcp__playwright__browser_navigate, mcp__playwright__browser_snapshot, mcp__playwright__browser_evaluate, mcp__playwright__browser_take_screenshot
---

# Services · Clonar página (1 a 1)

Reproducir **una sola página** del origen completa: su contenido, sus secciones
visuales y su verificación, en una pasada. Se ejecuta página a página, no en lote:
menos blast radius y checkpoint humano por página.

Requisitos previos: `/services-scaffold-structure` (registro esqueleto ya creado) y
`/services-extract-design` (`DESIGN.md` + tokens listos).

Entrada: la página a clonar (URL origen + su entrada en `inventory.json`). Salida:
esa página clonada y verificada, lista para revisión.

## Proceso (para UNA página)

### 1 — Contenido
Rellenar el registro esqueleto de esa página con su copy real, meta title/description,
atributos (`custom_fields`) e imágenes, tomados del origen. Reflejar el estado
activo/inactivo del origen.

El registro puede ser de una entidad del template (servicio, landing, página estática,
entrada de blog) o de una entidad creada por `/services-model-entities` y listada en
`entidades_nuevas`. En ese caso, los campos a rellenar son los del mapeo
campo→columna que dejó esa skill en el inventario, no los del template.

### 2 — Comportamiento (solo si la página es un listado)
Si el mapa registró listado filtrable, buscador, paginación o calendario en esta
página, implementarlo aquí: componente Livewire + consulta Eloquent. Las facetas, su
orden y el tamaño de página salen del mapa; **los valores posibles de cada faceta
salen del esquema** (enum o tabla), no de los valores que se vieron en el origen.

No replicar la solución técnica del origen. Que allí lo resuelva una API interna es un
detalle suyo: el requisito es el comportamiento, no el mecanismo.

### 3 — Diseño
Replicar en Blade las secciones concretas de esa página (según el mapa de
`/services-map-source`), respetando el `DESIGN.md` y los componentes Flux del
template. No copiar CSS crudo: traducir a los tokens `@theme`.

### 4 — Persistir la página en el seeder de contenido
Añadir la entrada de esta página a `database/seeders/data/clone-content.json`, con el
mismo copy, meta y atributos que se acaban de escribir en la base de datos, y volver a
correr `php artisan db:seed --class=CloneContentSeeder` para comprobar que lo escrito
en el fichero reproduce lo que hay en la BD.

**Una página no está clonada hasta que está en ese fichero.** Lo que solo vive en la
base de datos local no llega a producción: allí se corren los seeders, no se copia la
BD. Saltarse esto no rompe nada visible hoy y deja la página en blanco el día del
despliegue.

### 5 — Verificar
`browser_take_screenshot` de la página origen y de la reconstruida y comparar lado a
lado: secciones, jerarquía, copy clave, meta. Reportar coincidencias y desajustes.
Si la página tiene listado, comprobar además que filtrar y paginar devuelven lo mismo
que el origen para al menos una combinación de facetas.

### 6 — Parar para revisión
Presentar la página clonada (URL local + comparación) y **esperar validación** antes
de pasar a la siguiente. Si hay que corregir, iterar sobre esta misma página.

## Guardarraíles

- Una página por ejecución. No encadenar páginas sin validación intermedia.
- La página no se da por hecha si no está en `clone-content.json`.
- No inventar copy: lo que no esté en el origen, no se pone.
- Trabajar sobre el registro esqueleto existente; no recrear estructura.
- Al terminar todas las páginas: verificación global con `/services-verify`.
