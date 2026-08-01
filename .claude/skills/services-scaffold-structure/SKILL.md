---
name: services-scaffold-structure
description: Generar un seeder de Laravel que replica la estructura base de la web origen — árbol de categorías y ubicaciones, registros vacíos de servicios, landings y páginas, y los menús de navegación — para migrarla a producción. Segundo paso (global) del clonado.
disable-model-invocation: true
allowed-tools: Read, Write, Edit, Bash
---

# Services · Replicar estructura base

Convertir el inventario en la **estructura** del site: un seeder de Laravel con el
árbol y los registros esqueleto, sin contenido final. El contenido real lo rellena
`/services-clone-page` página a página. El diseño va en `/services-extract-design`.

Entrada: `storage/app/clone/inventory.json`. Salida: un seeder en
`database/seeders/` (p. ej. `CloneStructureSeeder.php`) reproducible en local y en
producción.

Requisito previo: si el inventario trae `fuera_de_modelo`, ejecutar antes
`/services-model-entities` — esta skill **no crea esquema**, solo inserta filas en
tablas que ya existen.

## Qué crea (solo estructura)

En orden de dependencias:
1. **Categorías** — árbol (raíz primero, luego hijas por `parent_id`).
2. **Servicios** — registro por servicio en su categoría, con `custom_fields`
   declarados pero vacíos.
3. **Ubicaciones** — árbol país → región → provincia → ciudad → distrito.
4. **Landings** — categoría×ubicación (según el patrón A/B/C del mapa), sin copy.
5. **Páginas** — estáticas (aviso legal, gracias, sobre nosotros, contacto), sin copy.
6. **Entidades nuevas** — las del bloque `entidades_nuevas` del inventario (creadas por
   `/services-model-entities`), con sus taxonomías y relaciones resueltas, sin copy.
7. **Menús** — los ítems de cabecera, footer y legales desde `chrome` del inventario.
   Va al final porque los enlaces apuntan a lo sembrado en los pasos anteriores.

Slugs definitivos desde el inicio (los usa `/services-clone-page` para localizar
cada registro). Nada de blog aquí: las entradas se crean con su contenido.

## Proceso

### 1 — Leer inventario y fijar la dimensión geográfica
Cargar `inventory.json` y resolver el árbol (padres antes que hijos).

Escribir `SITE_LOCATIONS` en el `.env` con el valor que dejó el mapeo en
`site.locations` (ver `config/site.php`). En `false`, el sitio no registra la ruta
`/{slug}`, oculta Ubicaciones y Landings del admin y no expone sus herramientas MCP —
**sin borrar código**, para que los `cherry-pick` desde el template sigan aplicando.

Con `false`, los pasos 3 y 4 (ubicaciones y landings) se saltan y se dice en el reporte.
Es un cambio de `.env`: anunciarlo, nunca hacerlo en silencio.

### 2 — Generar los dos seeders
**`CloneStructureSeeder`** — idempotente (upsert por slug, re-ejecutable sin duplicar),
inserta las entidades como estructura vacía leyendo `database/seeders/data/clone-structure.json`.
Reflejar el estado activo/inactivo del origen.

**`CloneContentSeeder`** — se crea aquí **vacío**, leyendo
`database/seeders/data/clone-content.json`, que arranca como `{}`. Lo va rellenando
`/services-clone-page`, una entrada por página.

Los dos, porque **producción se siembra, no se copia**. Si el contenido clonado vive
solo en la base de datos local, desplegar deja el site con la estructura entera y
todas las páginas en blanco, y no hay forma de reproducirlo. El seeder de contenido es
lo que hace que el clon sea versionado y repetible.

### 2b — Sembrar los menús
`MenuItem` ya existe en el template; aquí solo se siembran sus filas desde
`chrome.header` / `chrome.footer` del inventario. Reglas:

- Usar el **`href` extraído** por el mapeo, nunca deducirlo del texto del enlace.
  Enlaces externos, subdominios y anclas se conservan tal cual.
- **Distinguir enlace de bloque dinámico.** Un mega-menú que agrupa fichas por su
  categoría no son N enlaces sueltos: es un ítem `dynamic_block` con su `source`. Si se
  siembra plano, cada ficha nueva obliga a tocar el menú a mano y acaba desincronizado.
- Respetar jerarquía (`parent_id`), columnas del footer y orden (`position`).
- Sembrar **siempre** lo del origen: un menú vacío tras migrar es una regresión.

La pantalla de admin para editarlos es código de plantilla, igual que la entidad. No
se construye por proyecto.

### 3 — Sembrar y comprobar en local
`php artisan db:seed --class=CloneStructureSeeder`. Verificar conteos por entidad
contra el inventario y que el árbol quede bien enlazado.

### 4 — Reportar
Conteo creado por entidad (incluidos los ítems de menú por ubicación) y ruta de los dos
seeders. Migrar a producción = correr allí **los dos**, estructura y después contenido.
Siguiente paso: `/services-extract-design`.

## Guardarraíles

- Solo estructura: sin copy, sin meta final. El relleno es de `/services-clone-page`.
- Seeder idempotente: re-ejecutar no duplica.
- No fabricar entidades que no estén en el inventario.
- No crear ni alterar esquema (migraciones/modelos): eso es `/services-model-entities`.
  Si falta una tabla, parar y decirlo — no sembrar a medias en silencio.
