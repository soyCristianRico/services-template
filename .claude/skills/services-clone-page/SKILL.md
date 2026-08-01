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

## Cómo se ejecuta

**Una conversación por ejecución**, nombrando la página al invocar. Dentro de esa
conversación se itera hasta darla por buena —la skill para y espera— y se cierra al
validarla. La siguiente empieza en blanco.

Se puede porque **el estado vive en ficheros, no en la conversación**: `mapa.md`,
`inventory.json`, `DESIGN.md`, `clone-content.json` y el Blade ya escrito. Una
conversación nueva los lee y sabe todo lo que necesita. Encadenar páginas en el mismo
hilo llena el contexto de capturas y la comparación pierde precisión justo cuando hace
falta medir al píxel.

Lo único que sí es aprendizaje —una contradicción entre el origen y `DESIGN.md`— se
escribe en `DESIGN.md`, así que la siguiente ejecución lo hereda. Por eso ese
guardarraíl no es opcional.

**Qué queda por clonar:** las páginas del mapa que aún no están en
`clone-content.json`. Ese fichero es el registro de avance, no una lista aparte.

## Antes de empezar: leer el papel de la página

El mapa ya clasificó cada página, y su `papel` decide el alcance de esta ejecución. No
se vuelve a decidir aquí; se lee y se anuncia al empezar.

- **`unica`** — se clona entera, una vez.
- **`plantilla`** — se clona **la maqueta una sola vez**, contra el registro de
  referencia que señaló el mapa (el que más campos rellenos tiene, para enfrentarla a
  todos los bloques). Las demás fichas no son páginas nuevas: son contenido en
  `clone-content.json`.
- **`indice`** — su contenido no es copy sino una consulta. El peso está en el paso 2.

En las de plantilla, antes de darla por buena hay que pasarle **los casos raros que
anotó el mapa** —la ficha sin precio, la que no tiene imagen— y comprobar que aguanta
el campo vacío en vez de romperse o dejar un hueco. Validar solo contra el registro más
completo es validar contra el caso feliz.

Si el mapa no trae `papel`, no inventarlo aquí: volver a `/services-map-source`. Es un
dato del mapa, y decidirlo página a página termina clonando N veces la misma maqueta.

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
