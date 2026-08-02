<?php

declare(strict_types=1);

use App\Services\Seo\SeoService;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The whole legal text on one page, with an anchor per block.
 *
 * One page instead of four is deliberate: the sections cross-reference each
 * other constantly, and a single URL means the footer, the cookie banner and
 * every consent link point at the same document. Everything that changes
 * between sites lives in config/legal.php — read the header there before
 * editing this file, because the processors and the services are the parts a
 * notice must state truthfully.
 */
new
#[Layout('layouts.public')]
class extends Component
{
    /** @var array{name: string, address: string, email: ?string} */
    public array $entity;

    public string $siteName;

    public string $domain;

    public string $jurisdiction;

    /** @var list<string> */
    public array $services;

    /** @var list<array{purpose: string, provider: string, detail: string, url: string}> */
    public array $processors;

    /** @var list<array{name: string, detail: string}> */
    public array $recipients;

    public bool $analytics;

    public function mount(SeoService $seo): void
    {
        $this->entity = config('legal.entity');
        $this->siteName = (string) config('app.name');
        $this->domain = (string) preg_replace('/^www\./', '', (string) parse_url((string) config('app.url'), PHP_URL_HOST));
        $this->jurisdiction = (string) config('legal.jurisdiction');
        $this->services = config('legal.services');
        // A processor gated by `requires` is declared only while that config key
        // holds a value — see the note in config/legal.php.
        $this->processors = array_values(array_filter(
            config('legal.processors'),
            fn (array $processor): bool => ! isset($processor['requires'])
                || filled(config($processor['requires'])),
        ));

        $this->recipients = config('legal.recipients', []);

        // The cookie banner renders on exactly this condition, so the cookie
        // policy has to describe the same site the visitor is actually on.
        $this->analytics = (bool) config('services.google_tag_manager.id');

        $seo->setSEO(
            title: 'Aviso legal',
            description: "Toda la información legal de {$this->siteName} en un solo sitio: aviso legal, política de privacidad, política de cookies y condiciones de uso.",
            url: route('legal'),
        );
    }
};
?>

<div>
    <section class="border-b border-border bg-surface">
        <div class="mx-auto max-w-6xl px-6 py-16">
            <flux:heading level="1">Aviso legal</flux:heading>
            <p class="mt-4 max-w-2xl text-muted-foreground">
                Es la parte de la web donde te contamos las cosas legales que seguramente no vas a leer.
                Aun así, aquí están todas juntas y en un solo sitio. Vamos a ello.
            </p>
        </div>
    </section>

    <section class="pb-20">
        <div class="mx-auto max-w-6xl px-6">
            <div class="flex flex-col gap-12 lg:flex-row lg:gap-16">
                {{-- Índice --}}
                <nav class="shrink-0 lg:w-64" aria-label="Índice del aviso legal">
                    <div class="lg:sticky lg:top-24 lg:pt-16">
                        <p class="mb-3 text-sm font-semibold text-foreground">Te lo pongo fácil</p>
                        <ul class="space-y-1 border-l border-border">
                            <li><a href="#aviso-legal" class="block px-4 py-2 text-sm text-muted-foreground transition hover:bg-surface hover:text-foreground">Aviso legal</a></li>
                            <li><a href="#privacidad" class="block px-4 py-2 text-sm text-muted-foreground transition hover:bg-surface hover:text-foreground">Política de privacidad</a></li>
                            <li><a href="#cookies" class="block px-4 py-2 text-sm text-muted-foreground transition hover:bg-surface hover:text-foreground">Política de cookies</a></li>
                            <li><a href="#terminos" class="block px-4 py-2 text-sm text-muted-foreground transition hover:bg-surface hover:text-foreground">Condiciones de uso</a></li>
                        </ul>
                    </div>
                </nav>

                {{--
                    Deliberately NOT `.prose`: this markup is authored here, not
                    pasted by an editor, and the page is shared verbatim across
                    every site. Styling it on the wrapper keeps it identical
                    everywhere instead of inheriting whatever `prose` variant a
                    given site happens to use.
                --}}
                <article class="max-w-3xl flex-1 pt-16 text-muted-foreground
                    [&_h2]:mt-14 [&_h2]:mb-4 [&_h2]:text-2xl [&_h2]:font-semibold [&_h2]:text-foreground
                    [&_h3]:mt-8 [&_h3]:mb-3 [&_h3]:text-lg [&_h3]:font-semibold [&_h3]:text-foreground
                    [&_p]:mb-4 [&_p]:leading-relaxed
                    [&_ul]:mb-4 [&_ul]:list-disc [&_ul]:space-y-2 [&_ul]:pl-6
                    [&_a]:font-medium [&_a]:text-foreground [&_a]:underline [&_a]:underline-offset-2
                    [&_strong]:font-semibold [&_strong]:text-foreground">

                    {{-- ─── AVISO LEGAL ─────────────────────────────────────── --}}
                    <section id="aviso-legal" class="scroll-mt-24">
                        <h2 class="!mt-0">Aviso legal</h2>

                        <h3>Titular de la web {{ $domain }}</h3>
                        <ul>
                            <li>Identidad del responsable: {{ $entity['name'] }}</li>
                            <li>Nombre comercial: {{ $siteName }}</li>
                            <li>Dirección: {{ $entity['address'] }}</li>
                            @if ($entity['email'])
                                <li>Correo electrónico: <a href="mailto:{{ $entity['email'] }}">{{ $entity['email'] }}</a></li>
                            @endif
                        </ul>
                        <p>
                            Esta web cumple con la Ley Orgánica 3/2018, de 5 de diciembre, de Protección de Datos Personales
                            y garantía de los derechos digitales (LOPDGDD), con el Reglamento (UE) 2016/679 del Parlamento
                            Europeo y del Consejo de 27 de abril de 2016 relativo a la protección de las personas físicas
                            (RGPD), y con la Ley 34/2002, de 11 de julio, de Servicios de la Sociedad de la Información y
                            de Comercio Electrónico (LSSI-CE).
                        </p>

                        <h3>Finalidad de la página web</h3>
                        <p>Los servicios prestados por el responsable de la página web son los siguientes:</p>
                        <ul>
                            @foreach ($services as $service)
                                <li>{{ $service }}</li>
                            @endforeach
                        </ul>

                        <h3>Usuarios</h3>
                        <p>
                            El acceso y/o uso de este sitio web te atribuye la condición de USUARIO, y aceptas desde ese
                            momento este aviso legal. El mero uso de la página web no supone el inicio de relación
                            laboral ni comercial alguna.
                        </p>

                        <h3>Condiciones generales de uso</h3>
                        <p>
                            Las presentes condiciones generales regulan el uso —incluido el mero acceso— de las páginas
                            que integran el sitio web de {{ $siteName }}, así como los contenidos y servicios puestos a
                            disposición en ellas. Toda persona que acceda a <a href="{{ url('/') }}">https://{{ $domain }}</a>
                            acepta someterse a las condiciones generales vigentes en cada momento.
                        </p>
                        <p>
                            {{ $siteName }} se reserva el derecho de retirar todos aquellos comentarios y aportaciones que
                            vulneren el respeto a la dignidad de la persona, que sean discriminatorios, xenófobos,
                            racistas, pornográficos, que atenten contra la juventud o la infancia, el orden o la seguridad
                            pública o que, a su juicio, no resulten adecuados para su publicación.
                        </p>
                        <p>
                            En cualquier caso, {{ $siteName }} no será responsable de las opiniones vertidas por los
                            usuarios a través de las herramientas de participación que puedan crearse.
                        </p>

                        <h3>Compromisos y obligaciones de los usuarios</h3>
                        <p>
                            El usuario queda informado, y acepta, que el acceso a esta web no supone en modo alguno el
                            inicio de una relación comercial con {{ $siteName }}. El usuario se compromete a utilizar el
                            sitio web, sus servicios y contenidos sin contravenir la legislación vigente, la buena fe y el
                            orden público.
                        </p>
                        <p>
                            Queda prohibido el uso de la web con fines ilícitos o lesivos, o que de cualquier forma puedan
                            causar perjuicio o impedir el normal funcionamiento del sitio.
                        </p>
                        <p>Respecto de los contenidos de esta web, se prohíbe:</p>
                        <ul>
                            <li>Su reproducción, distribución o modificación, total o parcial, a menos que se cuente con nuestra autorización como legítimos titulares.</li>
                            <li>Cualquier vulneración de los derechos del prestador o de los legítimos titulares.</li>
                            <li>Su utilización para fines comerciales o publicitarios sin autorización de {{ $siteName }}.</li>
                        </ul>
                        <p>
                            El usuario se compromete a no llevar a cabo ninguna conducta que pudiera dañar la imagen, los
                            intereses y los derechos de {{ $siteName }} o de terceros, o que pudiera dañar, inutilizar o
                            sobrecargar el portal, o impedir de cualquier forma su normal utilización.
                        </p>
                        <p>
                            No obstante, debes ser consciente de que las medidas de seguridad de los sistemas informáticos
                            en Internet no son enteramente fiables y que, por tanto, {{ $siteName }} no puede garantizar la
                            inexistencia de software malicioso u otros elementos que puedan producir alteraciones en tus
                            sistemas informáticos, aunque pone los medios y las medidas de seguridad oportunas para
                            evitarlo. Todo el tráfico del sitio viaja cifrado mediante certificado SSL.
                        </p>

                        <h3>Información publicada y contenidos de terceros</h3>
                        <p>
                            La información sobre equipos, servicios, características y disponibilidad publicada en esta web
                            tiene carácter orientativo y puede variar. No constituye una oferta contractual: las condiciones
                            aplicables son siempre las del presupuesto que se te facilite.
                        </p>
                        <p>
                            La web puede incluir información y enlaces relativos a proveedores y fabricantes. {{ $siteName }}
                            no se hace responsable de posibles inexactitudes o errores que pudieran contener esos contenidos,
                            ni garantiza la experiencia, integridad o responsabilidad de dichos terceros.
                        </p>
                        <p>
                            Esta web puede incluir contenidos patrocinados, anuncios y/o enlaces de afiliados, debidamente
                            identificados cuando así lo exija la normativa.
                        </p>
                        <p>
                            Cualquier relación contractual o extracontractual que el usuario formalice con los proveedores,
                            anunciantes o terceros contactados a través de este portal se entiende realizada única y
                            exclusivamente entre el usuario y ese tercero. El usuario acepta que {{ $siteName }} actúa
                            únicamente como cauce o medio y que, por tanto, no tiene ningún tipo de responsabilidad sobre
                            los daños o perjuicios de cualquier naturaleza ocasionados con motivo de esas negociaciones o
                            relaciones.
                        </p>

                        <h3>Medidas de seguridad</h3>
                        <p>
                            Los datos personales comunicados por el usuario pueden ser almacenados en bases de datos cuya
                            titularidad corresponde en exclusiva a {{ $entity['name'] }}, que asume todas las medidas de
                            índole técnica, organizativa y de seguridad que garantizan la confidencialidad, integridad y
                            calidad de la información contenida en ellas, de acuerdo con la normativa vigente en protección
                            de datos.
                        </p>

                        <h3>Derecho de exclusión</h3>
                        <p>
                            {{ $siteName }} se reserva el derecho a denegar o retirar el acceso al portal y/o a los
                            servicios ofrecidos, sin necesidad de preaviso, a aquellos usuarios que incumplan estas
                            condiciones generales de uso.
                        </p>

                        <h3>Plataforma de resolución de conflictos</h3>
                        <p>
                            Ponemos a disposición de los usuarios la plataforma de resolución de litigios que facilita la
                            Comisión Europea, disponible en
                            <a href="https://ec.europa.eu/consumers/odr/" target="_blank" rel="noopener">ec.europa.eu/consumers/odr</a>.
                        </p>

                        <h3>Derechos de propiedad intelectual e industrial</h3>
                        <p>
                            En virtud de lo dispuesto en los artículos 8 y 32.1, párrafo segundo, de la Ley de Propiedad
                            Intelectual, quedan expresamente prohibidas la reproducción, la distribución y la comunicación
                            pública, incluida su modalidad de puesta a disposición, de la totalidad o parte de los
                            contenidos de esta página web con fines comerciales, en cualquier soporte y por cualquier medio
                            técnico, sin la autorización de {{ $siteName }}.
                        </p>
                        <p>
                            El usuario conoce y acepta que la totalidad del sitio web —incluyendo sin carácter exhaustivo el
                            texto, el software, los contenidos, su estructura, selección y ordenación, las fotografías y los
                            gráficos— está protegida por marcas, derechos de autor y otros derechos legítimos.
                        </p>
                        <p>
                            En el caso de que un usuario o un tercero consideren que se ha producido una violación de sus
                            legítimos derechos de propiedad intelectual por la introducción de un determinado contenido en
                            la web, deberá notificarlo indicando sus datos personales, los contenidos protegidos y su
                            ubicación en la web, la acreditación de los derechos señalados y una declaración expresa
                            responsabilizándose de la veracidad de la información facilitada.
                        </p>

                        <h3>Exclusión de garantías y responsabilidad</h3>
                        <p>{{ $siteName }} no otorga garantía alguna ni se hace responsable de los daños y perjuicios de cualquier naturaleza que pudieran traer causa de:</p>
                        <ul>
                            <li>La falta de disponibilidad, mantenimiento y efectivo funcionamiento de la web, de sus servicios o de sus contenidos.</li>
                            <li>La existencia de software malicioso o lesivo en los contenidos.</li>
                            <li>El uso ilícito, negligente, fraudulento o contrario a este aviso legal.</li>
                            <li>La falta de licitud, calidad, fiabilidad, utilidad y disponibilidad de los servicios prestados por terceros y puestos a disposición de los usuarios en el sitio web.</li>
                        </ul>

                        <h3>Ley aplicable y jurisdicción</h3>
                        <p>
                            Con carácter general, las relaciones entre {{ $siteName }} y los usuarios de sus servicios
                            telemáticos se encuentran sometidas a la legislación y jurisdicción españolas y a los tribunales
                            de {{ $jurisdiction }}.
                        </p>

                        @if ($entity['email'])
                            <h3>Contacto</h3>
                            <p>
                                Si tienes cualquier duda sobre estas condiciones legales o cualquier comentario sobre el
                                portal, escríbenos a <a href="mailto:{{ $entity['email'] }}">{{ $entity['email'] }}</a>.
                            </p>
                        @endif
                    </section>

                    {{-- ─── PRIVACIDAD ──────────────────────────────────────── --}}
                    <section id="privacidad" class="scroll-mt-24">
                        <h2>Política de privacidad</h2>

                        <h3>Quiénes somos</h3>
                        <p>
                            Responsable del tratamiento: {{ $entity['name'] }}, con nombre comercial {{ $siteName }} y
                            dirección en {{ $entity['address'] }}.
                            @if ($entity['email'])
                                Datos de contacto: <a href="mailto:{{ $entity['email'] }}">{{ $entity['email'] }}</a>.
                            @endif
                        </p>
                        <p>La dirección de la web es <a href="{{ url('/') }}">https://{{ $domain }}</a>.</p>

                        <h3>Principios que aplicamos a tu información personal</h3>
                        <ul>
                            <li><strong>Licitud, lealtad y transparencia:</strong> siempre te pediremos tu consentimiento para el tratamiento de tus datos personales para uno o varios fines específicos, de los que te informaremos previamente.</li>
                            <li><strong>Minimización de datos:</strong> sólo te pediremos los datos estrictamente necesarios para el fin del que se trate. Los mínimos posibles.</li>
                            <li><strong>Limitación del plazo de conservación:</strong> los datos se mantendrán durante no más tiempo del necesario para los fines del tratamiento.</li>
                            <li><strong>Integridad y confidencialidad:</strong> tus datos se tratarán garantizando una seguridad adecuada, tomando las precauciones necesarias para evitar el acceso no autorizado o el uso indebido por parte de terceros.</li>
                        </ul>

                        <h3>Qué datos recogemos y de dónde salen</h3>
                        <p>Los datos personales que tratamos proceden exclusivamente del formulario del sitio:</p>
                        <ul>
                            <li><strong>Formulario de solicitud de presupuesto:</strong> nombre, teléfono, correo electrónico y el contenido de tu consulta, junto con la página desde la que la envías, para poder preparar la propuesta y responderte.</li>
                        </ul>
                        <p>
                            Junto al envío de cualquiera de esos formularios registramos también tu dirección IP, con la
                            finalidad de comprobar el origen del mensaje, protegernos frente a envíos automatizados y
                            detectar posibles irregularidades.
                        </p>
                        <p>
                            No compramos bases de datos, no recogemos categorías especiales de datos y no elaboramos
                            perfiles ni tomamos decisiones automatizadas con efectos jurídicos sobre ti.
                        </p>

                        <h3>Con qué base legitimamos el tratamiento</h3>
                        <ul>
                            <li><strong>Tu consentimiento</strong>, que otorgas al marcar la casilla correspondiente antes de enviar cualquier formulario, y que puedes retirar en cualquier momento.</li>
                            <li><strong>El interés legítimo</strong> en mantener la seguridad del sitio, en lo que respecta al registro de la dirección IP.</li>
                        </ul>

                        <h3>Encargados del tratamiento</h3>
                        <p>
                            Para poder prestar el servicio, {{ $siteName }} recurre a los siguientes proveedores, que
                            acceden a datos personales por cuenta del responsable y bajo el correspondiente contrato de
                            encargo:
                        </p>
                        <ul>
                            @foreach ($processors as $processor)
                                <li>
                                    <strong>{{ $processor['purpose'] }}:</strong> {{ $processor['provider'] }}. {{ $processor['detail'] }}
                                    <a href="{{ $processor['url'] }}" target="_blank" rel="noopener">Su política de privacidad</a>.
                                </li>
                            @endforeach
                        </ul>
                        @if ($recipients === [])
                            <p>
                                Más allá de esos encargados, no cedemos tus datos a ningún tercero, salvo obligación legal.
                            </p>
                        @else
                            <h3>Destinatarios de tus datos</h3>
                            <p>Además de los encargados anteriores, tus datos se comunican a:</p>
                            <ul>
                                @foreach ($recipients as $recipient)
                                    <li><strong>{{ $recipient['name'] }}:</strong> {{ $recipient['detail'] }}</li>
                                @endforeach
                            </ul>
                            <p>Fuera de lo anterior, no cedemos tus datos a ningún tercero salvo obligación legal.</p>
                        @endif

                        <h3>Transferencias internacionales</h3>
                        <p>
                            Los proveedores anteriores prestan el servicio desde la Unión Europea. Cuando alguno de ellos
                            trate datos fuera del Espacio Económico Europeo, lo hará amparado en las garantías previstas en
                            el capítulo V del RGPD, principalmente las cláusulas contractuales tipo aprobadas por la
                            Comisión Europea.
                        </p>

                        <h3>Cuánto tiempo conservamos los datos</h3>
                        <ul>
                            <li><strong>Solicitudes de presupuesto:</strong> mientras dure la gestión de tu consulta y, después, durante el plazo de prescripción de las posibles responsabilidades derivadas y los plazos legales de conservación aplicables.</li>
                            <li><strong>Registros técnicos y direcciones IP:</strong> el tiempo estrictamente necesario para la finalidad de seguridad que los justifica.</li>
                        </ul>

                        <h3>Tus derechos</h3>
                        <p>
                            Puedes dirigirnos una comunicación por escrito al domicilio indicado más arriba
                            @if ($entity['email'])
                                o a <a href="mailto:{{ $entity['email'] }}">{{ $entity['email'] }}</a>,
                            @endif
                            acompañando copia de tu DNI u otro documento identificativo, para ejercer los siguientes
                            derechos:
                        </p>
                        <ul>
                            <li><strong>Acceso:</strong> saber si estamos tratando tus datos y cuáles.</li>
                            <li><strong>Rectificación:</strong> corregirlos si son inexactos.</li>
                            <li><strong>Supresión:</strong> pedir que los borremos, salvo imperativo legal.</li>
                            <li><strong>Limitación del tratamiento:</strong> en cuyo caso sólo los conservaremos para el ejercicio o la defensa de reclamaciones.</li>
                            <li><strong>Oposición:</strong> dejaremos de tratar los datos en la forma que indiques, salvo motivos legítimos imperiosos.</li>
                            <li><strong>Portabilidad:</strong> si quieres que tus datos los trate otra entidad, te facilitaremos su traslado.</li>
                            <li><strong>Retirada del consentimiento</strong> en cualquier momento, sin que ello afecte a la licitud del tratamiento previo a su retirada.</li>
                        </ul>
                        <p>
                            Si consideras que hay un problema con la forma en que tratamos tus datos, puedes dirigir tu
                            reclamación a la autoridad de control competente, que en España es la
                            <a href="https://www.aepd.es" target="_blank" rel="noopener">Agencia Española de Protección de Datos</a>,
                            donde encontrarás además modelos y formularios para ejercer tus derechos.
                        </p>

                        <h3>Seguridad y brechas</h3>
                        <p>
                            La comunicación entre tu navegador y el sitio utiliza un canal cifrado mediante protocolo HTTPS.
                            Adoptamos medidas razonablemente adecuadas para detectar código malicioso, ataques de fuerza
                            bruta e inyecciones de código.
                        </p>
                        <p>
                            Aun así, debes ser consciente de que ninguna medida de seguridad en Internet es enteramente
                            fiable. En caso de detectarse una brecha de seguridad que afecte a tus datos, nos comprometemos
                            a comunicarlo a la autoridad de control y, cuando proceda, a los usuarios afectados, en el plazo
                            máximo de 72 horas.
                        </p>
                        <p>
                            Garantizas que los datos personales que nos facilitas son veraces y te comprometes a comunicarnos
                            cualquier modificación de los mismos, siendo el único responsable de la inexactitud o falsedad de
                            los datos facilitados.
                        </p>

                        <h3>Cambios en esta política</h3>
                        <p>
                            Nos reservamos el derecho a modificar esta política para adaptarla a novedades legislativas o
                            jurisprudenciales, así como a las prácticas de la industria. En esos supuestos anunciaremos en
                            esta página los cambios introducidos con antelación razonable a su puesta en práctica.
                        </p>
                    </section>

                    {{-- ─── COOKIES ─────────────────────────────────────────── --}}
                    <section id="cookies" class="scroll-mt-24">
                        <h2>Política de cookies</h2>

                        <p>
                            Una de las formas en las que se recopila información en un sitio web es a través de la tecnología
                            llamada «cookies». Esta política tiene por finalidad informarte de manera clara y precisa sobre
                            las que se utilizan en {{ $siteName }}.
                        </p>

                        <h3>¿Por qué existe esta página?</h3>
                        <p>
                            La LSSI-CE obliga a advertir al usuario de la existencia de cookies, informar sobre ellas y
                            requerir su permiso para descargarlas. Lo dice el artículo 22.2 de la Ley 34/2002: «Los
                            prestadores de servicios podrán utilizar dispositivos de almacenamiento y recuperación de datos
                            en equipos terminales de los destinatarios, a condición de que los mismos hayan dado su
                            consentimiento después de que se les haya facilitado información clara y completa sobre su
                            utilización».
                        </p>

                        <h3>¿Qué es una cookie?</h3>
                        <p>
                            Es un fichero que se descarga en tu equipo al acceder a determinadas páginas web. Permite, entre
                            otras cosas, almacenar y recuperar información sobre los hábitos de navegación de un usuario o de
                            su equipo: número de páginas visitadas, ciudad a la que se asigna la dirección IP, número de
                            usuarios nuevos, frecuencia y reincidencia de las visitas, tiempo de visita, navegador o tipo de
                            terminal desde el que se accede.
                        </p>
                        <p>
                            No es un virus, ni un troyano, ni un gusano, ni spam, ni spyware, y no abre ventanas emergentes.
                            Las cookies no almacenan información sensible como datos bancarios o documentos de identidad: lo
                            que guardan es de carácter técnico y de preferencias. El servidor no te asocia a ti como persona,
                            sino a tu navegador.
                        </p>

                        <h3>Tipos de cookies</h3>
                        <p>Según quién las gestione:</p>
                        <ul>
                            <li><strong>Propias:</strong> se envían a tu equipo desde un dominio gestionado por el propio editor y desde el que se presta el servicio solicitado.</li>
                            <li><strong>De terceros:</strong> se envían desde un dominio que no gestiona el editor, sino otra entidad que trata los datos obtenidos.</li>
                        </ul>
                        <p>Según su finalidad:</p>
                        <ul>
                            <li><strong>Técnicas:</strong> permiten el funcionamiento básico del sitio, la navegación y el uso de sus opciones. No requieren consentimiento.</li>
                            <li><strong>De análisis:</strong> recogen información sobre cómo se navega por el sitio, qué secciones se usan más o cuánto dura la visita, para poder mejorarlo.</li>
                        </ul>

                        <h3>¿Qué cookies utilizamos?</h3>
                        <p>
                            <strong>Cookies propias, técnicas.</strong> Las estrictamente necesarias para que el sitio
                            funcione: mantener tu sesión, proteger los formularios frente a envíos fraudulentos y recordar
                            la decisión que hayas tomado sobre estas mismas cookies, para no volver a preguntártelo en cada
                            visita.
                        </p>
                        @if ($analytics)
                            <p>
                                <strong>Cookies de terceros, de análisis.</strong> Este sitio utiliza Google Analytics, cargado
                                a través de Google Tag Manager, para entender el uso que se hace del sitio y mejorarlo. Estas
                                cookies <strong>sólo se instalan si las aceptas</strong> en el aviso que aparece en tu primera
                                visita: hasta entonces, y si las rechazas, el consentimiento se transmite en modo denegado y no
                                se realiza seguimiento. Google trunca la dirección IP antes de almacenarla para garantizar el
                                anonimato.
                            </p>
                        @else
                            <p>
                                <strong>Cookies de terceros.</strong> Este sitio no instala ninguna. No hay analítica de
                                terceros, y por eso tampoco verás un aviso de cookies al entrar.
                            </p>
                        @endif
                        <p>
                            No utilizamos cookies publicitarias ni de remarketing, ni compartimos estas cookies con redes
                            sociales.
                        </p>

                        <h3>¿Cómo cambio mi decisión?</h3>
                        <p>
                            Puedes modificar tu elección en cualquier momento desde el enlace «Configurar cookies» que
                            encontrarás en el pie de página. También puedes eliminar o bloquear las cookies desde la
                            configuración de tu navegador; ten en cuenta que en algunos casos es necesario instalar una
                            cookie para que el navegador recuerde tu decisión de no aceptarlas.
                        </p>

                        <h3>¿Qué ocurre si las desactivo?</h3>
                        <p>
                            El sitio seguirá funcionando con normalidad: las cookies de análisis no son necesarias para
                            navegar, consultar fichas ni enviar formularios. Lo único que se pierde es nuestra capacidad de
                            medir cómo se usa el sitio para mejorarlo.
                        </p>

                        <h3>Notas adicionales</h3>
                        <ul>
                            <li>Ni esta web ni sus representantes legales se hacen responsables del contenido ni de la veracidad de las políticas de privacidad de los terceros mencionados en esta política de cookies.</li>
                            <li>Los navegadores web son las herramientas encargadas de almacenar las cookies, y es desde ellos desde donde debes ejercer tu derecho a eliminarlas o desactivarlas.</li>
                            @if ($analytics)
                                <li>Google Analytics almacena las cookies en servidores que pueden estar ubicados fuera de la Unión Europea, en los términos de su propia política de privacidad.</li>
                            @endif
                        </ul>
                    </section>

                    {{-- ─── CONDICIONES DE USO ──────────────────────────────── --}}
                    <section id="terminos" class="scroll-mt-24">
                        <h2>Condiciones de uso</h2>

                        <p>
                            Esta web es gratuita para quien la consulta: no se vende nada a través de ella, no hay proceso de
                            compra ni pasarela de pago, y no hace falta registrarse para ver ningún contenido. Lo único que
                            puedes hacer es enviarnos una solicitud de presupuesto, sin compromiso alguno.
                        </p>

                        <h3>La solicitud de presupuesto no es un contrato</h3>
                        <p>
                            Enviar el formulario no reserva material, no bloquea fechas ni genera obligación de contratar por
                            ninguna de las partes. Es una petición de información. A partir de ella te preparamos una
                            propuesta, y sólo cuando la aceptas expresamente y por escrito nace la relación contractual, en
                            los términos que esa propuesta recoja.
                        </p>
                        <p>
                            Los precios, plazos y disponibilidad que puedas ver en la web son orientativos y dependen de las
                            fechas, del lugar de entrega y del estado de la flota. Los que valen son los del presupuesto.
                        </p>

                        <h3>Qué esperamos de ti</h3>
                        <ul>
                            <li>Que los datos que nos facilitas en el formulario sean veraces, y muy en particular el teléfono y el correo: son la única forma que tenemos de atenderte.</li>
                            <li>Que no lo utilices para enviar comunicaciones comerciales no solicitadas ni contenido ilícito.</li>
                            <li>Que, si escribes en nombre de una empresa, tengas capacidad para hacerlo.</li>
                        </ul>

                        <h3>Qué puedes esperar de nosotros</h3>
                        <ul>
                            <li>Atender tu solicitud en un plazo razonable, o decirte que no podemos cubrirla.</li>
                            <li>Usar tus datos únicamente para preparar y hacer seguimiento de esa propuesta, en los términos de la política de privacidad de más arriba.</li>
                            <li>No enviarte comunicaciones comerciales ajenas a tu consulta sin tu consentimiento.</li>
                        </ul>

                        <h3>Cambios en estas condiciones</h3>
                        <p>
                            Podemos modificar estas condiciones cuando resulte necesario. El uso continuado del sitio tras su
                            publicación supone su aceptación. Si el cambio afecta a una propuesta ya aceptada, prevalece lo
                            pactado en ella.
                        </p>

                        <h3>Sometimiento a tribunales</h3>
                        <p>
                            Con renuncia a su propio fuero cuando legalmente sea posible, las partes se someten expresamente
                            a los juzgados y tribunales de {{ $jurisdiction }}. Cuando la otra parte tenga la condición de
                            consumidor, será competente el fuero que le reconozca la normativa de consumo. El presente
                            contrato se formaliza en lengua castellana.
                        </p>
                    </section>

                    <p class="mt-12 text-sm italic">
                        Última actualización: {{ config('legal.updated_at') }}.
                    </p>
                </article>
            </div>
        </div>
    </section>
</div>
