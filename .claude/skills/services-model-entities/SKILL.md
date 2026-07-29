---
name: services-model-entities
description: Proponer y crear el esquema (migraciones, modelos, enums, factories) de los tipos de contenido del origen que no tienen entidad en el template. Paso intermedio entre el mapeo y el scaffold, solo si el inventario trae `fuera_de_modelo`.
disable-model-invocation: true
allowed-tools: Read, Write, Edit, Bash, Glob, Grep
---

# Services · Modelar entidades nuevas

Convertir el bloque `fuera_de_modelo` del inventario en **esquema real** del proyecto:
migraciones, modelos, enums y factories. Es el paso que cierra el hueco entre
`/services-map-source` (que solo detecta y documenta) y
`/services-scaffold-structure` (que siembra datos y da por hecho que las tablas ya
existen).

Entrada: `storage/app/clone/inventory.json` → bloque `fuera_de_modelo`.
Salidas: migraciones + modelos + enums + factories + sus tests, y el inventario
actualizado con el mapeo entidad→modelo.

## Cuándo se ejecuta

Solo si `fuera_de_modelo` no está vacío. Si lo está, saltar directamente a
`/services-scaffold-structure`.

## Proceso

### 1 — Leer el inventario, las respuestas y la fuente autoritativa
Cargar `fuera_de_modelo`. Leer también `storage/app/clone/preguntas.md`: las respuestas
**mandan** sobre cualquier inferencia.

**Regla de la pregunta abierta.** Una pregunta sin responder que afecta al esquema de
una entidad **bloquea esa entidad**, no las demás. Se modela y se crea todo lo que no
dependa de ella, y se reporta al final la lista de entidades y campos aparcados con la
pregunta que los desbloquea.

Bloquean por definición: el **dominio de un select** (modelarlo con los valores
observados congela un enum incompleto), qué **taxonomías** se comparten, y si una
capacidad sin datos se conserva o se descarta. No bloquean: nombres de columna, tipos
evidentes, índices.

Si el mapeo registró **código fuente del origen** (plugin, tema, repo hermano), leerlo:
define los campos reales, sus tipos, valores de select, taxonomías y relaciones,
incluidos los que no se ven en las páginas muestreadas. El código manda sobre lo
scrapeado; las respuestas del cliente mandan sobre el código.

### 2 — Decidir el modelado, entidad por entidad
Para cada entidad propuesta, resolver y dejar por escrito:

- **¿Entidad propia o cabe en una existente?** Si comparte ciclo de vida, campos y
  vistas con `services`, quizá no haga falta. Si tiene atributos, listados y filtros
  propios, entidad propia. No inflar el modelo, pero tampoco meter con calzador.
- **Campos** — nombre, tipo de columna, nullable, defaults. Los grupos repetidos
  (`motivo_1..6`, `faq_1..6`, `plazas[]`) se normalizan: o `json`, o tabla aparte si
  se van a consultar o filtrar por separado.
- **Selects fijos → Enums PHP** con `label()` en español (ver reglas del proyecto).
  Valores editables por el cliente → tabla/relación, no enum.
- **Taxonomías del origen** — ¿reutilizan el árbol de `categories` existente o
  necesitan el suyo? Dos taxonomías distintas no se fusionan solo porque tengan
  tamaños parecidos.
- **Relaciones** — con métodos Eloquent tipados. Las relaciones opcionales al modelo
  del template (p. ej. → `Service`) van `nullable` + `nullOnDelete`.
- **Estado y orden** — `is_active`, `position`, `published_at` si el origen los tiene.

### 3 — Presentar la propuesta y PARAR
Mostrar el esquema propuesto (tabla de campos por entidad, enums, relaciones) y
**esperar validación explícita** antes de escribir nada. Señalar de forma destacada:

- entidades con **lógica de negocio no obvia** (pagos, ficheros protegidos,
  caducidades, estados con transiciones),
- campos cuyo tipo es una apuesta,
- lo que se propone **descartar** del origen y por qué.

Este checkpoint es obligatorio: a partir de aquí se toca el esquema del proyecto.

### 4 — Crear el esquema
Con la propuesta validada, y siguiendo las convenciones del proyecto:

1. `php artisan make:migration` — una migración por tabla, orden de dependencias
   (padres antes que hijos). Índices en claves foráneas, `is_active` y campos de filtro.
2. `php artisan make:model` — casts en el método `casts()`, relaciones tipadas,
   `protected` en vez de `private`.
3. `php artisan make:enum` (o `make:class`) para los selects fijos, con `label()` en español.
4. `php artisan make:factory` — con estados útiles para los tests.
5. `php artisan migrate` en local.

### 5 — Tests
Test por modelo en `tests/Feature/Models/` con `describe()` + `it()`: relaciones,
casts, scopes. Enums con sus `label()`. Ejecutar solo los tests afectados
(`php artisan test --compact --filter=...`), nunca la suite entera.

### 6 — Cerrar
`composer run format`. Actualizar **las dos salidas del mapeo, no solo una**:

- `inventory.json` — mover cada entidad de `fuera_de_modelo` a `entidades_nuevas` con
  su modelo, tabla y mapeo campo→columna, para que el scaffold sepa dónde sembrar.
- `mapa.md` — reflejar el mismo movimiento. Es el documento que lee una persona; si se
  queda con las entidades en `fuera_de_modelo` deja de describir el proyecto.

Reportar: entidades creadas, tablas, tests en verde, y **lo aparcado por preguntas sin
responder** (paso 1). Siguiente paso: `/services-navigation` y después
`/services-scaffold-structure`.

Toda entidad creada aquí genera trabajo de admin: el cliente tiene que poder
gestionarla sin tocar código, igual que en su CMS de origen. Eso es
`/services-admin-panel`, y se ejecuta más tarde — pero se anota ya, porque es la parte
que más se olvida al modelar.

## Guardarraíles

- **Parada obligatoria tras la propuesta.** No se escribe migración sin validación.
- Solo esquema: **nada de datos**. Sembrar es de `/services-scaffold-structure`,
  rellenar es de `/services-clone-page`.
- No tocar las tablas del template (`categories`, `services`, `locations`, `landings`,
  `blog_posts`, `pages`, `leads`) salvo para **añadir** una FK nullable. Nunca alterar
  ni borrar columnas existentes.
- Nada de `migrate:fresh`, `migrate:rollback` ni `db:wipe` sin permiso explícito.
- No modelar lo que no esté en el inventario.
- Modificar siempre in-place: nada de `*New.php` ni `*V2.php`.
