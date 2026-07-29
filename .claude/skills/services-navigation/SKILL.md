---
name: services-navigation
description: Convertir los menús de cabecera, footer y legales de la web origen en una entidad editable desde el admin, con los ítems del origen sembrados por defecto. Se ejecuta tras modelar las entidades y antes de clonar páginas.
disable-model-invocation: true
allowed-tools: Read, Write, Edit, Bash, Glob, Grep
---

# Services · Menús editables

Sacar la navegación del código y meterla en base de datos, para que el cliente pueda
cambiarla sin tocar Blade. Los ítems del origen se siembran por defecto: el sitio
funciona igual desde el minuto cero, pero ahora es editable.

Entrada: `inventory.json` → `chrome.header` y `chrome.footer`.
Salidas: entidad `MenuItem` (migración + modelo + enum de ubicación), seeder con los
ítems del origen, componente Blade que los pinta, y CRUD en el admin.

## Por qué es una entidad y no un Blade

El menú cambia cada vez que el cliente añade un servicio, y cada cambio es una llamada
al equipo. Es de lo que más soporte ahorra por lo poco que cuesta.

## Modelo

Un solo `MenuItem` para todas las ubicaciones:

```
location     enum   header | footer | legal | (las que use el origen)
parent_id    FK     autorreferencia, para desplegables
label        string
type         enum   enlace | bloque_dinamico
url          string nullable   (si type = enlace)
source       string nullable   (si type = bloque_dinamico: qué entidad lista)
position     int
is_active    bool
```

**El `type` es la parte que se suele olvidar.** Un mega-menú que agrupa fichas por
categoría no es una lista de enlaces sueltos: si se modela así, cada ficha nueva
obliga a tocar el menú a mano y el cliente acaba con un menú desincronizado. Los
bloques dinámicos se autorrellenan desde su entidad; los enlaces manuales conviven con
ellos en el mismo árbol.

## Proceso

### 1 — Leer la navegación del origen
De `inventory.json`: ítems de cabecera y footer con su `href` **extraído** (nunca
deducido del texto), jerarquía, columnas del footer y enlaces legales. Distinguir:
enlaces internos, externos, subdominios y anclas.

### 2 — Detectar qué es dinámico
Si un bloque del menú lista todas las fichas de una entidad agrupadas por su
taxonomía, es `bloque_dinamico`, no veinte enlaces. Marcarlo y anotar de dónde sale.

### 3 — Crear entidad, seeder y componente
Migración, modelo con `parent()`/`children()` tipados, enum de ubicación con `label()`
en español, seeder idempotente con los ítems del origen, y un componente Blade que
pinte cualquier ubicación resolviendo los bloques dinámicos.

### 4 — Admin
CRUD con **reordenación por arrastre**, anidado a un nivel, activar/desactivar sin
borrar, y validación de que un `enlace` tiene URL y un `bloque_dinamico` tiene origen.

### 5 — Tests
Modelo (jerarquía, scopes por ubicación, orden), enum, y render del componente
resolviendo un bloque dinámico. Solo los tests afectados.

## Guardarraíles

- **Sembrar siempre lo del origen.** Un menú vacío tras migrar es una regresión.
- No mover a base de datos la navegación del **admin**: la tocan los desarrolladores,
  no el cliente, y en código es más simple.
- Enlaces externos y subdominios se conservan tal cual; no convertirlos en rutas
  internas.
- Nada de borrado en cascada silencioso: desactivar un padre oculta sus hijos, no los
  elimina.
