<?php
require_once __DIR__ . '/includes/asset-url.php';
require_once __DIR__ . '/includes/favicon-links.php';
require_once __DIR__ . '/includes/analytics.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/current-datetime.php';
require_once __DIR__ . '/includes/site-sections.php';
require_once __DIR__ . '/includes/site-header.php';
require_once __DIR__ . '/includes/site-footer.php';
require_once __DIR__ . '/includes/contact.php';
require_once __DIR__ . '/includes/astronomy-icon.php';
require_once __DIR__ . '/includes/explore-sky.php';

$capabilitiesCurrentDateTime = get_current_datetime('America/Argentina/Buenos_Aires');
$pageSeo = aquellasLunasSeoPage(
    'Qué podés hacer en Aquellas Lunas',
    'Conocé las herramientas de Aquellas Lunas para observar el cielo, consultar eventos, planificar fotografías y entender los datos astronómicos.',
    '/que-podes-hacer.php'
);

$mainSections = [
    [
        'title' => 'El cielo hoy',
        'url' => 'cielo-de-hoy.php',
        'icon' => ['type' => 'moon_phase', 'subtype' => 'first_quarter'],
        'paragraphs' => [
            'Esta sección resume la jornada completa del Sol y la Luna para la ubicación elegida.',
        ],
        'lead' => 'Podés consultar:',
        'items' => [
            'la fase y el porcentaje iluminado de la Luna;',
            'la hora de salida y puesta de la Luna;',
            'la hora de salida y puesta del Sol;',
            'si la Luna está sobre el horizonte;',
            'su altura y dirección;',
            'los intervalos durante los que será visible;',
            'la duración del día;',
            'los distintos crepúsculos;',
            'la hora azul;',
            'la hora dorada;',
            'la nubosidad prevista;',
            'los mejores momentos para observar.',
        ],
        'closing' => [
            'Los gráficos permiten ver cómo cambia la altura del Sol y la Luna a lo largo del día. También muestran los períodos de día, noche y crepúsculo para entender mejor en qué momento se produce cada salida, puesta o evento.',
            'Cuando existe información meteorológica disponible, se incluye la previsión de nubosidad y el detalle de la altura de las nubes.',
        ],
    ],
    [
        'title' => 'El cielo esta noche',
        'url' => 'cielo-de-esta-noche.php',
        'icon' => ['type' => 'conjunction'],
        'paragraphs' => [
            'Esta sección está pensada para responder una pregunta simple: qué vale la pena mirar esta noche.',
            'La información se adapta al momento de la consulta. Si todavía es de día, muestra la próxima noche. Si ya oscureció, se concentra en lo que continúa visible o aparecerá más tarde.',
        ],
        'lead' => 'Podés encontrar:',
        'items' => [
            'planetas visibles;',
            'estrellas destacadas;',
            'conjunciones;',
            'otros objetos de interés;',
            'horarios aproximados;',
            'dirección hacia la que conviene mirar;',
            'tiempo durante el que permanecerán visibles;',
            'tipo de ayuda óptica recomendada;',
            'nubosidad prevista durante la noche.',
        ],
        'closing' => [
            'No busca enumerar todos los objetos posibles, sino destacar los más interesantes y presentar la información de una manera práctica.',
        ],
    ],
    [
        'title' => 'Calendario solar y lunar',
        'url' => 'sol-y-luna.php',
        'icon' => ['type' => 'moon_phase', 'subtype' => 'full_moon'],
        'paragraphs' => [
            'El Calendario solar y lunar permite comparar varios días y planificar con anticipación.',
        ],
        'lead' => 'Para cada fecha muestra:',
        'items' => [
            'salida y puesta del Sol;',
            'salida y puesta de la Luna;',
            'fase lunar;',
            'porcentaje iluminado;',
            'edad de la Luna;',
            'duración del día;',
            'intervalos de visibilidad;',
            'recorrido horario del Sol y la Luna.',
        ],
        'closing' => [
            'Las barras de visibilidad permiten reconocer rápidamente en qué momentos del día la Luna estará sobre el horizonte.',
            'Esta sección es especialmente útil para comparar fechas cercanas y elegir el mejor día para una observación o una fotografía.',
        ],
    ],
    [
        'title' => 'Eventos lunares',
        'url' => 'eventos.php',
        'icon' => ['type' => 'earthshine'],
        'paragraphs' => [
            'Eventos lunares reúne los principales acontecimientos relacionados con la Luna.',
        ],
        'lead' => 'Entre ellos pueden aparecer:',
        'items' => [
            'Luna nueva;',
            'cuarto creciente;',
            'Luna llena;',
            'cuarto menguante;',
            'conjunciones con planetas, estrellas o cúmulos;',
            'perigeos y apogeos;',
            'libraciones;',
            'luz cenicienta;',
            'Lunas muy finas antes o después de Luna nueva;',
            'eclipses;',
            'oportunidades en las que la Luna llena coincide de manera cercana con la salida o puesta del Sol.',
        ],
        'closing' => [
            'Cada evento se calcula para la ubicación seleccionada y puede incluir información como:',
        ],
        'secondary_items' => [
            'fecha y hora local;',
            'visibilidad;',
            'posición en el cielo;',
            'separación angular;',
            'porcentaje iluminado;',
            'horarios de salida o puesta;',
            'nubosidad prevista;',
            'recomendaciones breves para observar o fotografiar.',
        ],
        'after' => 'Cuando un evento tiene más información disponible, se puede abrir un detalle técnico con datos adicionales.',
    ],
    [
        'title' => 'Eclipses',
        'url' => 'eclipses.php',
        'icon' => ['type' => 'eclipse', 'subtype' => 'lunar_eclipse'],
        'paragraphs' => [
            'La sección Eclipses permite consultar eclipses solares y lunares, tanto próximos como de otros años.',
        ],
        'lead' => 'Para cada eclipse se puede ver:',
        'items' => [
            'fecha y hora local;',
            'tipo de eclipse;',
            'si será visible desde la ubicación elegida;',
            'inicio, máximo y final del fenómeno;',
            'magnitud;',
            'altura sobre el horizonte;',
            'mapa de visibilidad, cuando está disponible;',
            'observaciones específicas para la ubicación.',
        ],
        'closing' => [
            'Que un eclipse ocurra no significa que pueda verse desde cualquier lugar. Por eso la web diferencia entre el evento global y su visibilidad local.',
            'Los eclipses que aparecen en Eventos lunares pueden abrir el mismo detalle completo disponible en esta sección.',
        ],
    ],
    [
        'title' => 'Planificador',
        'url' => 'planificador.php',
        'icon' => ['type' => 'apsis', 'subtype' => 'perigee'],
        'paragraphs' => [
            'El Planificador está orientado a quienes quieren preparar una observación o una fotografía con mayor precisión.',
        ],
        'lead' => 'Permite consultar:',
        'items' => [
            'posición del Sol y la Luna;',
            'azimut;',
            'altura;',
            'dirección;',
            'horarios de salida y puesta;',
            'trayectoria sobre el horizonte;',
            'posición para una fecha y hora determinada.',
        ],
        'closing' => [
            'Puede servir para anticipar dónde aparecerá la Luna, comparar horarios o preparar una composición con edificios, paisajes u otros elementos del entorno.',
        ],
    ],
];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
<?php renderSeoHead($pageSeo); ?>
<?php renderAnalyticsTracking(); ?>
<?php renderFaviconLinks(); ?>
    <link rel="stylesheet" href="<?= htmlspecialchars(versionedAssetUrl('assets/css/styles.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(versionedAssetUrl('assets/css/home-v2.css'), ENT_QUOTES, 'UTF-8') ?>">
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/location.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/navigation-indicator.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
</head>
<body>
    <?php require __DIR__ . '/includes/navigation-indicator.php'; ?>
    <?php renderAstronomyDebugClock($capabilitiesCurrentDateTime); ?>
    <?php renderAstronomySiteHeader('capabilities'); ?>

    <main class="page capabilities-page">
        <div class="container capabilities-container">
            <header class="capabilities-hero">
                <p class="eyebrow">Guía del sitio</p>
                <h1>Qué podés hacer en Aquellas Lunas</h1>
                <p>Aquellas Lunas reúne información astronómica y meteorológica para ayudarte a entender qué está pasando en el cielo, qué podés ver desde tu ubicación y cuándo conviene observar o fotografiar.</p>
                <p>El sitio está pensado para público general. La idea es mostrar primero lo más importante con un lenguaje claro y accesible, y ofrecer información más técnica cuando quieras profundizar.</p>
            </header>

            <section class="capabilities-grid" aria-label="Secciones principales de Aquellas Lunas">
                <?php foreach ($mainSections as $section): ?>
                    <article class="card capability-card">
                        <header class="capability-card__heading">
                            <?php renderAstronomyIcon($section['icon'], -34.53, 'capability-card__icon'); ?>
                            <h2><?= htmlspecialchars($section['title']) ?></h2>
                        </header>
                        <?php foreach ($section['paragraphs'] as $paragraph): ?><p><?= htmlspecialchars($paragraph) ?></p><?php endforeach; ?>
                        <p><?= htmlspecialchars($section['lead']) ?></p>
                        <ul><?php foreach ($section['items'] as $item): ?><li><?= htmlspecialchars($item) ?></li><?php endforeach; ?></ul>
                        <?php foreach ($section['closing'] as $paragraph): ?><p><?= htmlspecialchars($paragraph) ?></p><?php endforeach; ?>
                        <?php if (isset($section['secondary_items'])): ?><ul><?php foreach ($section['secondary_items'] as $item): ?><li><?= htmlspecialchars($item) ?></li><?php endforeach; ?></ul><?php endif; ?>
                        <?php if (isset($section['after'])): ?><p><?= htmlspecialchars($section['after']) ?></p><?php endif; ?>
                        <a class="capability-card__link" href="<?= htmlspecialchars(astronomyInternalUrl($section['url']), ENT_QUOTES, 'UTF-8') ?>">Ir a <?= htmlspecialchars($section['title']) ?> <span aria-hidden="true">→</span></a>
                    </article>
                <?php endforeach; ?>
            </section>

            <section class="capabilities-special" aria-label="Funciones y criterios del sitio">
                <article class="card capability-feature capability-feature--location">
                    <h2>Tu ubicación</h2>
                    <p>Los horarios y resultados cambian según el lugar desde el que observás.</p>
                    <p>La ubicación elegida influye en:</p>
                    <ul><li>la salida y puesta del Sol;</li><li>la salida y puesta de la Luna;</li><li>la dirección y altura de los astros;</li><li>los períodos de visibilidad;</li><li>la duración del día;</li><li>los crepúsculos;</li><li>la visibilidad de eclipses;</li><li>la previsión meteorológica.</li></ul>
                    <p>Podés seleccionar otra ubicación para consultar cómo se verá el cielo desde allí.</p>
                    <p>La fecha, la hora y la zona horaria mostradas se adaptan a esa ubicación.</p>
                    <a href="<?= htmlspecialchars(astronomyInternalUrl('ubicacion.php'), ENT_QUOTES, 'UTF-8') ?>">Elegir ubicación</a>
                </article>

                <article class="card capability-feature capability-feature--calculation">
                    <h2>Cálculos astronómicos propios</h2>
                    <p>La información de la web no proviene de una tabla fija preparada para una única ciudad.</p>
                    <p>Los datos astronómicos se calculan para la fecha y ubicación seleccionadas.</p>
                    <p>Entre esos cálculos se encuentran:</p>
                    <ul><li>posición del Sol y la Luna;</li><li>altura y azimut;</li><li>salidas y puestas;</li><li>fases;</li><li>iluminación;</li><li>visibilidad;</li><li>crepúsculos;</li><li>conjunciones;</li><li>libraciones;</li><li>perigeos y apogeos;</li><li>eclipses;</li><li>trayectorias.</li></ul>
                    <p>Estos cálculos se combinan con información meteorológica para ofrecer una visión más útil de las condiciones reales de observación.</p>
                    <a href="<?= htmlspecialchars(astronomyInternalUrl('planificador.php'), ENT_QUOTES, 'UTF-8') ?>">Abrir el Planificador</a>
                </article>

                <article class="card capability-feature capability-feature--forecast">
                    <h2>Previsión del cielo</h2>
                    <p>Cuando hay pronóstico disponible, la web incorpora datos meteorológicos para ayudar a evaluar si un evento podrá observarse.</p>
                    <p>Puede mostrar:</p>
                    <ul><li>porcentaje de nubosidad;</li><li>estado general del cielo;</li><li>nubosidad por hora;</li><li>altura de las distintas capas de nubes;</li><li>momento con mejores condiciones previstas.</li></ul>
                    <p>La previsión meteorológica es orientativa y puede cambiar. Su función es ayudar a decidir cuándo puede valer la pena observar, no garantizar que el cielo estará despejado.</p>
                    <a href="<?= htmlspecialchars(astronomyInternalUrl('cielo-de-hoy.php'), ENT_QUOTES, 'UTF-8') ?>">Consultar la previsión</a>
                </article>

                <article class="card capability-feature capability-feature--technical">
                    <h2>Información simple y detalle técnico</h2>
                    <p>La primera capa de información intenta responder preguntas directas:</p>
                    <ul><li>qué ocurre;</li><li>cuándo ocurre;</li><li>hacia dónde mirar;</li><li>si está visible;</li><li>cuánto tiempo queda;</li><li>si las condiciones parecen favorables.</li></ul>
                    <p>Cuando querés profundizar, algunas tarjetas incluyen un enlace a Datos técnicos o Ver detalles.</p>
                    <p>Según el evento, allí pueden aparecer:</p>
                    <ul><li>horarios completos;</li><li>ángulos;</li><li>alturas;</li><li>azimut;</li><li>separaciones angulares;</li><li>intervalos de visibilidad;</li><li>contactos de un eclipse;</li><li>magnitud;</li><li>distancias;</li><li>datos meteorológicos;</li><li>mapas.</li></ul>
                    <p>De esta manera, la información principal sigue siendo clara, pero los datos más precisos continúan disponibles.</p>
                    <a href="<?= htmlspecialchars(astronomyInternalUrl('eventos.php'), ENT_QUOTES, 'UTF-8') ?>">Explorar eventos</a>
                </article>

                <article class="card capability-feature capability-feature--calendar">
                    <h2>Agendar eventos</h2>
                    <p>Los eventos que tienen una fecha y una hora confiables pueden agregarse a un calendario.</p>
                    <p>La opción Agendar evento permite abrir el evento ya preparado en:</p>
                    <ul><li>Google Calendar;</li><li>Outlook;</li><li>Apple Calendar;</li><li>otros calendarios compatibles.</li></ul>
                    <p>El evento puede incluir:</p>
                    <ul><li>título;</li><li>fecha y hora;</li><li>duración;</li><li>descripción;</li><li>ubicación;</li><li>zona horaria;</li><li>enlace a la web.</li></ul>
                    <p>La aplicación de calendario siempre solicita una confirmación antes de guardarlo.</p>
                    <a href="<?= htmlspecialchars(astronomyInternalUrl('eventos.php'), ENT_QUOTES, 'UTF-8') ?>">Ver eventos para agendar</a>
                </article>

                <article class="card capability-feature capability-feature--install">
                    <h2>Instalar Aquellas Lunas</h2>
                    <p>Aquellas Lunas puede guardarse para tenerla más a mano.</p>
                    <p>Según el dispositivo, es posible:</p>
                    <ul><li>instalarla como una aplicación en Android;</li><li>agregarla a la pantalla de inicio en iPhone o iPad;</li><li>instalarla como aplicación en una computadora compatible;</li><li>guardarla en los favoritos del navegador.</li></ul>
                    <p>La versión instalada sigue siendo la misma web. Cuando el sitio se actualiza, las nuevas funciones y contenidos también quedan disponibles.</p>
                    <button class="button" type="button" data-install-trigger data-install-source="capabilities">Instalar Aquellas Lunas</button>
                </article>
            </section>

            <section class="card capabilities-closing">
                <h2>Una web para entender y disfrutar el cielo</h2>
                <p>Aquellas Lunas no busca mostrar todos los datos posibles ni reemplazar una formación astronómica.</p>
                <p>Su objetivo es ayudarte a entender qué está pasando en el cielo, saber cuándo conviene mirar y descubrir eventos que pueden pasar inadvertidos.</p>
                <p>La información técnica está disponible, pero la prioridad es que cualquier persona pueda orientarse, observar y disfrutar el cielo sin necesidad de conocimientos previos.</p>
            </section>

            <?php renderAstronomyContactBlock('features_page'); ?>
            <?php renderAstronomyExploreSky('capabilities'); ?>
        </div>
    </main>
    <?php renderAstronomySiteFooter(); ?>
</body>
</html>
