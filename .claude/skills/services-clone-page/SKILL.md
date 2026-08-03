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

**El inventario puede quedarse corto.** Suele traer bien los titulares y las listas, y
faltarle la prosa. Cuando falte, o venga con marcas de escapado a mitad de frase, se
saca del origen —que es la fuente— y **se corrige también en el inventario**: si solo
se arregla aquí, la siguiente página tropieza con lo mismo.

El registro puede ser de una entidad del template (servicio, landing, página estática,
entrada de blog) o de una entidad creada por `/services-model-entities` y listada en
`entidades_nuevas`. En ese caso, los campos a rellenar son los del mapeo
campo→columna que dejó esa skill en el inventario, no los del template.

**Imágenes.** Descargar las del origen a `public/images/{colección}/{slug}.{ext}` y
**dejarlas versionadas**. El seeder de contenido las engancha a la colección de medios
del registro; aquí solo se colocan con ese nombre. Nunca enlazar al dominio de origen
—el día que se apague, el clon se queda sin imágenes— ni escribir la ruta en una
columna de texto. Una imagen que el origen usa en varios sitios se descarga una vez:
la ficha, la tarjeta del listado y el OG leen todos del mismo registro.

**El original no se pinta tal cual.** Leer la conversión que declara el modelo
(`getFirstMediaUrl('col', 'card')`), no el fichero original. Al `<img>` le hacen falta
`width`, `height` y `loading="lazy"`: sin los dos primeros el listado da saltos
mientras cargan. Si la entidad aún no tiene la conversión que hace falta, se añade al
modelo —es donde vive— y después `php artisan media-library:regenerate` para las
imágenes ya enganchadas.

Comprobarlo midiendo, no mirando: `naturalWidth` frente al ancho pintado. Si la
proporción pasa de ~2×, sobra imagen.

**Los embeds son contenido, no maqueta.** Un iframe de mapa, de vídeo, de calendario o
de reservas lleva en su `src` **qué entidad** enseña —un identificador de ficha, un ID
de vídeo, un calendario concreto—, y eso no se deduce de los datos que uno tenga a mano.
Se copia el `src` del origen tal cual. Reconstruirlo a partir de la dirección postal,
del nombre o del título parece equivalente y no lo es: un embed de mapa montado sobre la
dirección deja de señalar el negocio y devuelve una búsqueda de barrio con veinte pines
de comercios ajenos —y el HTML se ve impecable, porque sigue habiendo un mapa. La URL va
a configuración si el embed es del sitio entero, y a un campo del registro si es de
esa ficha en concreto — en cuyo caso viaja al seeder de contenido como cualquier otro
dato, o se pierde al desplegar. Nunca incrustada en el Blade.

Y **su alto también es del origen**: cambiar un alto fijo por una proporción parece una
mejora responsive, pero un `aspect-*` que en escritorio queda bien deja el bloque en una
franja de 150px en móvil. Si el origen fija píxeles, es una decisión, no un descuido.

### 2 — Comportamiento (solo si la página es un listado)
Si el mapa registró listado filtrable, buscador, paginación o calendario en esta
página, implementarlo aquí: componente Livewire + consulta Eloquent. Las facetas, su
orden y el tamaño de página salen del mapa; **los valores posibles de cada faceta
salen del esquema** (enum o tabla), no de los valores que se vieron en el origen.

No replicar la solución técnica del origen. Que allí lo resuelva una API interna es un
detalle suyo: el requisito es el comportamiento, no el mecanismo.

Eso vale para lo que el origen **calcula**, no para lo que el origen **identifica**. El
`src` de un embed o el ID de un formulario externo señalan una entidad concreta: ahí el
valor literal sí es el requisito, y re-derivarlo es inventarlo.

### 3 — Diseño
Replicar en Blade las secciones concretas de esa página (según el mapa de
`/services-map-source`), respetando el `DESIGN.md` y los componentes Flux del
template. No copiar CSS crudo: traducir a los tokens `@theme`.

**Antes de crear ruta o componente, mirar qué resuelve ya el template.** Suele haber un
catch-all `/{slug}` y un componente detrás que ya cubre este caso; lo normal es
ajustarlo, no escribir uno en paralelo. Un componente nuevo que hace lo mismo se lleva
por delante lo que el template ya tenía resuelto.

Y **dos rutas con el mismo método y URI no conviven**: Laravel las indexa por esa clave,
la última gana y la primera desaparece sin avisar. Si hace falta un catch-all y ya hay
otro, se toca el que existe.

**Los titulares van siempre por el componente del sistema, nunca en HTML crudo.** La
tentación llega con las etiquetas de región —«Filtros», «Tabla de contenidos», las
columnas del pie—: se quiere el `h2` semántico sin la escala de sección y se escribe a
mano con utilidades de tamaño. Eso saca el titular del sistema y lo deja atrás en el
próximo cambio de escala. Si hace falta otro tamaño, se añade **una variante con
nombre** a la hoja de estilos —por papel, no por página— y se usa desde el componente.

**Y ojo con meter una sección estructurada dentro de un campo de texto.** Si la página
tiene cifras destacadas, tarjetas o un equipo, eso son secciones con su maqueta: guardar
su copy como HTML plano en el cuerpo del registro parece que funciona —se ve el texto—
pero deja el bloque sin forma y sin poder darle estilo, porque un mismo tamaño no puede
servir a una cifra de 40px y al título de una tarjeta de 20px. Si el cuerpo del registro
ya viene así del volcado, **es señal de que falta trabajo de maqueta aquí**, no de que
haya que forzar las reglas de prosa.

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

**Los embeds no se pueden medir desde fuera**: el iframe es de otro dominio y
`browser_evaluate` no entra, así que la captura enseña «un mapa» y da por bueno
cualquier mapa. Se verifican por el `src` —el del origen y el de la reconstrucción
tienen que apuntar a la misma entidad— y pidiendo esa URL con `curl`: la respuesta dice
si el proveedor devuelve la ficha única que se espera o una lista de resultados, que es
justo la diferencia que la imagen no delata.

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
- No inventar copy **ni URLs de embed**: lo que no esté en el origen, no se pone.
- Trabajar sobre el registro esqueleto existente; no recrear estructura.
- **La cabecera y el pie no son parte de una página.** Son chrome compartido: si están
  mal, decirlo y seguir, no arreglarlos aquí.
- **Cuando el origen contradiga a `DESIGN.md`, manda el origen** — el contrato se
  extrajo del origen y puede estar equivocado. Corregir `DESIGN.md` en la misma pasada
  y decir qué se cambió, o el siguiente clonado repetirá el error.
- **La lista de páginas es el mapa, no el sitemap.** La 404, las de gracias y las de
  descarga no salen en ningún listado del origen y se olvidan justo por eso.
- **Una plantilla con estados solo se pudo observar en uno.** El origen resuelve la
  condición en servidor, así que los demás estados no están en ningún HTML. El mapa
  trae el observado y la respuesta del cliente sobre el resto: construir la maqueta
  contra el único visible deja los otros sin plantilla.
- Al terminar todas las páginas: verificación global con `/services-verify`.
