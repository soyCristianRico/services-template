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

## Antes de empezar: ¿única o plantilla?

No todas las páginas cuestan lo mismo. Antes de tocar nada, mirar el mapa y decidir
cuál de las dos es:

- **Única** — no comparte diseño con ninguna otra: la home, `/contacto/`,
  `/quienes-somos/`, las de gracias. Se clona entera, una vez.
- **De plantilla** — una maqueta que se reaprovecha para N registros: las 14
  oposiciones, las 5 áreas, los cursos, los webinars, los artículos del blog. Aquí se
  clona **la plantilla una sola vez**, contra un registro representativo, y se valida.
  Las demás no son páginas nuevas: son contenido en `clone-content.json`.

Elegir bien el registro representativo: el que tenga más campos rellenos, para que la
plantilla se enfrente a todos los bloques. Después, revisar los casos raros que anotó
el mapa (el que no publica precio, el que no tiene imagen) y comprobar que la
plantilla aguanta con el campo vacío en vez de romperse o dejar un hueco.

Anunciar de cuál se trata al empezar: cambia el alcance de la ejecución y lo que se
valida al final.

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
Capturar origen y reconstrucción **por tramos** (una página larga entera se reduce a
algo ilegible) y comparar secciones, jerarquía, copy clave y meta.

Mirar no basta: **medir**. Con `browser_evaluate` sobre el origen, sacar los valores
computados de lo que se está replicando —color, tamaño, peso, radio, familia— y
compararlos con los de la reconstrucción. La mitad de los desajustes que el ojo
perdona salen aquí. Comparar también la posición vertical de cada titular y su número
de líneas: es lo que detecta un contenedor o una tipografía que no cuadran.

Si la página tiene listado, **ejercitar los filtros en el origen antes de darlos por
buenos**: puede parecer roto y estar filtrando por otro campo. Comprobar que la
reconstrucción devuelve lo mismo para al menos una combinación de facetas.

Y comprobar que no hay desbordamiento horizontal ni en escritorio ni en móvil.

### 6 — Parar para revisión
Presentar la página clonada (URL local + comparación) y **esperar validación** antes
de pasar a la siguiente. Si hay que corregir, iterar sobre esta misma página.

## Guardarraíles

- Una página por ejecución. No encadenar páginas sin validación intermedia.
- La página no se da por hecha si no está en `clone-content.json`.
- No inventar copy: lo que no esté en el origen, no se pone.
- Trabajar sobre el registro esqueleto existente; no recrear estructura.
- **La cabecera y el pie no son parte de una página.** Son chrome compartido: si están
  mal, decirlo y seguir, no arreglarlos aquí.
- **Cuando el origen contradiga a `DESIGN.md`, manda el origen** — el contrato se
  extrajo del origen y puede estar equivocado. Corregir `DESIGN.md` en la misma pasada
  y decir qué se cambió, o el siguiente clonado repetirá el error.
- Al terminar todas las páginas: verificación global con `/services-verify`.
