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
- **Destinatario del equipo por configuración**, nunca escrito en el código.
- **Nada de datos personales en el asunto.**
- Lo que entregue un fichero protegido va por enlace firmado con caducidad, no adjunto.

### 4 — Probar
Test por correo: que se encola con el evento, que va a quien debe y que el cuerpo
lleva lo que tiene que llevar.

Y una prueba de punta a punta por evento en un entorno real: **con la cola corriendo**.
Un mailable correcto con el worker mal configurado se queda encolado para siempre sin
que nada avise, que es como se pierden los avisos de lead.

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
- **Probado con la cola corriendo**, o no está probado.
