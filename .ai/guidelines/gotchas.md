# Gotchas

Trampas que ya han costado tiempo. Antes de depurar algo raro, mira aquí.

- **Clases de Tailwind nuevas** no existen en el CSS hasta `npm run build` (o
  `npm run dev` en marcha). Un `w-72` que no se aplica suele ser esto.
- **Imports que se pierden:** el formateador borra un `use` que todavía no se usa.
  Añade el `use` y su uso en el mismo cambio, o escribe el nombre completo.
- **Partials de Blade** (`@include`) no heredan los `use` del componente: nombra los
  enums con su ruta completa.
- **Flux:** `wire:model` en un campo y `x-on:click` en su botón van en el mismo
  elemento. Un tooltip dentro de `<flux:label>` abre el campo al hacer clic.
- **Herramientas de escritorio:** `cat >` sobre un fichero existente lo vacía. Comprueba
  antes si existe.
- **SQLite:** al quitar una columna con índice, quita primero el índice.
- **Tests que dependen de la fecha de hoy** pueden fallar un día y pasar al siguiente:
  fija la fecha con `Carbon::setTestNow()`.
- **Columna de fecha sin cast:** llega a la vista como texto y `->format()` da un 500,
  solo en las filas que tienen valor (un `*_sent_at` que rellena una integración). Cada
  columna `date`, `datetime` o `timestamp` lleva su cast en `casts()`.

## Blade y Livewire

- **Las directivas de Blade (`@js`, `@json`, `@lang`, `@can`…) no se procesan dentro de los atributos de una
  etiqueta de componente** (`<flux:button x-on:click="copy(@js($x))">`): al navegador le llega el texto literal y
  el botón no hace nada, sin ningún error en el servidor. Pasa el valor con `data-value="{{ $x }}"` y léelo en
  Alpine con `$el.dataset.value`, o con un atributo enlazado (`:atributo="..."`). En un elemento HTML normal
  (`<section x-data="{ open: @js($x) }">`) sí funcionan.
- **Un `#[Computed]` guarda su valor** durante la petición. Tras cambiar lo que lee, `unset($this->nombre)`; si
  no, la vista enseña el valor de antes.
- **Comillas simples dentro de un atributo de una etiqueta de componente**, junto a un `{{ }}`
  (`<flux:button x-on:click="$wire.set('campo.{{ $i }}', '', false)">`), rompen la compilación de la vista con
  un error de sintaxis que no señala esa línea («unexpected token endif»). Usa un `<button>` HTML normal para
  ese botón, o pasa el valor por `data-*`. Visto en gestiona-tu-restaurante, 14/11/2026.
- **Probar la pantalla en el navegador**, no solo con tests: un test que comprueba el estado del servidor no
  demuestra que un botón, un copiar o un Alpine funcionen. Un fallo de atributo como el de arriba pasa los tests.
- **Los componentes Livewire probados directamente se saltan el `->can()` de la ruta.** Para comprobar que un
  miembro no puede, llama a la acción (`->call('hacer')->assertForbidden()`) o pide la ruta con `get()`.

## Tests

- **`$this->artisan()` dos veces en el mismo test reutiliza la misma instancia del comando**: un array de estado
  de la clase sobrevive a la segunda llamada. Reinicia el estado al principio de `handle()`.
- **El guard de autenticación recuerda al usuario dentro de un test.** Tras revocar un token o cerrar sesión,
  `$this->app['auth']->forgetGuards()` antes de la siguiente petición, o el test ve un usuario que ya no existe.
- **Tras un `->call()` de Livewire, la respuesta es JSON y las tildes salen escapadas** (`N\u00f3mina`):
  `assertSee('Nómina')` o `assertSeeInOrder([...])` fallan aunque la pantalla esté bien. Comprueba el estado
  (`->get('propiedad')`) o usa textos sin tilde en esa aserción. En la primera carga (`test()` o `get()`) sí se
  ven tal cual.
- **`Http::fake([...])` casa en orden de registro:** un patrón general puesto antes se come a los específicos de
  después. Y lo que no casa sale a internet de verdad: `Http::preventStrayRequests()` en todo test que toque la red.

## Despliegue

- **OPcache de producción y despliegue sin caída:** si el servidor tiene OPcache optimizado (no comprueba si los
  ficheros cambian) y `storage` es compartido entre versiones, PHP-FPM sigue sirviendo la versión vieja de lo que
  Livewire compila en `storage/framework/views/livewire/`, porque la ruta no cambia de una versión a otra. La
  pantalla nueva no sale aunque el código esté desplegado. El script de despliegue recarga PHP-FPM al final:
  `( flock -w 10 9 || exit 1; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock`. Visto en la demo de
  gestiona-tu-restaurante, 14/11/2026.
