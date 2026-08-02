---
name: services-structured-data
description: Diseñar, implementar y validar los datos estructurados (JSON-LD) del sitio clonado — por tipo de página, con un grafo compartido y validación real. Se ejecuta cuando las páginas ya tienen contenido. Es la primera skill del bucle cuyo objetivo NO es la paridad con el origen, sino superarlo.
disable-model-invocation: true
allowed-tools: Read, Write, Edit, Bash, Glob, Grep, WebFetch, mcp__playwright__browser_navigate, mcp__playwright__browser_evaluate
---

# Services · Datos estructurados

Dejar el sitio contando a los buscadores **qué es cada página**, en JSON-LD, con un
grafo coherente en todo el dominio y validado contra el validador real, no contra
«parece correcto».

Entrada: el sitio levantado con su contenido, `inventory.json` (campo `jsonld` por
página) y `SchemaBuilder`. Salida: el grafo implementado por tipo de página, validado,
y la decisión registrada.

## El encargo aquí es distinto al del resto del bucle

Todas las demás skills de clonado son de **paridad**: reproducen el origen y su
guardarraíl es «no inventes, lo que no esté en el origen no se pone».

Esta es la primera cuyo trabajo es **superar al origen**. Casi siempre el origen emite
poco o nada —un gestor con el módulo de schema a medias, o apagado—, así que copiarlo
sería copiar el hueco. Aquí sí se añade lo que allí no había.

Por eso necesita su propio guardarraíl, y es más estricto que el que sustituye:

> **Nada marcado que no esté visible en la página.**

Marcar un precio que no se enseña, un autor que no aparece o una valoración que nadie
ha dejado no es optimizar: es motivo de acción manual. El JSON-LD **describe** la
página, no la decora.

## Proceso

### 1 — Inventariar los dos lados
Tabla por tipo de página: qué emite el origen (sale de `inventory.json`; si no está el
campo, medirlo antes con el navegador) y qué emite la reconstrucción hoy.

Medir con `querySelectorAll('script[type="application/ld+json"]')` **en el navegador,
no con `curl`**: algunos gestores lo inyectan por JS y con `curl` sale un falso vacío.

Que el origen emita poco no es motivo para emitir poco. Es la línea de salida.

### 2 — Decidir el grafo por tipo de página
La pregunta no es «¿qué tenía el origen?» sino **«¿qué es esta página?»**. Una ficha de
servicio es un `Service` o un `Course`; una entrada de blog es un `BlogPosting`; un
producto con precio es un `Product` con su `Offer`; una sesión con fecha es un `Event`.

Reglas del grafo:

- **`Organization` y `WebSite` se declaran una vez** y las demás páginas los referencian
  por `@id`. Repetir el nodo entero en cada página infla el HTML y multiplica el sitio
  donde corregir un dato.
- **`@id` estables y absolutos**, derivados de la URL. Son lo que permite relacionar
  nodos entre páginas; si cambian en cada render, no relacionan nada.
- **`BreadcrumbList` donde haya jerarquía real**, y a ser posible con su rastro visible
  en la página. Sin migas visibles el marcado es correcto pero cojea.
- **Preferir menos nodos bien rellenos** que muchos a medias. Un `Product` sin `offers`
  no da resultado enriquecido y sí da avisos.

Comprobar qué tipos tienen **resultado enriquecido** hoy antes de invertir en ellos: la
lista cambia y hay tipos que Google dejó de usar. Un tipo sin resultado enriquecido
puede seguir mereciendo la pena por comprensión de entidades, pero se decide sabiéndolo,
no por inercia.

### 3 — Implementar en el constructor, no en el Blade
Los nodos se construyen en `SchemaBuilder` y se pasan por `setSEO(structuredData: …)`.
**Nunca escribir JSON-LD a mano en una plantilla**: se duplica, se desincroniza del
registro y no hay forma de testearlo.

Los datos salen del registro. Si una propiedad requerida no está en el modelo, la
decisión es añadirla al esquema o no emitir ese nodo — nunca escribirla a mano.

Antes de crear un método, mirar los que ya hay: es habitual que el template traiga
`breadcrumbList()`, `itemList()` o `faqPage()` escritos y **sin cablear a ninguna
página**, porque servían a un tipo de página que este sitio no usa.

### 4 — Validar de verdad
Por cada tipo de página, dos comprobaciones que fallan por motivos distintos:

1. **Sintáctica y de tipos** — el JSON parsea, los `@type` existen, las propiedades
   requeridas están. Validador de Schema.org o Rich Results Test.
2. **De veracidad** — por cada propiedad marcada, encontrar su contraparte visible en la
   página. Es la que caza el precio inventado y el `aggregateRating` de adorno, y es la
   que ningún validador hace por ti.

Un test automático por tipo de página que afirme los `@type` esperados: es lo que evita
que el grafo desaparezca sin que nadie se entere el día que alguien toque `setSEO`.

### 5 — Registrar la decisión
Anotar qué grafo lleva cada tipo de página y **por qué** — sobre todo los nodos
descartados y su motivo. Sin eso, la siguiente pasada vuelve a discutir si el listado
debería ser `ItemList` y se acaba tomando la decisión contraria por olvido.

## Guardarraíles

- **Nada marcado que no esté visible.** Es la regla de la que cuelgan las demás.
- **Nunca `aggregateRating`, `review` ni `priceValidUntil` inventados.** Es la tentación
  clásica de esta tarea y es sancionable. Sin reseñas reales, no hay nodo de reseñas.
- **Los datos salen del registro**, no escritos a mano en el Blade.
- **Un solo `Organization` por dominio**, referenciado por `@id`, no repetido.
- **No marcar páginas `noindex`.** Si no va al índice, su grafo no pinta nada — y si
  aparece, delata que la regla de indexabilidad está partida en dos sitios.
- **No emitir un nodo por tener el tipo disponible.** Si la página no es eso, no se
  marca: un `Course` que en realidad es una landing comercial es marcado engañoso.
- Al terminar: `/services-verify` comprueba el resultado, ahora sí contra un mapa que
  trae el campo.
