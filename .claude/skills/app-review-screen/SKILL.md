---
name: app-review-screen
description: Repasar UNA pantalla o módulo ya construido contra las decisiones tecnológicas que fija `.ai/guidelines/` del repo — qué se respetó, dónde se desvió y qué decisión había que consultar. Da un veredicto corto, no arregla nada. Es el correlato de revisión de app-build-screen. Usa cuando el usuario pide "repasa la pantalla de X", "revisa el módulo de...", o pega una rama/PR para mirar qué decisiones tecnológicas se siguieron.
allowed-tools: Read, Glob, Grep, Bash
disable-model-invocation: true
---

# App · Repasar pantalla

Mirar **una sola pantalla o módulo** ya construido y decir **qué decisiones
tecnológicas se siguieron** y dónde se desvió. No busca bugs de línea ni comprueba si
lo construido es lo que se pidió — eso es otra altura. Mira si la implementación
respetó el stack y la arquitectura que el repo tiene fijados.

**Solo repasa.** No toca código, no arregla. Los arreglos vuelven por
`/app-build-screen` o a mano.

**Una pantalla o módulo por pasada.** Para un diff grande, acotar a lo que toca un
módulo; la auditoría del repo entero es otra cosa.

## El contrato son las guidelines del repo

Las decisiones tecnológicas que se chequean **no viven en esta skill** — viven en
`.ai/guidelines/*.md` del repo donde corre. Cada repo tiene las suyas y derivan: la
plantilla trae un juego base, un proyecto maduro añade las suyas (arquitectura,
control de acceso, MCP…). La skill lee las del repo en cada pasada y chequea contra
esas. No trae una lista propia ni la de otro repo: eso generaría divergencia y falsos
positivos.

## Entrada

Qué repasar, en una de estas formas:

- **Un módulo/pantalla por nombre** (`usuarios`, `facturación`): la skill localiza sus
  capas en el paso 1.
- **Una rama o PR**: los ficheros cambiados respecto a la base (`git diff`).

Si no se dice cuál, preguntar antes de empezar.

## Proceso

### 0. Localizar el contrato

`Glob` sobre `.ai/guidelines/*.md`. Leer las que apliquen a lo que se repasa.

**Si el repo no tiene `.ai/guidelines/`:** parar y decirlo — no hay contrato que
chequear, así que no se inventan desviaciones de decisiones que este repo nunca
adoptó. Ofrecer partir de las de `app-template` como base y no seguir hasta que el
dueño del proyecto lo decida.

### 1. Delimitar qué se repasa

- **Desde un módulo:** reunir sus capas por la estructura que fija `.ai/guidelines/livewire.md`
  — la pantalla en `resources/views/pages/admin/{módulo}/`, su Form en
  `app/Livewire/Forms/{Module}/`, su servicio en `app/Services/{Module}/`, su Policy y su
  ruta en `routes/web.php`. `Glob`/`Grep` por el nombre del módulo en inglés.
- **Desde una rama/PR:** `git diff --name-only <base>...HEAD` y quedarse con los
  ficheros de la pantalla.

### 2. Contrastar, decisión a decisión

Leer el código de esas capas y recorrer las guidelines cargadas como checklist. Por
cada decisión que la guideline fija, mirar si el código la respeta. No reescribir la
regla: citarla (fichero de guideline) y señalar el fichero de código donde se cumple o
se desvía.

### 3. Decisiones que había que consultar

Aparte de las desviaciones, marcar si se tomó en silencio alguna decisión que las
guidelines del repo reservan a permiso explícito (`.ai/guidelines/permissions.md`) o
que es difícil de revertir.

### 4. Veredicto

Corto, en tres bloques y una línea final. Nada de recorrido fichero a fichero.

```
Respetado: [las decisiones clave que sí se siguieron, en una línea cada bloque]

Desviaciones:
- [fichero] — [qué decisión se saltó] → [consecuencia]

Decisiones que había que consultar:
- [cuál se tomó sin preguntar y por qué importa | ninguna]

→ [Listo | Hay que tocar esto antes de darlo por bueno]
```

Si no hay desviaciones ni decisiones sueltas, decirlo claro y cerrar en "Listo" — no
forzar hallazgos.

## Gotchas

- Si el módulo usa términos del negocio, van con su nombre en inglés del glosario
  (`.ai/guidelines/formatting.md`): un identificador en español ya es una desviación,
  no un detalle de estilo.
