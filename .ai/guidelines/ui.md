# UI criteria

Decisiones ya tomadas en pantallas reales, traídas de app-template. Valen para el admin
(`/admin`, `layouts.admin`); la web pública sigue `styling.md`. `/app-build-screen` las aplica al construir y
`/app-review-screen` las
contrasta al repasar. Los componentes que las implementan viven en
`resources/views/components/admin/`: úsalos, no los reescribas.

### Listados

- **Filtros:** solo el buscador (`x-admin.search-input`) a la vista. Todo lo demás, el
  estado incluido (salvo la vista principal, abajo), va dentro del panel «Filtros» con contador (`x-admin.filter-bar`). Los
  filtros activos son chips con su ×, y «Limpiar todo» al final.
- **Vista principal por estado:** cuando un estado es la forma en que el equipo mira el
  listado (alumnos en curso, pendientes de pago o antiguos), va a la vista como selector
  segmentado (`<flux:tabs variant="segmented">`) encima de la tabla: la opción más usada por
  defecto, en la URL, y el recuento de cada una con la búsqueda y los demás filtros aplicados.
  Una sola por listado; el resto de filtros, otros estados incluidos, siguen en «Filtros».
  Escondido en el panel, un estado por defecto oculta resultados sin avisar: quien busca a
  alguien dado de baja ve «0» y cree que no existe. El recuento le dice dónde está.
- **Buscador de personas:** un único scope en el modelo (`matching`) que usan todos los
  listados (y las tools de MCP, si las hay). Parte el texto en palabras y exige que cada una aparezca en el nombre,
  los apellidos, el DNI, el email o el teléfono, en cualquier orden. Nunca el texto entero
  contra una sola columna: «Marta Soriano» no está en ningún campo y no sale nada. El
  teléfono se compara sin espacios ni prefijo (`+34`). Tildes y mayúsculas ya las ignora la
  collation `utf8mb4_unicode_ci`: no hace falta normalizarlas.
- **Columnas:** ordenables solo si el valor se guarda y se compara tal cual (fechas,
  importes, nombres, plazas). Sin orden en campos calculados. El orden por defecto es
  el más útil para el equipo, no el alfabético; un estado se ordena en el orden en que
  se recorre (`CASE` en SQL), no por letra.
- **Orden y filtros en la URL** (`orden`, `sentido`, y cada filtro), para poder
  compartir o recargar la vista.
- **Filas de una sola línea:** el nombre truncado y los datos secundarios en su propia
  columna.
- **Entrar a la ficha:** clic en el nombre del registro (`x-admin.record-link`). Sin botones «Editar» ni
  «Ficha» por fila, y sin pantalla «ver» aparte de la de editar.
- **Alertas derivadas, no estados:** «vencida», «sin plaza»... se calculan desde las
  fechas y el estado que ya existen. Se guarda un estado solo si es una acción del
  equipo.

### Lo que va encima de la tabla

- **Como mucho dos líneas de controles antes de la primera fila:** el buscador con «Filtros», y
  el selector de vista. Si sale una tercera, algo está fuera de su sitio.
- **Cada cosa se elige en un solo control.** El periodo, con «‹ Noviembre 2026 ▾ ›»: flechas
  para el mes de al lado y un desplegable con pocos meses (3 antes, 2 después) y «Todos los
  meses». Nada de botones sueltos de «Mes en curso» o «Todos». Las fechas del panel «Filtros»
  quedan para los rangos raros.
- **El contexto de la pantalla va con el título** («Cobros ‹ Noviembre 2026 ›»), no como un
  filtro más.
- **Lo que un valor por defecto deja fuera se ve con su número, donde ya se mira.** Si el mes en
  curso esconde lo atrasado, «Atrasadas» es una vista más con el recuento en ámbar, no un banner.
- **Un banner (`flux:callout`) solo para lo que pide actuar y no es un trozo del listado:** un
  cobro que ha fallado, un error de una integración, algo por configurar. Uno por pantalla como
  mucho: si todo avisa, nada avisa.
- **La selección masiva vive en la tabla:** una casilla en la cabecera (`flux:checkbox.all`
  dentro de un `flux:checkbox.group`) marca la página. «Seleccionar las N de todas las páginas»
  y las acciones en bloque aparecen solo al marcar algo.
- Caso: Cobros de cierzo-formacion, 16/11/2026. Encima de la tabla había cinco franjas (vistas,
  mes con cuatro botones, aviso de meses anteriores, «Seleccionar las 2 cuotas…») y quedaron dos.

### Formularios y fichas

- **Crear:** una sola página, con secciones en orden y título de sección. Sin pasos ni
  pestañas: un error de validación en una pestaña oculta no se ve. Las secciones viven
  en `partials/` para compartirlas con la edición.
- **Editar:** aquí sí hay pestañas, para bloques que se tocan por separado, con la
  activa en la URL (`?tab=`). Un solo Guardar para todas; si la validación falla en una
  pestaña oculta, la pantalla salta a la pestaña del campo con error. Los datos
  sensibles van en su propia sección dentro de la pestaña que les toca, no en un aviso
  general.
- **Campos opcionales:** `<flux:label badge="Opcional">`. Lo que no lleva etiqueta se
  lee como obligatorio.
- **Tooltips** (`x-admin.info-tooltip`) al lado de la etiqueta, nunca dentro: dentro, el
  clic en el icono activa el campo.
- **Interruptores y botones «añadir»** debajo de su etiqueta, en su propia línea.
- **Estados excluyentes:** un único selector, nunca dos interruptores. El formulario
  deriva los booleanos al guardar.
- **Título:** el nombre del registro se edita en el propio título y guarda al salir,
  solo ese campo — no un título de texto fijo con el nombre como campo en una pestaña.
  En el alta es un campo normal.
- **Selectores:** el desplegable de Flux (`variant="listbox"`), nunca el `<select>`
  nativo; `searchable` si es una lista de personas o puestos, `clearable` si el valor
  es opcional (en vez de una opción vacía). La fecha usa el calendario de Flux en español
  (abajo, «Selectores y fechas»).
- **Guardar y Cancelar:** barra fija abajo, con fondo sólido igual al de la página
  (`bg-background`), siempre visible.
- **Anchos:** listados a ancho completo, con el buscador de ancho fijo. Fichas
  estrechas y centradas: `max-w-3xl` con pestañas, `max-w-2xl` en un formulario corto.
  La barra de Guardar/Cancelar a todo el ancho y la página con `pb-28` para que no tape
  el último campo.
- **Separadores:** ninguno. Espacio entre secciones (`space-y-10`) y un título pequeño
  dentro de cada una.
- **Cabecera:** `x-admin.page-header` con `breadcrumbs`; las acciones secundarias, en el
  menú de tres puntos (`x-admin.record-menu`).
- **Confirmaciones** con toast (`Flux::toast(text:, variant: 'success')`). Si el
  guardado redirige, el mensaje se deja en sesión (`status`) y el toast sale en
  `mount()`.
- **Avisos con acción:** `flux:callout` con `inline`, `flux:callout.heading` y
  `<x-slot:actions>`.

### Selectores y fechas

- Todo selector es el desplegable de Flux, `<flux:select variant="listbox">`; nunca el
  `<select>` nativo (un `<flux:select>` a secas lo renderiza). `searchable` si la lista es
  de personas o puestos, `clearable` si el valor es opcional, nunca una opción vacía
  del tipo «Nadie».
- La fecha usa el calendario de Flux con `locale="es-ES"`, y `clearable` si es opcional:
  ```blade
  <flux:date-picker wire:model="form.startsOn" locale="es-ES">
      <x-slot name="trigger">
          <flux:date-picker.input variant="custom" clearable />
      </x-slot>
  </flux:date-picker>
  ```
  Nunca `<flux:input type="date">` (ni `time` o `datetime-local`), ni un `flux:date-picker` sin
  `locale`: los dos siguen el idioma del navegador, y en uno en inglés salen «mm/dd/yyyy» y
  «October». Visto en gestiona-tu-restaurante, 16/11/2026.
  `tests/Unit/NativeDateFieldsTest.php` lo vigila (cópialo de app-template): falla con un campo así
  en una vista nueva, y su lista de vistas antiguas solo encoge. Al tocar una de esas vistas, pasa
  sus fechas al calendario y quítala de la lista.
