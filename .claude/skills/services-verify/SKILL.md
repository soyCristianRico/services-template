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

### 1 — Paridad de URLs
Cruzar el sitemap del origen con el del reconstruido. Reportar URLs del origen sin
equivalente y URLs nuevas que no existían en el origen.

### 2 — Contenido por página
Muestreo sobre cada tipo de página: presencia de H1, secciones, copy clave y
atributos. Reportar lo que falte o difiera; no exigir literalidad.

Los tipos a muestrear son los del inventario, **incluidas las `entidades_nuevas`**, no
solo las entidades que trae el template. Una entidad creada para este clon se verifica
igual que un servicio.

### 3 — Listados y filtros
Para cada página que el mapa marcó como listado: comprobar que están todas las facetas
documentadas, que sus opciones cubren el dominio completo del campo (no solo los
valores con datos) y que el conteo de resultados sin filtrar coincide con el origen.
Es el bloque donde más se escapa, porque una faceta que falta no rompe nada visible.

### 4 — Meta y SEO
Comparar meta title, meta description y presencia de JSON-LD por tipo de página.
Confirmar que las inactivas devuelven 404 y no salen en el sitemap.

### 5 — Visual
`browser_take_screenshot` de home y páginas tipo en ambos sitios y comparar layout,
jerarquía y marca. Diferencias de píxel por fuentes/render no cuentan como fallo.

## Reportar

Checklist por bloque (URLs, contenido, listados, SEO, visual) con estado y lista
priorizada de desajustes. Adónde vuelve cada hueco:

- contenido, diseño o listado de una página → `/services-clone-page` sobre esa página
- falta una página entera → `/services-map-source`
- falta un campo o una opción de faceta en el esquema → `/services-model-entities`
