# UI criteria

Decisiones ya tomadas en pantallas reales, traídas de app-template. Valen para el admin
(`/admin`, `layouts.admin`); la web pública sigue `styling.md`. `/app-build-screen` las aplica al construir y
`/app-review-screen` las
contrasta al repasar. Los componentes que las implementan viven en
`resources/views/components/admin/`: úsalos, no los reescribas.

### Listados

- **Filtros:** solo el buscador (`x-admin.search-input`) a la vista. Todo lo demás, el
  estado incluido, va dentro del panel «Filtros» con contador (`x-admin.filter-bar`). Los
  filtros activos son chips con su ×, y «Limpiar todo» al final.
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
  es opcional (en vez de una opción vacía). La fecha usa el campo nativo del navegador.
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
- La fecha usa el campo nativo del navegador (`<flux:input type="date">`).
