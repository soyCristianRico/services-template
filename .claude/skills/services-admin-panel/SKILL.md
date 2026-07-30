---
name: services-admin-panel
description: Construir el panel de administración de las entidades del proyecto — CRUD, subida de imágenes, bloques repetidos y herramientas MCP — para que el cliente gestione todo el contenido sin tocar código. Se ejecuta cuando el esquema ya existe y se sabe qué campos se usan de verdad.
disable-model-invocation: true
allowed-tools: Read, Write, Edit, Bash, Glob, Grep
---

# Services · Panel de administración

Dar al cliente el control del contenido que hoy tiene en su CMS de origen: crear y
editar fichas, subir imágenes, tocar los bloques repetidos y gestionar taxonomías, sin
depender del equipo para nada rutinario.

Requisito previo: el esquema ya creado (`/services-model-entities`). Conviene
ejecutarla **después** de clonar unas cuantas páginas, cuando ya se sabe qué campos se
usan de verdad y cuáles están siempre vacíos — así el formulario se diseña sobre uso
real, no sobre el esquema entero.

Entrada: modelos existentes + `inventory.json` (qué campos aparecen y en cuántas fichas).
Salidas: rutas y componentes Livewire de admin, Form classes, subida de imágenes,
herramientas MCP de las entidades nuevas, y sus tests.

## El problema real

Una entidad con muchos campos no se gestiona con un CRUD generado. Si la ficha del
origen tiene decenas de campos, bloques repetidos y variantes condicionales, un
formulario plano es inusable. Hay que **agrupar por secciones**, ocultar lo que no
aplica y respetar el orden mental con el que el cliente ya trabaja.

## Proceso

### 1 — Medir el uso real de cada campo
Del inventario, para cada entidad: qué campos están rellenos y en cuántas fichas.
Los que no usa nadie van al final o a una sección plegada; los que usa todo el mundo,
arriba. Un campo definido pero nunca usado no merece sitio destacado.

### 2 — Diseñar el formulario por secciones
Reproducir la agrupación con la que el cliente ya trabaja, no el orden de las columnas.
Reglas:

- **Campos condicionales**: si un select cambia qué se muestra (modelo de precio,
  estado, tipo), el formulario refleja esa condición; no se enseñan a la vez campos
  que se excluyen entre sí.
- **Bloques repetidos** (motivos, FAQs, testimonios, ítems de una lista): repetidor con
  añadir, quitar y reordenar por arrastre. Aquí es donde se decide si el campo va en
  `json` o en tabla — **la comodidad de edición manda**.
- **Variantes**: si una ficha puede tener dos bloques equivalentes (dos convocatorias,
  dos tarifas), se activan con un interruptor y el segundo bloque aparece entero.

### 3 — Imágenes
Subida con previsualización, sustitución y borrado, sobre el sistema de medios del
proyecto. Cualquier imagen que el origen deje cambiar, aquí también.

### 4 — Taxonomías y listados
CRUD de las taxonomías propias (áreas, tipos), y en cada índice: buscador, filtros por
las mismas facetas que el público, orden y paginación. El cliente busca como busca su
visitante.

### 5 — Paridad MCP
Las entidades nuevas necesitan sus herramientas MCP igual que las del template
(`app/Mcp/Tools/`): listar, obtener, crear y actualizar, registradas en el servidor y
autenticadas con el token por usuario que ya existe. Sin esto, unas entidades se
gestionan por agente y otras no, que es peor que no tenerlo.

### 6 — Permisos
Comprobar contra las policies en las rutas (`->can()`), nunca en `mount()`. Si hay
perfiles distintos (equipo vs cliente), reflejarlos.

### 7 — Tests
Por cada componente: carga, validación, guardado, autorización. Form classes con su
test propio y su componente anfitrión. Solo los tests afectados.

## Guardarraíles

- **Nada de CRUD genérico** para entidades con muchos campos: se diseña el formulario.
- No exponer en el admin campos que el cliente no debe tocar (identificadores externos,
  claves de integración): van a configuración, no a la ficha.
- Autorización **siempre** a nivel de ruta.
- No romper la paridad MCP: entidad nueva en el admin → herramientas MCP también.
- El admin no inventa contenido: rellenarlo es de `/services-clone-page`.
