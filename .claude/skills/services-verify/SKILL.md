---
name: services-verify
description: Verificación final del clon completo — paridad de URLs, contenido, meta/SEO y visual entre la web origen y la reconstruida — y reporte de desajustes. Cierra el proceso de clonado, tras haber clonado todas las páginas 1 a 1.
disable-model-invocation: true
allowed-tools: Read, Bash, WebFetch, mcp__playwright__browser_navigate, mcp__playwright__browser_snapshot, mcp__playwright__browser_evaluate, mcp__playwright__browser_take_screenshot
---

# Services · Verificar clon (final)

Auditar el site entero una vez clonadas todas las páginas con `/services-clone-page`.
Cada página ya se verificó al clonarla; esto es la pasada global que caza lo que se
escapa página a página (huecos de cobertura, sitemap, coherencia). No crea ni edita
contenido, solo audita y reporta.

Entrada: URL del origen, URL del reconstruido (local o staging) y
`storage/app/clone/inventory.json`. Salida: informe de paridad con desajustes.

## Qué verificar

### 0 — Cobertura por papel
Una plantilla no está hecha por tener su maqueta: lo está cuando **sus N fichas**
existen y tienen contenido. Contra el mapa, comprobar por cada `papel`:

- `plantilla` → maqueta escrita **y** las N fichas con su copy, no solo la de referencia
- `unica` → su página propia
- `indice` → su ruta responde y lista lo que debe

Es el bloque que caza el fallo más caro del bucle 1-a-1: dar por cerrada una plantilla
validada contra un solo registro.

### 1 — Paridad de URLs
Cruzar el sitemap del origen con el del reconstruido. Reportar URLs del origen sin
equivalente y URLs nuevas que no existían en el origen.

### 2 — Contenido por página
Muestreo sobre cada tipo de página: presencia de H1, secciones, copy clave y
atributos. Reportar lo que falte o difiera; no exigir literalidad.

Los tipos a muestrear son los del inventario, **incluidas las `entidades_nuevas`**, no
solo las entidades que trae el template. Una entidad creada para este clon se verifica
igual que un servicio.

### 3 — La copia que no pertenece a ninguna página
El bucle de clonado recorre páginas, así que reescribe lo que vive **dentro** de una
página. Lo que envuelve a todas —el banner de cookies, el formulario de captación, las
páginas de gracias, los textos por defecto de meta description— no lo toca nadie: llega
del template con la copia del negocio para el que se escribió el template, y sobrevive
al clon entero porque ninguna página lo reclama como suya.

Sale caro porque es el texto que aparece en **todas** las páginas. Un sitio que no pide
presupuestos con un banner de cookies que dice «gestionar tus solicitudes de
presupuesto» lo enseña en cada visita, y encima donde el usuario está decidiendo si se
fía.

Debería haberlo dejado escrito `/services-extract-design`, que es quien monta el marco.
Aquí se comprueba que lo hizo. Barrer el vocabulario del template contra el negocio que
se acaba de clonar; lo que casi siempre queda:

- `components/cookies/banner.blade.php` — para qué dice que se usan los datos
- el formulario de captación — su titular, su botón y su mensaje de éxito
- los textos por defecto de meta description en las plantillas de página
- las páginas de gracias

Y con la misma pasada, **las promesas de plazo heredadas**. «Te llamamos en 15 minutos»
o «respuesta en menos de 15 minutos» son compromisos comerciales del negocio original;
publicarlos en nombre de otro es prometer por él algo que quizá no cumple. No se
reescriben a ojo: se marcan y se preguntan.

Grep es suficiente: buscar los sustantivos del negocio viejo (presupuesto, obra,
avería, instalación…) en `resources/views` y en los cuerpos guardados. Que no aparezcan
en el sitio clonado no significa que no estén en la web: casi todo esto vive en rutas
que hoy no tienen ni un registro —un formulario de captación sin landings creadas— y
aparece meses después, cuando ya no lo mira nadie.

### 4 — Reproducibilidad en producción
Cruzar las páginas del mapa con las entradas de
`database/seeders/data/clone-content.json`. Toda página clonada tiene que estar ahí:
lo que solo esté en la base de datos local **no existe en producción**, porque allí se
corren los seeders.

Comprobar además que los seeders reproducen lo que se ve: sembrar sobre una base
limpia y volver a contar. Es el único bloque que se verifica contra ficheros y no
contra el sitio levantado, y por eso es el que más fácil se pasa por alto — un site
local perfecto puede desplegar en blanco.

Las **imágenes entran en esa prueba**: tras sembrar en limpio, cada registro que debía
tener imagen la tiene. Si falta, es que el fichero no está versionado o su nombre no
casa con el slug, y en producción saldría el hueco.

**Y esa prueba se repite contra el dominio desplegado, pidiendo la imagen.** Todo lo
anterior se comprueba en local, y hay un fallo que en local es invisible por
construcción: `public/storage` es un enlace simbólico que está en `.gitignore`, así que
no viaja en el repositorio. Si nadie corre `php artisan storage:link` en el servidor,
**cada imagen de MediaLibrary da 404 en producción con la base de datos y los ficheros
perfectos**, y el sitio se ve entero menos las fotos. Las de `public/images/` sí
cargan, porque esas van en git; esa asimetría es justo lo que despista al mirarlo.

No vale con abrir la página: pedir la URL de la imagen y mirar el código de respuesta.

```bash
curl -s -o /dev/null -w "%{http_code} %{content_type}\n" https://DOMINIO/storage/1/conversions/algo-card.jpg
```

`200 image/jpeg` es pasar. Un `404 text/html` es Laravel respondiendo a una ruta que
ningún fichero estático atendió — falta el enlace, o faltan los ficheros. Y añadir
`php artisan storage:link` al script de despliegue, que es idempotente: si el enlace ya
existe avisa y sigue con código de salida 0.

### 5 — Listados y filtros
Para cada página que el mapa marcó como listado: comprobar que están todas las facetas
documentadas, que sus opciones cubren el dominio completo del campo (no solo los
valores con datos) y que el conteo de resultados sin filtrar coincide con el origen.
Es el bloque donde más se escapa, porque una faceta que falta no rompe nada visible.

### 6 — Meta y SEO
Comparar meta title, meta description y presencia de JSON-LD por tipo de página.
Confirmar que las inactivas devuelven 404 y no salen en el sitemap.

**El sitemap se comprueba contra el inventario, no contra sí mismo.** Es el fallo caro
de este bloque: un sitemap bien formado, con `lastmod` correcto y sin errores, puede
dejarse fuera el catálogo entero si se construye recorriendo rutas sin parámetros —las
fichas cuelgan de rutas con `{slug}` y ese barrido no las ve. Cruzar URL a URL contra
las indexables del mapa y contar; que responda 200 y valide no dice nada del contenido.

**Y las `noindex` no pueden estar en el sitemap.** Si una página emite `noindex` y
aparece listada, la regla de indexabilidad vive duplicada en dos sitios que ya
discrepan; el arreglo es unificarla, no quitarla del sitemap a mano.

> **Del JSON-LD, aquí solo se verifica la paridad**, y contra el campo `jsonld` del
> inventario. Si ese campo no está, **no se da por bueno**: se mide el origen y se
> reporta que el mapa está incompleto. Dar «OK» porque el mapa no traía nada contra qué
> comparar es el modo silencioso de fallar de este bloque.
>
> Que el grafo esté a la altura de lo que la página **es** —y no solo a la del origen—
> no se juzga aquí: es `/services-structured-data`.

Comprobar también las **redirecciones que el mapa recogió del origen**: una muestra
tiene que responder con el mismo destino que allí. Es lo que impide que años de
historial se conviertan en 404 el día que se migra el dominio.

**Y que cada formulario manda lo que mandaba el origen.** El mapa registró, por cada
uno, si había aviso al equipo y correo a quien lo rellenó. Que el formulario responda
bien no dice nada de eso: el envío va por la cola y falla sin ruido.

### 7 — Visual
`browser_take_screenshot` de home y páginas tipo en ambos sitios y comparar layout,
jerarquía y marca. Diferencias de píxel por fuentes/render no cuentan como fallo.

## Reportar

Checklist por bloque (cobertura, URLs, contenido, copia heredada, reproducibilidad,
listados, SEO, visual) con estado y lista priorizada de desajustes. Adónde vuelve cada
hueco:

- contenido, diseño o listado de una página → `/services-clone-page` sobre esa página
- copia del template que sobrevivió al clon → `/services-extract-design`, que es quien
  monta el marco y su copia. No es de ninguna página, así que el bucle no vuelve a ella
- falta una página entera, o el mapa no trae `papel` → `/services-map-source`
- falta un campo o una opción de faceta en el esquema → `/services-model-entities`
- una página no está en `clone-content.json` → `/services-clone-page` sobre esa página
- el grafo JSON-LD no describe lo que la página es → `/services-structured-data`
- un formulario no manda el correo que mandaba el origen → `/services-transactional-emails`
