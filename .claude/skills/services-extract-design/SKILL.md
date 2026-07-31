---
name: services-extract-design
description: Extraer el sistema visual de la web de servicios actual (colores, tipografías, escalas, logo) y volcarlo en DESIGN.md + tokens de Tailwind del template. Tercer paso (global) del clonado; deja la base visual sobre la que se replica cada página.
disable-model-invocation: true
allowed-tools: Read, Write, Edit, Bash, WebFetch, mcp__playwright__browser_navigate, mcp__playwright__browser_evaluate, mcp__playwright__browser_take_screenshot, Skill
---

# Services · Extraer diseño de la web actual

Generar el `DESIGN.md` del template **a partir de lo que hay hoy en la web origen**,
no de un brief nuevo. Es la base visual global; la réplica de secciones página a
página la hace `/services-clone-page`.

Entrada: URL de la web origen (+ capturas de `/services-map-source`). Salidas:
`DESIGN.md` y el bloque `@theme` de `resources/css/app.css`, más logo/favicon/OG en
`public/`.

## Dos fuentes, y hacen falta las dos

- **El CSS da los tokens.** Valores exactos de color, tipografía, radios y sombras.
- **El navegador da los patrones.** Cómo se combinan: qué color resalta una palabra
  dentro del H1, dónde se usa el segundo color, qué forma tienen los iconos.

Ninguna sustituye a la otra. Con solo CSS sale una paleta correcta y un sitio que no
se parece al original; con solo capturas, colores aproximados a ojo.

## Proceso

### 1 — Sacar los tokens del CSS del origen

Antes de abrir el navegador, buscar el sistema declarado. Casi siempre existe y es
más fiable que muestrear estilos computados de un elemento suelto.

**WordPress + Elementor** (el caso más frecuente):
- El **kit global** está en `uploads/elementor/css/post-<id>.css`. Descargar los
  `post-*.css` que enlaza la home y quedarse con el que define
  `--e-global-color-primary`: ahí está la paleta entera, la tipografía por nivel
  (`h1`…`h6` con tamaño, peso, interlineado y tracking) y el estilo de botón.
- Si el cliente tiene **plugin o tema propio**, sus variables CSS valen aún más:
  dan la **semántica**, no solo el valor. Un `--x-primary`, `--x-text-muted`,
  `--x-radius` te dice qué papel juega cada color según su autor, que es justo lo
  que un valor computado no te dice.

Otros CMS: buscar el bloque de custom properties del tema.

Anotar también: familias y **pesos** cargados (`fonts.googleapis.com`,
`fonts.bunny.net`), radios, sombras.

### 2 — Sacar los patrones del navegador

Abrir la home y una página tipo, y capturar. Lo que hay que mirar es lo que **no
está en el CSS**:

- **Composición del hero**: banner superior, píldora sobre el H1, si el H1 mezcla
  dos colores, prueba social, cuántos CTA y de qué tipo
- **Rol real del segundo color**: si es color de acción o solo de señal (estrellas,
  badges). Confundirlo es el error más caro: pintar CTAs del color equivocado
- **Forma de los iconos**: línea o relleno, con o sin círculo de fondo
- **Ritmo de secciones**: alternancia de fondos, centrado, aire

Descargar logo, favicon y OG. **Comprobar las dimensiones del OG**: si no es
1200×630 se recorta en redes, y hay que decirlo aunque venga así del origen.

### 3 — Generar el sistema de diseño

Pasar todo lo anterior a `/design-system-from-brief`, que escribe `DESIGN.md` y los
tokens `@theme`, y colocar los assets en `public/`. Esa skill es dueña del formato
del fichero y de las reglas de Flux; aquí solo se le da la marca ya extraída.

Dos comprobaciones que **no son opcionales**, porque fallan en silencio:

- **Contraste antes de asignar roles.** Medir cada combinación real (texto sobre
  fondo de botón, enlaces sobre blanco, texto sobre el segundo color). Un color de
  marca claro suele necesitar texto oscuro encima: darlo por hecho al revés produce
  un `*-foreground` ilegible. Documentar los ratios en `DESIGN.md`, incluidos los
  que se quedan por debajo del mínimo por fidelidad al origen.
- **Que la fuente exista en el proveedor.** Pedir la URL del `@import` y ver que
  devuelve las familias y los pesos pedidos. Si el nombre no existe, el `@import`
  no falla: los titulares caen a la fuente del sistema y nada avisa.

### 4 — Comprobar la base

Levantar el proyecto en local, capturar la home y compararla con la del origen.

Un build verde no demuestra nada: un token mal escrito produce una clase que
resuelve a vacío sin error. Verificar que los valores aterrizaron en el CSS
compilado, y que los `color-mix` derivados de Flux resuelven al color de marca —
eso es lo que confirma que el trío `accent` propagó a toda la librería.

Aún sin replicar secciones: eso es página a página.

### 5 — Reportar

Tokens definidos, assets colocados, ratios de contraste medidos y desviaciones
respecto al origen (si se ha cambiado algún valor, decir cuál y por qué). Siguiente
paso: `/services-clone-page` (bucle página a página).

## Cuándo parar

Cuando color, tipografía y assets del template reflejen los del origen, y los
patrones de composición del hero y de las tarjetas estén documentados. El detalle de
cada sección se ajusta después, página a página.
