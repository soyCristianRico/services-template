---
name: services-transactional-emails
description: Implementar los correos que el site envía por cada evento — al equipo del cliente y a la persona que actuó — con sus textos, su plantilla y sus pruebas. Se ejecuta cuando los formularios ya están clonados y antes de desplegar.
disable-model-invocation: true
allowed-tools: Read, Write, Edit, Bash, Glob, Grep
---

# Services · Correos transaccionales

Dejar cubierto **todo lo que el site manda por correo**: qué evento lo dispara, quién
lo recibe, qué dice y cómo se comprueba que sale.

Entrada: el bloque de formularios del inventario (qué campos y qué hacía cada uno en el
origen) y el perfil de tono del proyecto. Salida: los correos implementados, con sus
plantillas, sus textos y sus tests.

## Dos destinatarios por evento, y el segundo es el que falta

Cada evento tiene hasta dos correos, y se deciden por separado:

- **Al equipo del cliente** — «ha pasado esto». Es el que suele existir.
- **A la persona que actuó** — acuse, confirmación, enlace de descarga. Es el que casi
  siempre se olvida, porque nadie lo echa de menos desde dentro: quien prueba el
  formulario es del equipo y solo mira si le ha llegado el aviso.

La plantilla trae de serie **el aviso de lead al equipo, y nada más**. Todo lo demás
—incluido el acuse a quien escribe— se implementa aquí.

> Si el origen mandaba un correo y el clon no, es una regresión, aunque nada falle.
> Los gestores de formularios los mandan por defecto, así que la ausencia no se nota
> hasta que un cliente pregunta por qué ya nadie recibe confirmación.

## Proceso

### 1 — Inventariar los eventos
Del mapa, la lista de todo lo que dispara un correo: cada formulario, y además los
eventos que no son un formulario —una compra completada, una descarga protegida, una
inscripción, un cambio de estado—. Por cada uno, las dos filas: **equipo** y
**persona**.

Si el mapa no dice qué mandaba el origen, es de las cosas que no se pueden averiguar
desde fuera y estaba en `preguntas.md`. Sin respuesta, se propone lo razonable y se
marca como pendiente de confirmar; no se deja el hueco.

### 2 — Decidir qué lleva cada uno
- **Al equipo**: los datos para actuar, y el enlace al registro en el admin. Nada de
  adornos. Si la acción tiene contexto útil (qué producto, qué importe, de qué página
  vino), va aquí.
- **A la persona**: qué acaba de pasar, **cuándo tendrá respuesta con un dato concreto**
  —no «en breve»—, y qué puede hacer mientras. Si el correo entrega algo (una descarga,
  un acceso), eso es lo primero del cuerpo, no un enlace al final.

Los textos salen del **perfil de tono del proyecto**, no de la voz de la plantilla.

### 3 — Implementar
Un mailable por evento y destinatario, con su vista en `resources/views/emails/`,
siguiendo la estructura del correo de lead que ya trae la plantilla.

- **Se encolan**, no se envían en la petición: un fallo de correo no puede tumbar el
  envío de un formulario ni hacer esperar a quien lo rellenó.
- **Destinatario del equipo por configuración**, nunca escrito en el código, y
  **admitiendo varias direcciones separadas por comas**. Casi siempre son dos —la del
  cliente y la de la agencia—, y un lead que llega a un solo buzón es un lead que nadie
  contesta la semana que esa persona no está.
- **Nada de datos personales en el asunto.**
- **El asunto dice si el correo pide una acción.** Si todos los formularios llegan como
  «Nuevo lead: nombre», un contacto con una pregunta se lee igual que un alta de
  newsletter que no necesita nada, y se pierde entre ellas.
- Lo que entregue un fichero protegido va por enlace firmado con caducidad, no adjunto.

**Dos nombres que no se pueden usar en un Mailable**: un método `subject()` o una
propiedad `$replyTo`. Los dos existen ya en `Illuminate\Mail\Mailable` y redeclararlos
es un fatal de PHP **al cargar la clase**, antes de ejecutar nada. Pest se muere sin
imprimir una sola línea y devuelve código 2, así que parece que revienta el entorno y no
el código. `subjectLine()` y `$teamInbox` valen igual.

**La plantilla se publica y se viste**: `php artisan vendor:publish --tag=laravel-mail`
y luego el tema con los tokens del contrato visual del proyecto. Un correo no carga la
hoja de estilos del sitio, así que los colores van a pelo en el CSS. Tres cosas se
escapan siempre: el logo apaisado aplastado por los 75×75 cuadrados que trae Laravel, el
radio de los botones —si en el sitio son pastilla, en el correo también— y el pie, que
por defecto no lleva ni dirección ni teléfono ni enlace a privacidad.

**El markdown de los correos pega las líneas seguidas en un solo párrafo.** Un bloque de
datos escrito como líneas consecutivas llega como «Nombre: X Email: Y Teléfono: Z» del
tirón. Los campos se pasan a la vista como array y se separan con `<br>`, sin ponérselo
al último.

### 4 — Probar
Test por correo: que se encola con el evento, que va a quien debe y que el cuerpo
lleva lo que tiene que llevar.

Y una prueba de punta a punta por evento en un entorno real: **con la cola corriendo**.
Un mailable correcto con el worker mal configurado se queda encolado para siempre sin
que nada avise, que es como se pierden los avisos de lead.

Esa prueba se hace **en el entorno de destino y después de desplegar**, y se mira que la
cola baje, no que el proceso exista:

- `Queue::size()` antes y después. Si sube y no baja, el worker no consume. **Contar la
  tabla `jobs` no vale si la cola es redis**: los trabajos no están ahí y siempre saldrá
  cero, que se lee como «no se encoló nada» cuando es justo lo contrario.
- **Cero trabajos fallidos no significa que todo vaya bien.** Un worker que muere al
  arrancar el trabajo no llega a marcar nada como fallido.
- El caso que más cuesta ver: **un worker demonio que sigue vivo desde antes del
  deploy**. Mantiene las clases en memoria y queda atado al directorio de la release
  anterior; cuando el servidor limpia releases viejas, revienta con un fatal del
  autoloader y deja de consumir sin morir. Proceso corriendo, cola creciendo, ningún
  error a la vista. Se ve en el log del worker, no en el de la aplicación.
- Por eso **el script de despliegue tiene que ejecutar `queue:restart`**. Sin esa línea,
  cada despliegue deja el worker con el código anterior. Se nota cuando hay una clase
  nueva; un cambio dentro de una clase que ya existía falla igual de callado.
- Si `queue:restart` no surte efecto, es que el proceso ni siquiera lee la señal —va por
  caché, y su entorno es el de cuando arrancó. Ahí toca reiniciarlo desde el supervisor,
  y comprobar en `ps` que la hora de arranque es de ahora.

### 5 — Reportar
Tabla de eventos × destinatarios con lo implementado y lo que quedó pendiente de
confirmar con el cliente.

## Guardarraíles

- **Ningún evento sin decidir sus dos filas.** Que no haya correo a la persona puede
  ser correcto, pero se decide y se anota; no se omite por olvido.
- **Los textos vienen del perfil de tono**, no de la plantilla ni inventados.
- **Todo encolado.** Nada de envíos síncronos en la petición.
- **El remitente es del dominio del proyecto.** El de ejemplo de la plantilla se
  rechaza o cae en spam.
- **El aviso al equipo admite varias direcciones.** Una sola es una decisión que se toma,
  no el único valor posible.
- **Probado con la cola corriendo**, o no está probado.
