---
name: app-build-screen
description: Construir o rehacer UNA pantalla del admin de una web de servicios, de la maqueta al back — esquema, servicio, form, conexión con datos reales, herramientas MCP si hacen falta y tests — siempre en el mismo orden y con las mismas capas, y con los criterios de `.ai/guidelines/ui.md`. Usa cuando el usuario pide "construir la pantalla de X", "añadir la pantalla de...", "rehacer la pantalla de...", o pega el bloque de definición de una pantalla (qué ve, datos, filtros) para implementarla. Adaptada de app-template; los sitios que salen de esta plantilla la heredan.
allowed-tools: Read, Write, Edit, Bash, Glob, Grep, mcp__ruklab__get-deliverable-tool, mcp__ruklab__list-deliverables-tool, mcp__ruklab__add-deliverable-block-tool
disable-model-invocation: true
---

# App · Construir pantalla

Añadir o rehacer **una sola pantalla** del admin, del maquetado al back, probada y con
el visto bueno de Cristian. Sigue siempre el mismo orden y las mismas capas (Form,
servicio, ruta) para que el admin crezca pantalla a pantalla sin rehacer lo anterior.

**Una pantalla por ejecución.** Si no cabe en un bloque de 2 horas, es demasiado
grande: partirla en dos pantallas y ejecutar esta skill dos veces.

## Entrada

Una de estas dos:

- **Pantalla nueva:** el bloque de definición — qué ve y qué puede hacer el usuario,
  qué datos nuevos aparecen y con qué se relacionan, por qué campos se busca o filtra,
  y si algo sale por correo o por MCP. Si llega como enlace a un entregable de
  ruklab.app, leerlo con `get-deliverable-tool` (también la pestaña «Ideas y dudas»).
- **Pantalla existente a rehacer:** su nombre o ruta (`services`, `/admin/landings`). El
  punto de partida es el código actual: listar qué hace hoy (acciones, campos, managers
  anidados, tests) para no perder nada al rehacerla.

**Si falta algo, preguntar antes de maquetar.** No inventar qué ve o edita el usuario.

## Cómo se escribe el código

- **Código** según `.ai/guidelines/formatting.md` (inglés en el código, español solo en
  la interfaz) y `.ai/guidelines/clean-code.md`.
- **Vistas** según `.ai/guidelines/blade.md`: nada de `@php` en la vista; la lógica va a
  un `#[Computed]`, al componente o al enum.
- **Integraciones externas**: detrás de una interfaz con un driver falso que funciona en
  local, para no depender de credenciales.
- **Commits:** en este repo solo se hace commit cuando Cristian lo pide
  (`.ai/guidelines/git.md`). Los **Punto de commit** de abajo marcan dónde proponerlo;
  si ya dio permiso para la pantalla, se hace ahí, uno por paso. Nunca push sin que lo pida.

## Proceso

### 1. Maquetar

1. Vistas en `resources/views/pages/admin/{módulo}/`: `⚡index.blade.php` (listado) y
   `⚡edit.blade.php` (alta y edición; el alta muestra las secciones seguidas y la
   edición las agrupa en pestañas — ver `.ai/guidelines/ui.md`). Layout
   `#[Layout('layouts.admin')]`, cabecera con `<x-admin.page-header>`.
2. Rutas en `routes/web.php`, dentro del grupo `admin.` (`Route::livewire(...)`).
3. Entrada del menú: un caso nuevo en `app/Enums/AdminSection.php` (etiqueta, grupo,
   ruta) y, si el módulo se puede apagar, su interruptor en `AdminNavigation::isEnabled()`.
   El layout no se toca.
4. Pantalla nueva: datos de ejemplo escritos a mano en la vista, todavía sin modelo.
   Pantalla a rehacer: se maqueta directamente sobre los datos reales.

Formulario agrupado por secciones en el orden mental del usuario, no un CRUD genérico.
Campos condicionales solo cuando aplican; lo que casi nadie usa, al final o plegado.

Terminar cuando, en el navegador (Orca), la pantalla se ve completa. Apuntar los campos
que han salido (nombre, tipo, si puede quedar vacío, valor por defecto): es la entrada
del paso 2. **Punto de commit.**

### 2. Construir el back

Solo lo que la pantalla necesite y aún no exista.

**Esquema** — migración (`make:migration`, padres antes que hijos), modelo
(`make:model`), enum si hay selects fijos (`make:enum`; valores que edita el usuario van
a tabla, no a enum), factory para tests. Imágenes → media library con su colección,
nunca una columna con la ruta. No modelar lo que nadie ha pedido. Las migraciones ya
desplegadas no se editan: se crea una nueva. `migrate:fresh` y `migrate:rollback` piden
permiso explícito (`.ai/guidelines/permissions.md`).

**Servicio y form** — dónde vive cada uno y cuándo toca servicio y no form está en
`.ai/guidelines/livewire.md`. La autorización, si la pantalla la necesita más allá de
`auth`, va en la ruta con `->can()`, nunca en `mount()`.

**Punto de commit** por cada capa cerrada: esquema, servicio, form.

### 3. Conectar con datos reales

Sustituir los datos de ejemplo por el modelo, el servicio y el form. El listado lleva
buscador, orden, paginación y filtros por los campos que marcó la entrada, con los
componentes de `ui.md` (`x-admin.search-input`, `x-admin.filter-bar`). No exponer en
pantalla lo que el usuario no debe tocar.

Terminar cuando cada acción se ha probado a mano en el navegador. **Punto de commit.**

### 4. Herramientas MCP

Solo si la entrada dice que estos datos tienen que consultarse o cambiarse desde un
agente. Herramientas en `app/Mcp/Tools/{Módulo}/`, registradas en
`app/Mcp/Servers/ServicesServer.php` (actualizar su bloque de instrucciones). Si una
herramienta existente ya escribe estos datos, cambiarla en el mismo paso que el form
para que las dos reglas no diverjan. **Punto de commit.**

### 5. Tests

Cubrir, por cada capa creada o tocada: modelo, enum si lo hay, servicio, form, la propia
pantalla (ruta + acciones) y las herramientas MCP si se añadieron. Al rehacer una
pantalla, actualizar sus tests existentes antes de crear nuevos. Mecánica en
`.ai/guidelines/testing.md` (nunca la suite entera: ficheros o `--filter`).

Cerrar con `composer run format` (`.ai/guidelines/formatting.md`). **Punto de commit.**

### 6. Autorrepaso contra el contrato

Antes de enseñar nada, repasar lo construido contra `.ai/guidelines/` — las mismas que
mira `/app-review-screen` — y corregir en el sitio lo que se haya desviado. Después,
recorrer cada criterio de `.ai/guidelines/ui.md` con la pantalla abierta: no basta con
haberlos leído al maquetar. Los que más se pasan por alto: título de la ficha editable,
selectores `listbox`, entrada a la ficha por el nombre, anchos, barra fija de Guardar,
toasts en vez de callouts, y un solo Guardar en edición. **Punto de commit.**

### 7. Visto bueno — PARAR

Enseñar la pantalla a Cristian (en Orca o con capturas) y **esperar su visto bueno**.
Si pide cambios: lo visual vuelve al paso 1, los datos al paso 2. Si tras dos rondas
sigue sin darlo, parar y revisar con él si la pantalla es la que hace falta.

Si la entrada venía de un entregable de ruklab.app, anotar en su pestaña «Ideas y dudas»
(`add-deliverable-block-tool`, leyéndola antes) las dudas e ideas que hayan salido — una
línea cada una, sin construirlas. Devolver un resumen: pantalla hecha, herramientas MCP,
resultado de los tests, ideas anotadas y lo aparcado.

## Criterios de UI/UX

Viven en `.ai/guidelines/ui.md`. Los componentes que las implementan están en
`resources/views/components/admin/`; úsalos, no los reescribas.

## Referencia: el módulo de servicios (Catalog)

| Qué | Dónde |
|---|---|
| Entrada del menú, etiqueta y grupo | `app/Enums/AdminSection.php`, `app/Services/Admin/AdminNavigation.php` |
| Form | `app/Livewire/Forms/Catalog/ServiceForm.php` |
| Listado y edición | `resources/views/pages/admin/services/` |
| Rutas | `routes/web.php` (grupo `admin.`) |
| Herramientas MCP y servidor | `app/Mcp/Tools/Catalog/`, `app/Mcp/Servers/ServicesServer.php` |
| Tests | `tests/Feature/{Forms,Livewire/Admin/Services,Mcp}/` |
