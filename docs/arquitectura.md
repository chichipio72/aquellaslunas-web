# Arquitectura de la web

## Alcance

Este repositorio contiene la capa web pública de Aquellas Lunas. Usa PHP 8.5, HTML, CSS y JavaScript sin frameworks. Los cálculos astronómicos pertenecen a una API separada; PHP valida entradas, consume esa API desde el servidor y evita publicar su URL interna en el navegador.

Las páginas públicas son:

- `index.php`: portada;
- `sol-y-luna.php`: rango diario de Sol y Luna;
- `eventos.php`: efemérides filtrables;
- `eclipses.php`: búsqueda y detalle de eclipses;
- `planificador.php`: planificación cartográfica del Sol y la Luna;
- `fotografia.php`: planificación de encuadres fotográficos lunares a escala angular;
- `ubicacion.php`: configuración de la ubicación global;
- `galeria.php`: fotografías disponibles de la tienda;
- `acerca-del-sitio.php`: contenido institucional;
- `acerca.php`: redirección 301 compatible hacia la página institucional actual.

## Ubicación, encabezado y registro de secciones

La herramienta de Fotografía separa cuatro contratos. `includes/photography-scene.php`
construye el estado astronómico continuo y su clasificación descriptiva;
`includes/photography-geometry.php` calcula sensor, campo visual y encuadre;
`includes/photography-editorial.php` persiste escenas y variantes; y
`assets/js/photography-planner.js` renderiza exclusivamente el modo Esquema. El estado
serializado es la entrada prevista para un futuro renderer Simulado, que no debe
recalcular astronomía en el navegador.

Los objetos brillantes del esquema son una selección de `ConjunctionCatalog` y
conservan las coordenadas calculadas por el motor. Los eclipses se consultan mediante
`astronomyEvents()` y Fotografía sólo clasifica el contrato local normalizado. Las
escenas y variantes activas se relacionan por `primary_scene` sin reemplazar el
estado continuo; `includes/photography-links.php` prepara URLs desde Lo próximo y
Eventos Lunares sin trasladar una focal artística.
Las imágenes editoriales de escena y variante son referencias fotográficas reales
opcionales: su ausencia no altera ningún contrato ni genera una superficie vacía en
administración o en la página pública. Cuando ambas existen, la variante seleccionada
tiene precedencia visual sobre la imagen general de su escena.

`includes/location-context.php` valida y entrega la única ubicación activa. Las páginas
locales no leen cookies ni procesan geolocalización por separado. La estructura incluye
nombre, latitud, longitud, zona IANA y origen. `includes/site-header.php` muestra la
localidad enlazada a `ubicacion.php`; `site-footer.php` centraliza la atribución.

`ubicacion.php` es el único punto de escritura. PHP valida antes de emitir cookies.
Cuando el navegador ya posee una suscripción Web Push activa y una configuración de
avisos asociada, la página puede copiar allí, por decisión explícita del usuario, la
ubicación general recién validada. La identidad se vuelve a comprobar con la
`PushSubscription` completa y el endpoint público actualiza exclusivamente nombre,
coordenadas y zona horaria; no crea configuración ni modifica preferencias o material
criptográfico de la suscripción.
`assets/js/location.js` pide permisos y maneja errores; `assets/js/astro-map.js` crea el
mapa Leaflet, busca con Nominatim y mantiene el marcador del observador. Las futuras
capas o líneas de azimut deben permanecer separadas de ese marcador.
Cuando una sección abre `ubicacion.php`, puede enviar su ruta interna mediante
`return`; el valor validado se conserva como `return_to`. La página permite volver
sin guardar y, después de confirmar, redirige al mismo destino. Se admiten consultas
internas, pero se rechazan esquemas, hosts, rutas fuera de la instalación, fragmentos
y caracteres de control.

`includes/site-menu.php` conserva el catálogo estable de secciones —identificador,
etiqueta y URL— y carga desde `site_menu_groups` y `site_menu_sections` la agrupación,
orden y visibilidades `public_visible`/`admin_visible`. `Inicio` queda fuera de esas
tablas de asignación, siempre primero y visible. `includes/site-sections.php` compone
ese estado con el contrato de swipe; `renderAstronomySiteNavigation()` omite grupos
vacíos y aplica `class="is-active"` y `aria-current="page"` a la página actual.
La sesión administrativa se detecta sin abrir ni alterar la sesión pública.

El recorrido táctil continúa definido por `swipe_enabled` y no se reordena desde el
menú administrativo. Ocultar una entrada afecta sólo la navegación: su URL y sus
restricciones de acceso propias no cambian.

Las vistas públicas invocan el encabezado común con la marca **Aquellas Lunas** y el subtítulo **Una Luna diferente cada noche**. El menú está centralizado en el registro de secciones.

### Contenido de “Qué ofrece Aquellas Lunas”

`que-podes-hacer.php` no contiene texto editorial: carga
`content-data/que-podes-hacer.json` mediante `includes/capabilities-content.php`.
El esquema versión 1 separa hero, tarjetas principales, tarjetas especiales y cierre;
los cuerpos aceptan exclusivamente bloques `paragraph` y `list`. Iconos, estilos y
acciones se validan contra contratos controlados, los enlaces son internos y todo
texto se escapa. Un archivo ausente o inválido produce un estado público controlado
y registra el diagnóstico. La carpeta se transfiere en despliegues pero `.htaccess`
y la configuración Apache local impiden descargarla directamente.

## Includes compartidos

- `api-config.php`: carga y valida configuración.
- `api-client.php`: cURL, reintento controlado, métricas y cabeceras dinámicas sin caché.
- `current-datetime.php`: reloj editorial real/simulado. En el entorno local habilitado persiste una fecha y hora de pared en sesión; producción usa siempre el reloj real.
- `astronomy-icon.php`: mapeo visual compartido para fases y eventos secundarios. Traduce `moon_phase/new_moon|first_quarter|full_moon|last_quarter`, `earthshine`, `conjunction`, `libration_*`, `apsis/perigee|apogee` y cualquier `eclipse` a clases `astro-icon--*`; los tipos desconocidos usan `astro-icon--generic`. Recibe la latitud para orientar los cuartos y la luz cenicienta según hemisferio.
- `site-menu.php`: catálogo, grupos, orden y doble visibilidad del menú.
- `site-sections.php`: composición del menú y contexto de swipe resuelto por PHP.
- `tonight.php`: contrato tolerante y presentación compartida de visibilidad nocturna.
- `location-context.php`: lectura, validación, fallback y guardado de ubicación.
- `site-header.php`, `site-footer.php` y `location-map.php`: interfaz compartida.
- `event-presentation.php`: títulos, resúmenes y detalles técnicos de eventos.
- `home-sky.php`: validación de Luna instantánea y avisos de salida.

`api-config.php` también es la única fuente de detección del entorno.
`appEnvironment()` sólo reconoce `APP_ENV=local`; cualquier ausencia o valor distinto
es producción. Las herramientas técnicas consultan `canUseSiteDebugTools()`, que
acepta local o una sesión administrativa válida. Una bandera
de una función particular —simulador, timings, navegación u otra— nunca debe usarse
como indicador general del entorno.

## Infraestructura de contenidos

`includes/content-system.php` carga el catálogo editorial exclusivamente desde
MySQL mediante la capa `includes/content-database.php`. El loader aplica
validaciones de contrato y devuelve un catálogo seguro (vacío + diagnósticos) si la
conexión falla, sin propagar excepciones a las páginas públicas.

La validación cubre contrato y metadatos, Markdown, identificadores y opciones de
trivia, imágenes locales y marcadores
`[[imagen]]`, `[[esquema]]`, `[[trivia]]` y `[[embed]]`. El renderizador Markdown propio implementa
un subconjunto deliberadamente pequeño y escapa primero todo texto. Los componentes
futuros se emiten como marcadores visibles, sin ejecutar contenido arbitrario.

`[[embed url="..."]]` es una directiva controlada, no HTML libre. La política
central de proveedores admite HTTPS con host exacto `chichipiosblog.com.ar` y
rutas bajo `/astronomia/`, además de los widgets propios con host exacto
`aquellaslunas.com.ar` y rutas bajo `/astro/embeds/`. El renderer es el único que
define iframe, sandbox, carga diferida y presentación responsive. Una directiva
inválida se omite de la vista pública y queda como advertencia editorial.

El iframe se emite con `sandbox="allow-scripts allow-same-origin"`,
`referrerpolicy="strict-origin-when-cross-origin"` y `loading="lazy"`; no admite
usuario, contraseña, puerto, esquemas distintos de HTTPS, traversal, barras
codificadas ni rutas fuera del prefijo autorizado. `.content-embed` ocupa el 100 %
de su columna, usa altura acotada en escritorio y una altura mayor bajo 700 px para
conservar la utilidad de herramientas verticales. Lleva
`data-swipe-navigation-ignore` para no competir con la navegación táctil global.

Las imágenes reutilizables se resuelven exclusivamente desde
`assets/images/tienda/previews/contenido/`. Un único resolver filtra archivos JPG/JPEG, PNG y
WEBP mediante extensión y contenido real. Conserva la imagen solicitada cuando
existe; si falta, selecciona una alternativa válida al azar. Una carpeta ausente o
vacía produce contenido sin imagen. Los reemplazos nunca invalidan entidades y sólo
se informan como advertencias cuando el debug local está activo.

Las páginas públicas nunca enlazan los originales: artículos, bloques, trivias y
“Sabías que…” usan únicamente esas previews limitadas. Las fotografías reciben la
clase `js-protected-photo`, no son enlaces y deshabilitan arrastre y menú contextual
mediante una mejora JavaScript progresiva. Apache rechaza hotlinking casual desde
referers externos sólo para esta carpeta, manteniendo dominio público, entorno local
y solicitudes sin Referer. Estas medidas son exclusivamente disuasorias: una
preview mostrada puede recuperarse con herramientas de desarrollo, caché o captura
de pantalla. El clic derecho no es seguridad; la protección efectiva depende de no
publicar el original, limitar resolución y conservar la marca de agua de la preview.

`contenidos.php` muestra el índice y `contenido.php?slug=...` resuelve artículos. La
portada usa una carga focalizada: elige una trivia y una entrada “Sabías que…”
visibles, carga sólo las opciones de la trivia elegida y conserva únicamente el slug
de sus artículos para construir los enlaces. No arma el catálogo editorial completo.
Las opciones de trivia se mezclan sobre una copia. En modo usuario sólo participan
entidades válidas y visibles. Las pantallas que necesitan índice o detalle conservan
la carga del catálogo completo.

### SEO, navegación y relaciones de contenidos

El SEO de contenidos parte del catálogo MySQL ya validado. Un artículo sólo se
considera público cuando existe, es válido, está visible y no se abrió mediante la
preview administrativa. En ese caso `contenido.php` publica `index, follow`, canonical
individual estable por slug, Open Graph de tipo `article` y JSON-LD `Article`; si hay
fecha de actualización o imagen principal válidas se incorporan como `dateModified`
e `image`. La misma respuesta incluye un `BreadcrumbList` Inicio → Contenidos →
artículo y renderiza esa ruta como `<nav>` visible y accesible. Artículos inexistentes,
inválidos, ocultos o en preview usan `noindex, nofollow`.

`contenidos.php` es una `CollectionPage`: sin búsqueda usa `index, follow`; con una
consulta `q` mantiene la canonical limpia del índice y cambia a `noindex, follow` para
evitar indexar combinaciones internas sin impedir el seguimiento de sus artículos.
`sitemap.php`, servido públicamente como `/astro/sitemap.xml`, agrega dinámicamente
únicamente artículos válidos y visibles con esa misma canonical y `lastmod` cuando
`actualizado_en` contiene una fecha válida.

El bloque **Contenidos relacionados** consume exclusivamente la lista dirigida y
ordenada de `contenido_articulos_relaciones` del artículo origen. El helper elimina
duplicados y autorrelaciones, descarta slugs inválidos y destinos ocultos o inválidos,
y conserva como máximo los tres primeros. No infiere reciprocidad ni usa palabras
clave como sustituto editorial.

El slug publicado es parte de la URL pública estable. El editor permite modificarlo
porque sigue siendo una herramienta técnica, pero muestra una advertencia explícita
sobre enlaces externos e indexación. No existe historial, alias ni redirección desde
el slug anterior.

`includes/content-debug.php` administra ese modo mediante la sesión local y un POST
con CSRF. Requiere `canUseSiteDebugTools()`. Tanto enlaces como páginas directas y loader comprueban
`isContentEnabled()`.

### Criterio permanente para controles interactivos

Todo enlace, botón o control nuevo debe usar un patrón visual explícito y coherente
con el sitio; nunca se publica con la apariencia nativa del navegador. Para acciones
de tarjeta se reutiliza `.home-v2-card__link`, para acciones generales las variantes
de `.button`, y para elecciones compactas `.interactive-choice`. Todos los patrones
deben incluir estados de foco visibles, interacción por teclado y comunicar estados
sin depender únicamente del color.

La trivia de portada mezcla las opciones en el servidor, pero cada opción conserva
su propia condición de correcta derivada de la presencia de `explicacion`. El
cliente bloquea selecciones posteriores, marca textualmente la respuesta correcta y
publica resultado y explicación mediante una región `aria-live`.

### Editor de contenidos

El único editor operativo vive en `/admin/contenidos/`, exige autenticación
administrativa y persiste exclusivamente en MySQL. El editor local antiguo fue
retirado; el sitio público y `/admin/` no dependen de herramientas locales.

## Web Push MVP

El piloto Web Push permite configurar cada dispositivo desde `/notificaciones.php`. El permiso y la suscripción se solicitan sólo después de una acción explícita. La página obtiene la `PushSubscription` real del navegador y el backend exige coincidencia exacta de endpoint, hash, `p256dh` y `auth`; ningún ID numérico funciona como credencial ni se devuelven secretos en las respuestas. La ubicación guardada para avisos es independiente de la ubicación general del sitio. El catálogo público excluye tipos no disponibles y administrativos. `/admin/notificaciones-prueba.php` y `/admin/notificaciones-astronomicas.php` continúan como herramientas privadas de prueba, supervisión y corrección.

`web-push/subscribe.php` registra o reactiva la suscripción y `web-push/unsubscribe.php` marca la fila inactiva antes de retirarla del navegador. `web-push/device-config.php` atiende únicamente solicitudes JSON de origen propio con CSRF de sesión y guarda configuración y preferencias en una transacción mediante la capa común. El service worker sólo atiende `push` y `notificationclick`: no contiene caché, precache ni comportamiento offline.

Mientras dure el piloto, el menú puede incluir **Administración** → `admin/` para
permitir el ingreso desde una PWA standalone. Sus visibilidades pública y administrativa
se controlan desde **Menú y secciones**. Ocultarla no deshabilita la ruta, que sigue
reutilizando el login, la sesión y el CSRF existentes y no concede acceso por sí misma.

La migración automática `20260817_site_menu_configuration` crea las dos tablas y
conserva cada valor público previo de `menu.*.enabled`. La visibilidad administrativa
inicial es verdadera para todas las secciones; `Pruebas visuales` queda explícitamente
sólo para administración, y Galería también queda sólo administrativa cuando su valor
público previo estaba apagado. Los grupos iniciales son Eventos, Explorar,
Configuración y Sobre Aquellas Lunas. El panel permite crear, renombrar y ordenar
grupos, mover y ordenar secciones y editar ambas visibilidades. Una clave foránea
`ON DELETE RESTRICT` y la validación de aplicación impiden eliminar grupos ocupados.

El endpoint valida método, tipo y tamaño de cuerpo, origen cuando el navegador lo informa, endpoint HTTPS y claves Base64 URL. `includes/web-push.php` persiste mediante una sentencia preparada en `WEB_DB`; el endpoint es único y un alta repetida reactiva y actualiza la misma fila. No almacena ubicación, preferencias ni identidad personal; `user_agent` es diagnóstico opcional.

El panel envía desde PHP en el hosting mediante `minishlink/web-push` 10.1 y el autoloader de Composer. `symfony/polyfill-mbstring` cubre la ausencia conocida de `mbstring`; producción sigue necesitando PHP 8.2+, cURL, OpenSSL con P-256 e iconv. `vendor/` se genera localmente desde `composer.lock` y sólo se incluye en el despliegue al usar `--include-vendor`. La clave privada VAPID se lee sólo desde la configuración externa, nunca se entrega al navegador. El script Python de la mini PC se conserva como alternativa manual. Ambos emisores actualizan éxito/error y desactivan respuestas 404/410.

Las tareas automáticas del hosting entran únicamente por `scripts/run-scheduled-tasks.php`. El orquestador usa un lock no bloqueante, aplica las migraciones compatibles registradas explícitamente y luego procesa pruebas programadas y avisos astronómicos mediante funciones compartidas. Los wrappers anteriores permanecen disponibles sólo para diagnóstico manual. El estado operativo y las migraciones aplicadas se persisten en MySQL para su consulta administrativa.

El catálogo `web_push_notification_types` separa la disponibilidad global de cada tipo de la preferencia del dispositivo y de su estado técnico. Conserva nombre, plantillas, destino y programación predeterminada. Los procesadores sólo sustituyen marcadores permitidos explícitamente por tipo y guardan en el historial el título, cuerpo y URL usados en cada intento real. Los tipos administrativos, como `test`, no aparecen entre las preferencias normales del dispositivo.

Los cuatro avisos astronómicos públicos —salida lunar, eclipses, conjunciones
lunares y tránsitos satelitales— poseen título, cuerpo y URL configurables en ese
catálogo; los procesadores no fijan copias alternativas. Al activar notificaciones,
la página muestra el ID público `AL-XXXXXXXX` únicamente como referencia de soporte
y explica que, ante un problema, el usuario puede contactar por Instagram y
mencionarlo. El ID no se presenta como identificador de seguimiento ni concede
acceso a la configuración.

La portada expone accesos contextuales con campana junto a la próxima salida lunar,
los eclipses, las conjunciones y los tránsitos ISS/Tiangong que corresponda mostrar.
El enlace sólo abre `/notificaciones.php?notification_type=...#tipos-de-aviso`; nunca
cambia una preferencia desde la tarjeta ni crea un recordatorio para ese evento
concreto. El significado es siempre “configurar todos los avisos de esta clase”.
`assets/js/home-notification-links.js` consulta, cuando existe permiso y suscripción,
el estado vigente del tipo para mejorar `aria-label` y `title`; una falla conserva el
enlace neutral y no bloquea la portada.

El editor administrativo valida y guarda el contenido persistido en tablas
editoriales. Su núcleo se mantiene separado de la vista para probar normalización,
carga desde MySQL y diagnósticos de consistencia.

El selector visual de imágenes reutiliza exclusivamente
`astronomyContentAvailableImages()` sobre el nivel superior de
`assets/images/tienda/previews/contenido/`. Descarta extensiones no admitidas y
archivos que no sean imágenes reales. Esos archivos ya son las previews
reutilizables, por lo que no se busca otra variante. La base conserva únicamente el
nombre del archivo.

El campo estructurado `imagen` del artículo representa su imagen principal y es
opcional, igual que en trivias y “Sabías que…”. Se resuelve una vez en el loader y
se muestra en índice y página individual. Los marcadores `[[imagen ...]]` continúan
siendo componentes independientes insertados dentro del Markdown. Antes de guardar,
el editor verifica todos los nombres recibidos por POST contra la lista interna de
la galería, por lo que rutas, nombres ajenos y valores manipulados son rechazados.

`imagen_posicion_x` e `imagen_posicion_y` guardan el punto focal de la imagen
principal como porcentajes de 0 a 100. La ausencia o un valor inválido se normaliza
a 50. El índice muestra una miniatura apaisada 16:9 en una columna editorial del
35 % a la izquierda del texto; bajo 700 px, imagen y texto se apilan. La página individual
separa el H1 del cuerpo Markdown y ordena título, resumen, cabecera y desarrollo.
Las páginas editoriales usan una columna de lectura propia: el artículo completo
admite hasta 1050 px y los bloques de texto se limitan a una línea de lectura más
estrecha de 60 rem (960 px con la raíz predeterminada). Es la única excepción a la regla general de márgenes
uniformes del sitio. Dentro de ese ancho, la imagen principal se muestra centrada en
un contenedor ajustado al tamaño real de la fotografía, con un margen interno breve.
Como excepción editorial, cuando sobra espacio horizontal la imagen principal puede
ampliarse de forma proporcional hasta un 30 % sobre su tamaño natural, sin recorte
y sin deformación. El editor
conserva el original como referencia y muestra aparte una preview 16:9 fiel al
resultado público; habilita el selector de foco cuando hay recorte. Una relación fuera
del rango tolerante 1.70–1.85 produce una advertencia no bloqueante.

Fuera de la imagen principal del artículo individual, ninguna presentación amplía un
archivo por encima de sus dimensiones naturales. El resolver expone ancho y alto
reales como límites CSS; índice, componentes internos y previews pueden reducir la
fotografía con `contain`, pero no aplican zoom. Si el marco disponible es mayor, la
imagen queda centrada y el espacio restante permanece libre. La única excepción es
el hero editorial ya descrito, que puede escalar hasta 1,3×.

Esta política no se propaga a otras clases de recurso. Una fotografía independiente
usa `[[imagen src="archivo.jpg" alt="Descripción"]]` y siempre queda centrada. Para
asociar explícitamente texto e imagen se usa un bloque cerrado:
`[[bloque-imagen src="archivo.jpg" alt="Descripción" posicion="derecha"]] ... [[/bloque-imagen]]`.
Admite `derecha` o `izquierda` y se renderiza como Grid autocontenido, sin flotados;
en móvil el texto queda primero y la imagen centrada después. Las fotografías
verticales usan `min(70%, 240px)` y, hasta 380 px, `min(60%, 210px)`; las
horizontales pueden ocupar `min(90%, 480px)`. Las alineaciones
laterales antiguas de `[[imagen]]` siguen siendo legibles como figuras centradas y
producen una recomendación de migración en debug local. Todas usan `contain`, ancho
máximo y altura automática. El futuro componente
`[[esquema ...]]`, incluidos SVG e ilustraciones, también deberá usar `contain` y
nunca recortar su contenido.

La interfaz de edición conserva un único formulario y distribuye sus campos en tres
paneles ARIA: Contenido, “Sabías que…” y Trivias. Los dos últimos usan un patrón
maestro-detalle: una lista compacta controla mediante `hidden` cuál bloque del mismo
formulario queda visible. Cambiar de solapa o selección no reconstruye nodos ni
modifica sus valores. Las solapas y listas admiten flechas, Inicio y Fin; los
controles mantienen foco visible. La solapa activa viaja en un campo oculto del
formulario y se incorpora a la redirección posterior al guardado, por lo que
Contenido, “Sabías que…” o Trivias permanecen seleccionadas tanto después de un
guardado exitoso como ante una validación fallida.

El navegador actualiza en la lista el título/pregunta y la visibilidad mientras se
escribe. Los errores del servidor se señalan tanto en el elemento de la lista como
junto al campo correspondiente. Altas y bajas sólo alteran el modelo DOM hasta
guardar; la baja utiliza un diálogo integrado y el formulario avisa con
`beforeunload` si se intenta salir con cambios pendientes.

El índice local calcula el estado editorial agregando incidencias del artículo, sus
trivias y sus tarjetas: sin incidencias es **Válido**, con warnings no bloqueantes es
**Con advertencias**, y con cualquier error es **Con errores**. Al editar, cada
warning conserva la ruta de campo y el mensaje del resolver, incluidos el nombre
solicitado y la alternativa elegida.

Las advertencias de imagen se etiquetan por origen: imagen principal, trivia,
“Sabías que…” o imagen embebida. Los componentes Markdown conservan número ordinal
y línea aproximada calculada desde el offset del marcador. El editor ofrece
**Ir al componente**, activa la solapa Contenido y selecciona esa línea en el
textarea; nunca reemplaza automáticamente el nombre de imagen escrito en Markdown.

Trivias y tarjetas “Sabías que…” no mantienen destinos editoriales. Los bloques
`referencia` presentes en archivos históricos se ignoran silenciosamente al cargar
y se eliminan al volver a serializar el artículo. Por eso estas entidades no pueden
fallar por un artículo o ancla inexistentes y “Sabías que…” no muestra un enlace
“Leer más” sin destino.

Si un POST no supera la validación, el editor conserva el modelo normalizado en el
formulario y presenta **No se guardaron los cambios**, la cantidad de errores y el
detalle. Cada solapa afectada recibe un contador y cada colección mantiene sus
indicadores por elemento; un guardado exitoso continúa con redirección 303 y una
confirmación inequívoca.

En la solapa Contenido, slug, versión y visibilidad forman una fila compacta. La
imagen principal vive en un único panel con el original —máximo 220×390 px— y la
vista 16:9 del hero lado a lado; en móvil se apilan. Título, resumen y demás
metadatos continúan después a ancho completo, fuera de esa grilla visual.
- `asset-url.php`: query `v=<filemtime>` para assets locales.
- `analytics.php`, `seo.php` y `favicon-links.php`: cabecera pública.
- `moon-images.php`: miniaturas lunares estáticas.

## Motor PHP y selección de fuentes

`astronomy-engine/` es la unidad portable integrada. `includes/api-client.php`
registra manualmente el namespace PSR-4 `AstronomyEngine\` desde
`astronomy-engine/src/`; el motor no depende del mapa de Composer ni de
`vendor/`. `includes/astronomy-data.php` resuelve `daily`, `range`, `directions`,
`moon/instant`, `altitude-profile` y `tonight` con la fuente `api` o `php`
seleccionada en administración. La otra fuente actúa como fallback técnico.
`moon/image` permite `static` o `api`, con la alternativa como fallback y el PNG
pequeño como último recurso. Las excepciones son la Luna grande de “El cielo
hoy” en portada y `luna-fecha-favorita.php`: usan el componente estático Three.js de
`assets/js/moon-three-render.js`, geometría PHP embebida y configuración
`home.moon_three.*` en portada y `favorite.moon_three.*` en la sección de fecha
favorita. La portada conserva `moon-image.php` como fallback y la sección nueva
compone sus descargas en el navegador.

`luna-fecha-favorita.php` ofrece composición vertical 1440 × 2560 y apaisada
2560 × 1440, con el bloque editorial opcional en ambas. Usa
`favorite.moon_three.*`, independiente de la portada y con controles adicionales
para color, brillo y degradado horizontal del fondo. La vista inicial conserva el
normal configurado; recién al exportar intenta cargar el normal map 8K y mantiene el
estándar como fallback cuando el dispositivo no admite texturas de 8192 píxeles o la
carga falla.

`luna-interactiva.php` reutiliza `MoonDiskAppearanceCalculator`, albedo y normal
map, pero monta `assets/js/interactive-moon.js`: una escena explorable que parte
de la orientación/libración real y proyecta etiquetas HTML desde coordenadas 3D.
Su apariencia usa el namespace persistente `interactive.moon_three.*`, separado
de portada y fecha favorita. Conserva los mismos trece controles básicos del
render; `size_percent` define la escala inicial y el zoom del visitante se aplica
sobre ella. La página pública y `embeds/luna-interactiva.php` consumen el mismo
conjunto.
El normal map global 8K derivado offline del DEM LOLA 64 ppd se conserva como
segunda capa progresiva de este visor. En modo automático se solicita al alcanzar
el zoom configurado (1,3 por defecto), nunca durante la carga inicial; los modos
estándar y 8K permiten forzar el recurso. Si `MAX_TEXTURE_SIZE` es menor que 8192
se mantiene silenciosamente el mapa estándar. Una vez activado, el 8K permanece
en la sesión para evitar cambios repetidos de textura. El visor ampliado de portada
reutiliza esta misma política al abrirse o hacer zoom, y el exportador de fecha
favorita reutiliza el recurso y la comprobación técnica sólo al iniciar una descarga.
El catálogo curado `assets/data/moon-features.json` separa cráteres, mares, otros
accidentes y alunizajes, con importancia 1–2. Los nombres geográficos provienen
del Gazetteer IAU/USGS (planetocéntrico, este positivo); los alunizajes usan
referencias NASA/NSSDC. La visibilidad se decide con la normal transformada de
cada punto, las colisiones se resuelven en pantalla y el detalle adicional exige
zoom. Capas, detalle, giro y zoom son reproducibles mediante query string; con
`embed=1` se omiten navegación y pie. `illumination=realistic|full` alterna entre
el punto subsolar calculado y una luz frontal fija respecto de la cámara; el modo
completo no altera orientación, libración ni coordenadas de superficie.

La búsqueda pública mantiene otro conjunto de datos: `assets/data/moon-gazetteer.json`
es una instantánea local de los accidentes lunares aprobados por IAU/USGS y se
regenera offline con `tools/update-moon-gazetteer.py`; nunca genera etiquetas por
sí mismo ni consulta USGS durante la navegación. Se combina en cliente con los
alunizajes curados. El objeto elegido se materializa como un marcador temporal y
orienta el globo hacia sus coordenadas. `assets/data/moon-geology-experiment.json`
aporta bloques científicos opcionales para las diez fichas piloto, siempre
conservando tipo de evidencia, fuentes y limitaciones; el resto recibe sólo la
ficha básica del Gazetteer.

`assets/data/moon-geology-auto.json` mantiene separado el enriquecimiento
reproducible. `tools/build-moon-geology-auto.py` une el punto central del
Gazetteer con los polígonos del mapa geológico USGS 1:5M y sólo incorpora cruces
de cráteres LPI clasificados como seguros por nombre, coordenadas y diámetro.
Los matches probables o ambiguos quedan en `moon-geology-auto-report.json`, no en
las fichas. Las métricas conservan si son medidas, modeladas o estimaciones
publicadas. Esta capa es contexto científico opcional y nunca controla etiquetas.

Las recomendaciones de cráteres de “Esta noche” se calculan en
`includes/moon-crater-recommendations.php` para portada y página detallada. El
índice compacto `moon-crater-observation.json`, regenerable con
`tools/build-moon-crater-observation-catalog.py`, contiene sólo cráteres nominales
de al menos 20 km y referencias a los indicadores científicos ya importados. La
puntuación combina iluminación rasante del lado iluminado, distancia al limbo,
diámetro, topografía, morfología, ficha científica y catálogo curado. Si la Luna
no alcanza 10° durante el tramo restante de oscuridad civil o ningún candidato
supera el umbral, el bloque no se presenta.

La presentación en español se resuelve sin alterar esos catálogos mediante
`assets/data/moon-geology-es.json`. El recurso reúne un vocabulario controlado,
nombres públicos de fuentes y traducciones de las unidades USGS indexadas por su
código. Las descripciones e interpretaciones se traducen una sola vez por unidad y
se reutilizan en todas las fichas que caen en ella. Las etimologías individuales
del Gazetteer permanecen en el dato original, pero no se muestran mientras no haya
una estrategia de localización trazable.

Los widgets de contenidos no reutilizan la página pública como iframe genérico:
`embeds/luna-interactiva.php` ofrece un wrapper dedicado sobre el mismo módulo,
con estado inicial, capas, bloqueo de rotación y controles parametrizados por URL.
`embeds/libracion-lunar.php` genera 121 muestras compactas entre la Luna nueva
anterior al instante vigente y la siguiente, calculadas con
`PrincipalPhaseCalculator`. Ambos modos recorren exactamente esos mismos límites;
`assets/js/lunar-libration-widget.js` interpola libración, orientación, fase y Sol
en cada frame. La orientación inicial usa `lunar_north_screen_angle_degrees`,
calculado topocéntricamente con la ubicación central del sitio; durante el ciclo
se congela esa referencia de pantalla y varía sólo el ángulo del eje lunar para
no comprimir la rotación diaria del campo en una oscilación rápida. El tiempo avanza siempre
hacia adelante y el cierre aplica un blend visual suave sólo sobre el último 2 %
del ciclo. La administración puede capturar un ciclo como
WebM 1280 × 720 o 720 × 1280 en el navegador mediante `captureStream` y
`MediaRecorder`; el video compone sólo fondo y canvas, sin controles ni readout.
El payload de este widget carga `favorite.moon_three.*`: luz principal y ambiente,
tratamiento de textura, relieve, exposición, tamaño y composición del fondo se
comparten sin parámetros visuales adicionales entre la previsualización del
administrador, la URL embebida y el WebM exportado.
La cámara compensa el campo horizontal de los marcos verticales antes de aplicar
el `zoom` configurable, por lo que el disco completo entra también en 9:16.
`embeds/fases-tierra-luna.php` agrega la comparación recíproca: toma el mismo
instante y vector solar de `MoonDiskAppearanceCalculator`, ilumina la Tierra con
el vector opuesto y conserva `iluminación Tierra = 1 - iluminación Luna`. El
punto terrestre central se obtiene de la ascensión recta geocéntrica lunar y el
tiempo sidéreo de Greenwich; el diámetro visible terrestre se representa 3,67
veces mayor. La fecha y hora iniciales, autoplay, velocidad y controles forman
parte de la URL, y el ciclo cliente usa las mismas 121 muestras de una lunación.
La referencia de norte celeste queda fijada para el observador en el instante
seleccionado: la Luna varía sólo por libración y el eje terrestre conserva su
inclinación en pantalla, sin comprimir la rotación diaria del campo en el ciclo.
El generador codifica además parámetros Three.js independientes por cuerpo. Luna
y Tierra separan luz principal, ambiente, exposición y rugosidad; la Luna expone
también los dos componentes de `normalScale`. Son estado reproducible de la URL,
no configuración persistente ni valores compartidos con los otros widgets.
`admin/widgets-lunares/` genera preview, URL pública e iframe. Conserva en
`localStorage` la última configuración de cada widget y la selección activa para
sobrevivir una recarga del navegador. Esta preferencia local no modifica widgets
ya insertados ni constituye configuración global. El campo “URL del embed” también
funciona como entrada: reconoce cualquiera de las nueve rutas del generador,
selecciona el widget y aplica sus parámetros a los
controles y a la preview; parámetros futuros sin control visible se conservan al
regenerar la URL durante esa sesión. Estas superficies no forman parte del menú ni
del sitemap.

Las dos rutas adicionales son `embeds/escena-lunar.php`, una composición lunar
observacional configurable, y `embeds/lunas-llenas-tamano.php`, que compara las
próximas doce lunas llenas contra el diámetro a 384.400 km. La escena mantiene su
estado técnico en la URL, pero el generador puede guardar presets con nombre y
descripción en `lunar_scene_presets`. El endpoint autenticado
`admin/api/lunar-scene-presets.php` valida una lista cerrada de parámetros y permite
crear, actualizar, duplicar o eliminar esos presets con CSRF. Guardarlos no publica
el embed, no cambia la ubicación activa y no los convierte en configuración global.

Los eclipses reales se publican sólo como widgets en
`embeds/eclipse-lunar-real.php` y `embeds/eclipse-solar-real.php`. Sus series,
contactos, discos aparentes y orientación se preparan en PHP desde el motor; el
cliente únicamente interpola y renderiza. En el solar, la fotosfera se representa
como un disco cálido casi uniforme y la corona se compone mediante halo continuo
y filamentos Bézier irregulares. Prominencias, perlas y diamante se anclan al limbo
solar y sólo se revelan en totalidad o alrededor de C2/C3. Son capas visuales
graduables con `corona_level`, `prominence_level` y `baily_level` entre 0 y 10;
no modifican muestras, tamaños ni contactos. Las claves booleanas anteriores se
normalizan únicamente para conservar URLs existentes.
`totality_effects_before_seconds` y `totality_effects_after_seconds` limitan su
aparición fuera de la totalidad a márgenes visuales configurables alrededor de
C2 y C3. La entrada y salida son suaves; los tiempos se comparan contra los
contactos locales ya calculados y no los desplazan ni recalculan.
La cámara del eclipse lunar real mantiene un margen vertical para readout y
controles y aumenta su distancia en formatos angostos según el aspect ratio; el
disco completo debe permanecer dentro del canvas tanto en la preview como en los
modales públicos.
El eclipse lunar real conserva sin cambios el sombreado parcial y suma una
adaptación perceptual exclusiva de la totalidad: `totality_brightness` y
`totality_copper_intensity` se multiplican por una envolvente suave que vale cero
en U2/U3 y alcanza uno en el máximo. Son ajustes visuales de URL y nunca alteran
contactos ni geometría.
Dentro de esa envolvente, el fragment shader calcula para cada punto
`(radio_umbra - distancia_al_centro) / radio_umbra`. Esa profundidad real gobierna
la mezcla borde naranja–zona profunda roja, la caída de brillo, saturación y
contraste de textura. Una perturbación procedural determinista puede deformar
sutilmente la profundidad; con `totality_atmospheric_irregularity=0` desaparece.
Los demás ajustes son `totality_max_darkness`, `totality_gradient_contrast`,
`totality_edge_color`, `totality_deep_color`, `totality_saturation`,
`totality_texture_contrast` y `totality_gradient_softness`.
El fondo del eclipse lunar real admite además `sky_color` y `sky_brightness`.
Son controles puramente visuales del color y luminosidad del cielo; no modifican
la luz de la Luna, la geometría, los contactos ni las muestras astronómicas.
La URL puede ajustar además exposición general; intensidad y color de fotosfera;
oscurecimiento del limbo; color del disco lunar; brillo, color y oscurecimiento
del cielo; e intensidad/color independientes de corona, prominencias y
perlas/diamante. Todos son parámetros exclusivos de presentación consumidos por
el canvas y no entran en los adaptadores geométricos.
La interpolación temporal compartida trata orientación celeste, ángulo del disco,
longitud y acimut como ángulos cíclicos: entre muestras siempre recorre el arco
corto. Esto evita giros visuales completos al cruzar la representación 0°/360°
sin modificar las muestras ni la orientación calculada por el motor.
Al recargar la preview por un cambio de estos parámetros, la administración lee
el progreso temporal vigente y lo pasa sólo a la URL interna mediante
`_preview_progress`. Este parámetro efímero no se incorpora a la URL pública ni
al iframe copiado; evita volver al máximo durante la calibración visual.

`embeds/eclipse-solar-espacio.php` agrega la vista geocéntrica didáctica sin
integrarla a superficies públicas. `SolarEclipseShadowGeometry` reutiliza las
efemérides de `MeeusEclipsePositionCalculator` y entrega simultáneamente vectores
geocéntricos ecuatoriales inerciales y su transformación al marco terrestre
rotante, además de eje, radios de penumbra y núcleo, tipo umbra/antumbra y su
intersección con la esfera terrestre. Los contactos globales de esta escena
se refinan desde la tangencia de esos conos con la Tierra. El shader de la Tierra
calcula por fragmento la separación y radios aparentes de Sol y Luna desde cada
punto superficial, aplica el solapamiento de discos sólo en el hemisferio diurno
y conserva el terminador mediante iluminación paralela. La trayectoria del mesh
lunar interpola el vector inercial por timestamp y después normaliza una sola vez
para aplicar la distancia visual constante configurada. La rotación sidérea se
aplica exclusivamente a la Tierra y a la localización superficial de la sombra.
No hay autoencuadre ni normalización por frame dependiente del eclipse; la escena
comprime sólo la distancia visual Luna–Tierra y lo declara. Radios relativos y
geometría de sombra permanecen en unidades físicas del motor.

La integración pública de eclipses comparte `includes/eclipse-widget-embed.php`
entre portada, Eventos lunares y Eclipses. Las URLs guardadas en Configuración
del sitio son plantillas completas: se valida host, HTTPS y ruta del widget, se
preserva cualquier parámetro presente y se sustituyen exclusivamente `date`,
`lat`, `lon` y `elevation`. El host de la plantilla nunca se usa para renderizar:
el iframe apunta a la ruta del mismo entorno que sirve la página, para que preview
y detalle público ejecuten la misma versión del widget. Si una plantilla no corresponde al widget esperado,
se intenta el valor predeterminado seguro; si tampoco puede construirse, el
detalle conserva su imagen representativa. El aviso de portada consulta el grupo
`eclipse` por separado del ranking de “Lo próximo” y sólo admite circunstancias
locales cuya `visibility_classification` no sea `not_visible`.

`includes/astronomy-events.php` es el punto único para eventos. Fases, ápsides,
nodos, libraciones, conjunciones y eclipses admiten `api`, `database`, `php`,
`auto` y `compare`. `earthshine` y `full_moon_observation` se calculan primero
con sus fachadas PHP y conservan la API como fallback técnico.

La selección llega hasta `LunarEventCalculator`: una consulta PHP de un solo
grupo ejecuta exclusivamente ese fenómeno, y una consulta combinada ejecuta
sólo la unión pedida. La caché exacta incluye los grupos en su clave. Omitir la
selección al invocar directamente el calculador conserva el cálculo completo
compatible. No existe todavía reutilización entre rangos contenedores.

Los modos configurables significan:

- `api`: API primaria; ante falla técnica, MariaDB dentro de cobertura y luego PHP;
- `database`: exclusivamente `astronomical_events`;
- `php`: exclusivamente la fachada portable;
- `auto`: MariaDB si cubre todo el intervalo 1900–2050 y PHP fuera de cobertura o ante indisponibilidad de la base;
- `compare`: MariaDB como resultado principal y PHP sólo como diagnóstico, sin modificar ninguna fuente.

La API Python es opcional para las funcionalidades migradas. La prueba final de
cierre debe detenerla y ejecutar smoke tests de Inicio, Esta noche, Sol y Luna,
Planificador, Eventos, Eclipses, `altitude-profile.php` y `moon-image.php`; luego
debe restaurarse el servicio y confirmarse nuevamente HTTP 200. La migración no
incluye `satellite-lunar-transits`.

## Eclipses y mapas mundiales

`eclipses.php` valida el formulario, limita la búsqueda exclusiva de eclipses a cinco
años y consume server-side `astronomyEvents()` con `types=eclipse`. El límite se refleja
en los controles del navegador y se vuelve a comprobar antes de consultar la fuente;
un exceso muestra “El intervalo máximo de consulta es de 5 años.” El listado
no renderiza mapas. Cada evento contiene un `<template>` que `assets/js/eclipses.js`
clona dentro de un `<dialog>`.

El detalle se implementa en `includes/eclipse-detail-component.php`. Ese componente
construye el modelo, el identificador estable, el botón disparador, la plantilla y el
único `<dialog>`. Lo consumen tanto `eclipses.php` como las tarjetas `type=eclipse`
de `eventos.php`; estas últimas usan el evento ya recibido por la consulta general y
no realizan otra petición. `assets/js/eclipses.js` resuelve la plantilla por el
identificador del eclipse. Mapas no disponibles producen `null` y no renderizan
sección ni espacio residual. La agenda se construye con el helper común de calendario.

La capa normaliza los contratos de API, MariaDB y PHP al esquema que consume el
componente. MariaDB aporta las circunstancias globales persistidas; cuando no
incluye circunstancias locales, la fachada PHP validada busca el eclipse
correspondiente y completa subtipo, visibilidad, fase visible, clasificación y
contactos del observador sin alterar el registro global. Eclipses soporta los
cinco modos de fuente, incluido el fallback API → MariaDB → PHP.

## Visibilidad de esta noche

Inicio resuelve `tonight` con la fuente API/PHP configurada y `detail=summary`; la página
`cielo-de-esta-noche.php` usa la misma selección con `detail=full`. En ambos casos la capa común recibe la fecha local
resuelta por `get_current_datetime()` y exactamente la ubicación global. No hay endpoint
intermedio ni segunda consulta al abrir tarjetas.

La presentación filtra `not_visible_tonight`. La portada admite `visible_earlier` sólo
si el instante está entre `night.start` y `night.end`; la página detallada conserva los
tres estados visibles y el orden entregado por la API, sin reordenar estrellas.
Direcciones sólo se usan en `visible_now`. `moon_proximity` se redacta como cercanía
visual y nunca se aplica a la propia Luna; `observation_aid` se presenta como ayuda de
observación.

La Luna se conserva en la página detallada sólo cuando coincide con un evento lunar
relevante: Luna llena en alguno de los días civiles atravesados por la noche, o una
conjunción, eclipse, luz cenicienta, oportunidad de Luna llena, ápside o libración
dentro de la ventana nocturna. La consulta de eventos sólo decide protagonismo; las
posiciones y ventanas observables continúan perteneciendo al contrato `tonight`.

La portada puede acompañar el resumen con una escena angular Luna–planetas/estrellas.
`TonightCalculator` entrega `moon_scenes` a partir del mismo catálogo y las mismas
muestras de cinco minutos usadas para las cercanías de hasta 10°; la capa de datos
completa este campo con el motor PHP cuando una respuesta API anterior todavía no lo
incluye. La selección descarta muestras anteriores al reloj vigente (también en modo
simulado), de modo que el esquema siempre representa una oportunidad futura.

La redacción de cercanías aplica una única prioridad editorial: si hay planetas
dentro de 10°, se omiten estrellas del resumen; se menciona un planeta o, si hay dos,
se usa la plantilla administrativa especial para ambos. Sólo cuando no hay planetas
se menciona la estrella cercana con menor separación. Los títulos, el texto de un
encuentro y la variante de dos planetas pertenecen a la configuración editorial del
administrador. Esta prioridad afecta el texto y el ancla de la escena, pero la escena
incluye todos los planetas y estrellas calculados dentro de 10° para su instante.

`includes/home-tonight-scene.php` selecciona el mismo encuentro priorizado
que el texto (incluidas las conjunciones formales), calcula únicamente encuadre y
presentación, y reutiliza la imagen lunar por fase y su rotación aparente. Sin objetos
válidos cercanos no se renderiza figura ni se reserva espacio.
El componente identifica directamente a la Luna y a cada astro, y usa un marco con
fondo completamente opaco para que la geometría calculada no se confunda con el
fondo estelar decorativo de las páginas. Los planetas usan un disco, Saturno añade
un anillo y las estrellas una cruz con núcleo; todos llevan nombre, la Luna su propia
etiqueta y el pie informa hora y escala angular.
La página detallada reutiliza ese mismo modelo junto a las tarjetas de encuentros:
las tarjetas se apilan a la izquierda y la escena ocupa la derecha en escritorio;
en móvil forman una sola columna. La escena puede ampliarse mediante un `dialog`
nativo, a ancho completo en móvil, sin recalcular posiciones ni duplicar el SVG en
el HTML inicial. El disparador admite clic y teclado; el diálogo se cierra mediante
su botón, el fondo o Escape y devuelve el foco al esquema.
`cielo-de-hoy.php` reutiliza también el modelo dentro de “Qué sucede hoy”, junto a
los eventos editoriales de la fecha. Para el día actual antes del mediodía consulta
la noche civil iniciada el día anterior; después utiliza la fecha visible. La escena
sigue sometida al filtro común de oportunidades futuras y desaparece sin dejar
espacio cuando no hay objetos válidos dentro de 10°.

El catálogo independiente de veinte estrellas y todas las constelaciones son contrato
de la API, no datos duplicados en PHP. La vista usa sólo `constellation.name`, omite el
bloque si falta y oculta categorías completas cuando su lista filtrada queda vacía.
Arrays opcionales ausentes en respuestas anteriores se interpretan como categorías
vacías.

La sección gráfica de nubosidad es progresiva y exclusivamente client-side. El HTML
inicial entrega la ventana `night.start`/`night.end` y permanece oculto;
`assets/js/cloud-cover.js` solicita una sola serie Open-Meteo compartida, valida los
cuatro porcentajes y conserva únicamente horas completas dentro de la ventana, incluso
al cruzar medianoche. Usa barras CSS sin librería gráfica y popovers nativos. La clave
de caché incluye coordenadas redondeadas, zona horaria y fecha de la noche; ante fallo
se elimina el nodo completo.

La vista lee los bloques global/local específicos de cada subtipo. `visibility_map` es opcional y sólo se busca en el bloque global. Para publicar una imagen exige `available=true`, `status=available` y `local_filename` simple, sin barras, `..` ni ruta absoluta. `versionedAssetUrl()` genera una ruta relativa al base path de la aplicación. `catalog_url` o `source_url` se validan como HTTP/HTTPS y sólo se ofrecen como enlace; nunca se hace hotlink.

Los GIF de mapas mundiales se generan desde la operación de la API pero FastAPI no los sirve. Se depositan en `assets/images/eclipses/`, se ignoran en Git salvo `.gitkeep` y se transfieren mediante el mirror FTPS. Apache debe poder leerlos (`0644` en local).

`api-config.php` también expone `loadStoreConfig()`: resuelve originales, previews y catálogo desde entorno o configuración externa y valida los permisos del conjunto. Originales y catálogo son almacenamiento privado; sólo los previews pertenecen al árbol público y son consumidos por la galería.

`loadStoreDatabaseConfig()` carga host, puerto, base, usuario y contraseña MySQL con la misma prioridad. `includes/store-database.php` crea bajo demanda una única instancia PDO por request, con `utf8mb4`, modo excepción, fetch asociativo y prepares emulados deshabilitados. Los fallos públicos son genéricos; el log conserva sólo tipo y código técnico sanitizados, nunca DSN ni credenciales.

`scripts/sync-store-photos.php` es un proceso exclusivamente CLI. Recorre sólo el nivel superior de originales, acepta JPG/JPEG sin distinguir mayúsculas, calcula `foto_id` como SHA-256 del contenido y agrega únicamente filas ausentes en `fotos`. No actualiza ni elimina fotos existentes, no toca `pedido_fotos` y no genera previews. Dimensiones y metadatos legibles se extraen antes del `INSERT`; EXIF se incorpora cuando la extensión está disponible. Un conjunto en memoria evita duplicar el mismo contenido incluso durante `--dry-run`.

El esquema revisado usa `fotos.foto_id CHAR(64)` y `fotos.archivo_original` como claves únicas. `pedido_fotos` conserva una copia de `foto_id`, nombre y precio, y su referencia opcional a `fotos.id` usa `ON DELETE SET NULL`. El sincronizador no ejecuta `UPDATE` ni `DELETE`, por lo que no altera ese historial.

`scripts/generate-store-previews.php` consulta fotos con alguno de los dos derivados pendiente. Verifica una vez que el SHA-256 del original coincida con `foto_id`, corrige orientación EXIF y reduce sin ampliar. Genera `tienda/<foto_id>.jpg` (800 px, marca semitransparente distribuida con ícono de Instagram y `aquellas_lunas`) y `contenido/<foto_id>.jpg` (400 px, sin marca). Cada archivo se publica atómicamente y su columna se actualiza sólo después; las fallas quedan aisladas por variante y foto. `--force` regenera ambos derivados y `--dry-run` no escribe archivos ni base.

`galeria.php` usa la conexión PDO compartida y consulta sólo filas disponibles con `archivo_preview_tienda` no vacío, ordenadas por creación descendente. `includes/store-gallery.php` acepta únicamente `tienda/<foto_id>.jpg`; cuadrícula y modal comparten esa preview comercial y nunca consultan originales ni la variante editorial.

## Compra inicial con Checkout Pro

La galería envía por POST únicamente los `foto_id` públicos seleccionados y un CSRF de sesión. `includes/store-checkout.php` vuelve a consultar las filas disponibles con bloqueo, exige una moneda común y recalcula importes en centavos. Pedido, copias históricas en `pedido_fotos` y pago pendiente se crean transaccionalmente; no se aceptan precios, moneda, total o nombres del navegador.

`includes/mercado-pago-client.php` crea la preferencia por HTTPS con Bearer e `X-Idempotency-Key`, sin SDK. Envía un ítem por foto, `codigo_publico` como `external_reference`, URLs configuradas y `auto_return=approved`. El ID se guarda exclusivamente en `preferencia_proveedor_id`; `pago_proveedor_id` queda reservado para el pago futuro. `respuesta_json` conserva sólo ID, modo y fecha, nunca token ni respuesta completa.

Los fallos al crear preferencias mantienen un mensaje público genérico. El log interno distingue errores cURL (errno y descripción saneada), respuestas HTTP de la API (estado y únicamente `error`, `message`, `cause.code` y `cause.description`) y respuestas exitosas inválidas. Los valores se limitan, eliminan caracteres de control y redactan Bearer, tokens conocidos y secretos en query strings; nunca se registra el cuerpo completo.

La consulta de pagos conserva el HTTP de error en `MercadoPagoApiException`. Sólo un 404 de `payment_lookup` se considera terminal y se transforma en `{"status":"ignored"}` con HTTP 200, sin acceder a la base; esto permite usar IDs ficticios en el simulador. Errores cURL, 401/403, 429, 5xx y respuestas 200 inválidas siguen produciendo 503 para permitir reintentos o corrección de credenciales.

Los tres retornos bajo `tienda/` son informativos y no confirman pagos ni habilitan descargas. `webhooks/mercado-pago.php` acepta sólo POST y valida la firma HMAC compuesta por `data.id`, `x-request-id` y el timestamp de `x-signature`. Con secreto vacío sólo admite mocks `test` desde loopback. El body no decide estados: el servidor consulta `GET /v1/payments/{id}` con Bearer y conserva una respuesta estrictamente saneada.

La confirmación bloquea pedido y pago dentro de una transacción. Exige referencia existente y coincidencia exacta de moneda e importe; sólo `approved` marca el pedido pagado. El permiso se crea con 32 bytes aleatorios, vencimiento y máximo configurables. El bloqueo del pedido y la comprobación previa por `pedido_id` evitan duplicar permisos ante reintentos aunque el esquema no tenga una clave única sobre esa columna. `pedido_fotos` nunca se actualiza. No existe todavía un endpoint que consuma el token.

## Administración privada

`admin/` y `admin/index.php` son el punto de entrada del panel privado, no enlazado desde el sitio público. `includes/store-admin-navigation.php` comparte el menú; los títulos de las tarjetas del panel y los encabezados de cada módulo respetan sus mismos nombres. El cierre de sesión permanece como acción separada. Las páginas administrativas reutilizan `renderFaviconLinks()` y publican el mismo conjunto SVG/ICO/PNG/Apple Touch Icon que la web pública, ajustando únicamente el prefijo relativo.

`admin/fuentes-astronomicas/` separa **Cálculos astronómicos generales** de
**Eventos astronómicos** y muestra solamente las fuentes válidas de cada
funcionalidad. `daily`, `range`, `directions`, `moon/instant`,
`altitude-profile` y `tonight` permiten elegir API o PHP; `moon/image`, colección
precalculada o API; los grupos de eventos muestran sus cinco modos. **Todo a
API** y **Todo a PHP** preparan los selectores compatibles sin guardar
automáticamente y conservan el valor actual donde la fuente pedida no existe.

`includes/store-admin-auth.php` conserva la autenticación histórica: cookie de sesión `aquellas_lunas_admin`, estado `store_admin_authenticated`, cookie HttpOnly/SameSite=Lax, validación mediante `password_verify()`, regeneración del ID, CSRF y destrucción completa. `admin/login.php` dirige al panel general después de autenticar y `admin/logout.php` mantiene el cierre por POST. Todas las respuestas administrativas usan `no-store` y `X-Robots-Tag: noindex`.

## Diagnóstico astronómico común

El registro histórico de `includes/api-client.php` se reutiliza para API, PHP,
MariaDB y assets lunares estáticos. Cada entrada conserva el contexto de uso y
registra fuente solicitada/usada, `source_ms`, tiempo total, fallback, error y
cantidad de resultados. Las entradas API mantienen además HTTP, cURL, intentos,
JSON y `Server-Timing`.

`renderAstronomyTimings()` sólo se muestra con la autorización técnica ya
existente. Antes de las operaciones informa el tiempo PHP completo transcurrido
desde `$_SERVER['REQUEST_TIME_FLOAT']`. Las barras auxiliares tienen ancho fijo,
normalizan `source_ms` contra el máximo del bloque y no alteran las mediciones.

La configuración editorial de tipos de eventos se mantiene separada de
`admin_configuracion_sitio`: `admin_tipos_eventos` identifica de forma estable
cada tipo persistido o derivado y guarda nombre, habilitación, relevancia
nocturna y renderer cerrado;
`admin_tipos_eventos_superficies` relaciona esos tipos con un catálogo cerrado de
superficies públicas. `includes/event-type-configuration.php` centraliza el
catálogo, la inicialización idempotente, la resolución de los eventos recibidos y
el fallback seguro. El frontend filtra después de recibir la respuesta de la API,
por lo que esta capa no modifica PostgreSQL, generación, cálculos ni contratos de
la API. Las consultas pueden limitar tipos para evitar trabajo innecesario, pero
el filtro central por tipo y superficie sigue siendo la autoridad de publicación.

`includes/editorial-configuration.php` implementa la segunda capa. Su catálogo
cerrado declara bloques, parámetros tipados, unidades, límites, defaults, textos y
placeholders permitidos/obligatorios. MySQL guarda sólo diferencias en
`admin_parametros_editoriales` y `admin_textos_editoriales`. Las bandas se validan
como secuencias estrictamente crecientes y todas las escrituras son preparadas y
transaccionales. Ante una falla administrativa, los lectores usan los defaults y
registran un diagnóstico genérico. Para nubosidad, PHP publica únicamente un JSON
escapado con valores ya validados; JavaScript no interpreta expresiones. El
catálogo también controla cupos y orden de categorías de destacados, nombres de
fases, compositores lunares y de eclipses, cinturón de Venus, recomendaciones de
nubosidad y resúmenes de “Sol y Luna”.

La ruta canónica de contenidos es `/admin/contenidos/`. La navegación calcula rutas válidas según la ubicación del script actual en `/admin` para evitar prefijos relativos frágiles.

`includes/store-admin-photos.php` consulta todas las fotos mediante la conexión PDO compartida y modifica con sentencias preparadas sólo disponibilidad, precio y los campos editoriales `titulo`, `descripcion` y `palabras_clave`. Estos últimos son opcionales, se recortan, validan a 255/5000/2000 caracteres y se guardan como texto o `NULL`. No interpreta HTML ni actualiza EXIF, `metadatos_json`, monedas o previews. La asignación múltiple de precios sigue siendo transaccional; el portal no muestra hashes, originales o nombres de archivo, no borra y no toca `pedido_fotos`.

El editor de Contenidos en `/admin/contenidos/` persiste en MySQL con transacciones únicas sobre `contenido_articulos`, `contenido_articulos_palabras_clave`, `contenido_articulos_relaciones`, `contenido_trivias`, `contenido_trivia_opciones` y `contenido_sabias_que`. Su importador editorial JSON valida un paquete completo y crea únicamente slugs nuevos. El script `scripts/migrations/import-content-to-web-db.php` se conserva sólo como referencia de la migración inicial desde PHP.

### Herramientas autorizadas por sesión administrativa

En producción, la sesión administrativa ya habilita simulación temporal, timings y diagnóstico editorial sólo para ese navegador. En local las capacidades técnicas dependen de `APP_ENV=local` y de sus interruptores específicos. La sesión pública `aquellas_lunas_local` conserva el estado de simulación/debug sin contener la autenticación.

### Regla de sesiones en admin

Regla permanente: ningún módulo administrativo debe incluir componentes que puedan abrir otra sesión antes de ejecutar `startStoreAdminSession()` y `requireStoreAdminAuthentication()`. Esta regla evita conflictos con la sesión local de simulación temporal (`aquellas_lunas_local`).

### Laboratorio Astronómico

El laboratorio ofrece los modos excluyentes Serie diaria y Extremos locales. El segundo reutiliza las expresiones controladas de `includes/astronomy-laboratory.php`; `includes/astronomy-laboratory-extrema.php` limita las variables admitidas y calcula máximos y mínimos diarios mediante CTE, `LAG()` y `LEAD()`. No persiste indicadores ni modifica `datos_astronomicos`. La clave primaria `fecha` proporciona el índice usado para acotar cada consulta.

Como referencia local del 31 de julio de 2026, sobre MariaDB 10.11 y distancia lunar, las medianas de tres ejecuciones fueron aproximadamente 68 ms para 5 años, 69 ms para 10, 85 ms para 20 y 168 ms para todo 1900–2100. Una expresión derivada (`duracion_dia`, 10 años) tardó aproximadamente 72 ms. Son mediciones orientativas del entorno local, no objetivos contractuales.

La referencia completa de autenticación, estructura administrativa, contrato JSON,
catálogo de variables, ejes, ambos modos de análisis y reglas de extensión está en
[Administración y Laboratorio Astronómico](administracion-y-laboratorio.md).

## Planificador de direcciones

`planificador.php` toma exclusivamente la ubicación global y presenta fecha y hora
editables. El navegador consulta `astronomy-directions.php` una vez en la carga inicial
y después sólo al confirmar **Mostrar direcciones**. Editar campos o cambiar capas no
consulta la API; el aviso de cambios distingue el estado editado del último aplicado.

El mapa tiene estados `updated`, `pending`, `loading` y `error`. Pendiente y carga
conservan las capas anteriores atenuadas, deshabilitan manejadores y controles Leaflet
y presentan un overlay con `aria-live`. **Actualizar mapa** invoca la misma función que
el submit principal. Volver exactamente a los valores aplicados restaura el estado
normal sin red; una falla mantiene el mapa anterior y habilita reintento.

La fecha y hora manuales sólo se aplican al confirmar. `Usar ahora` y los cuatro
horarios de salida/puesta son momentos completos: actualizan ambos controles y llaman
una vez a `requestDirections()`. Los horarios rápidos se reconstruyen desde la respuesta
del día aplicado; valores `rise` o `set` nulos no generan botones ni horas ficticias.

El flujo es navegador → proxy PHP → `GET /v1/astronomy/directions`. El proxy acepta sólo
GET, valida fecha 1900–2050, hora, coordenadas y zona IANA, y sanea el contrato antes de
devolver JSON. Nunca expone la URL interna.

`AstronomyMap.destinationPoint()` calcula geodésicamente el extremo visual desde el
observador y un azimut medido desde el norte en sentido horario. La longitud de las
líneas es representativa y no expresa distancia. La altura se informa por separado.
Eventos `rise`/`set` nulos y posiciones bajo el horizonte no generan capa.

Los accesos de horarios se construyen exclusivamente con el último JSON aplicado de
direcciones y nunca generan solicitudes. Al editar otra fecha quedan ocultos.
`astronomy-featured-dates.php` compone dos rangos de `/v1/astronomy/events` porque cada
consulta admite como máximo 366 días: seis meses recientes y doce meses futuros. Filtra
`moon_phase` a `new_moon`, `first_quarter`, `full_moon` y `last_quarter`, normaliza la
fecha local y permite caché privada durante una hora. `includes/featured-dates.php`
comparte la obtención entre el proxy JSON y el render inicial. El HTML conserva un
`<select>` completo; JavaScript lo mejora con popover de escritorio o bottom sheet
móvil. Seleccionar o expandir opciones sólo edita la interfaz y no consulta la API.

## Portada y perfiles de altura

PHP renderiza primero los horarios, textos y un espacio de altura reservada. Después `assets/js/home-sky.js` realiza dos solicitudes independientes y asíncronas a `altitude-profile.php`, una con `target=sun` y otra con `target=moon`. El fallo de una no impide renderizar la otra ni elimina los textos originales.

El proxy del mismo origen admite únicamente `GET`, valida objetivo, fecha, coordenadas y zona IANA, obtiene el intervalo efectivo de configuración y llama server-side a `/v1/astronomy/altitude-profile`. Cualquier otro método responde HTTP 405, `Allow: GET` y un error JSON genérico. Nunca devuelve la URL base interna. Exige exactamente tres roles y puntos válidos entre −90° y 90°. Tanto respuestas correctas como errores usan `Cache-Control: no-store`; los errores de entrada o infraestructura usan HTTP 400 o 503.

El SVG se crea en el navegador con segmentos lineales:

- Sol: solsticio de invierno, fecha solicitada (**Hoy**) y solsticio de verano;
- Luna: día anterior (**Ayer**), fecha solicitada (**Hoy**) y día siguiente (**Mañana**);
- línea completa, incluidas alturas negativas;
- línea horizontal de 0°;
- relleno sólo para la serie principal por encima de 0°;
- interpolación lineal del cruce con el horizonte y polígonos independientes si existen varios tramos positivos;
- leyenda compacta sin texto sobre la frecuencia de muestras;
- `<title>`, `<desc>`, `role="img"` y marcador accesible.

La tarjeta lunar conserva imagen y texto en dos columnas superiores y coloca el gráfico en una fila inferior a todo el ancho útil. La imagen mantiene protagonismo y el gráfico no usa desbordes, desplazamientos ni márgenes negativos.

El marcador interpola linealmente entre las dos muestras vecinas. Con reloj real se actualiza cada minuto sin volver a solicitar series. Con `debug_now` usa el instante simulado, queda fijo y no inicia temporizador.

El nombre de la fase actual no usa la clasificación amplia de `daily`. El helper
`astronomyMoonPhaseLabelForLocalDate()` consume eventos exactos: reserva los cuatro
nombres principales para su día civil local y usa los cuatro nombres intermedios el
resto de los días. Inicio y “El cielo hoy” comparten esa decisión.

## Próxima salida lunar

La portada obtiene la salida del día desde `/v1/astronomy/daily`. Si la Luna está bajo el horizonte y esa salida ya ocurrió o falta, consulta condicionalmente el día siguiente por el mismo endpoint. `homeFutureMoonrise()` descarta cualquier salida que no sea futura respecto de `/v1/moon/instant`, respetando ubicación, zona horaria y reloj simulado.

`homeMoonriseNoticePresentation()` calcula una sola vez los minutos restantes, redondeados hacia arriba:

- sobre la ventana configurada: “No está sobre el horizonte.”;
- 60 minutos hasta la ventana máxima: “La Luna saldrá a las HH:MM.”;
- 15–59: “La Luna saldrá en X minutos.” y nivel `soon`;
- 5–14: “La Luna está por salir.” y nivel `imminent`;
- menos de 5: “Preparate: la Luna está por salir.” y nivel `now`.

Los tres niveles destacados se presentan con una banda sobria. El último usa un pulso lento de borde; `prefers-reduced-motion` lo desactiva. La Luna visible conserva la presentación anterior.

## Presentación de eventos y Superluna

### Contexto satelital de portada

La opción `home.satellite_transits.enabled`, administrada en Configuración del
sitio → Portada, habilita este cálculo. Cuando está activa, la portada ejecuta
`SatelliteTransitService` una sola vez mediante
`includes/home-satellite-context.php`, después de resolver la ubicación activa,
el reloj —incluida la simulación local— y la ventana de oscuridad civil devuelta
por `tonight`. La consulta combina ISS y Tiangong con objetivos Luna y Sol para
las 48 horas siguientes. Desactivarla corta antes de construir el proveedor o
resolver TLE y deja el contexto en estado `disabled`.

El resultado completo queda en `$homePageContext['satellite']`. El mismo
contexto publica vistas derivadas en `sections.tonight.satellite_events` y
`sections.upcoming.satellite_events`: la primera contiene sólo eventos lunares
dentro de la noche local; la segunda contiene todos los eventos solares y los
lunares restantes, sin repetir los ya asignados a la noche. Una falla de red o
ausencia de TLE válido deja el bloque satelital como `unavailable`, registra el
error y no interrumpe los demás cálculos de Inicio.

Las dos tarjetas muestran únicamente `transit` y `very_close`, con hora local y
duración cuando existe. Los solares conservan la advertencia de filtro
certificado. Si la lista filtrada queda vacía no se genera contenedor alguno.
El diagnóstico administrativo al pie muestra `Satélites`, estado y tiempo
total; en ejecuciones correctas añade resolución TLE y cálculo astronómico.

### Búsqueda progresiva de “Lo próximo”

`includes/home-upcoming-events.php` centraliza el límite de seis elementos, los tipos
y los tramos `[0,7]`, `[7,7]` y `[14,16]`. Cada respuesta se combina con las
anteriores, se filtra con las mismas reglas editoriales, se deduplica y se ordena
cronológicamente antes de decidir si hace falta el tramo siguiente. La clave usa
`id`, `event_id` o `uid` cuando existe; en su defecto usa `type + datetime`.

El diagnóstico conserva una entrada separada por consulta:
`home v2 upcoming 1-7`, `8-14` y `15-30`. Sólo aparecen los tramos realmente
ejecutados, lo que permite observar el corte temprano y sumar sus tiempos.

`includes/event-presentation.php` es compartido por portada y `eventos.php`. Devuelve `title`, `summary`, `show_time`, `time_label`, `technical_details`, `explanation`, `public_details`, `contact_points` y `alert` para:

- fases y cuartos lunares;
- perigeo y apogeo;
- conjunciones y visibilidad simultánea;
- oportunidades unificadas de Luna fina y luz cenicienta;
- oportunidades `full_moon_observation`;
- libraciones destacadas (`libration`);
- eclipses (`eclipse`) lunares y solares;
- clasificación editorial de Superluna.

Sólo `moon_phase/full_moon` puede presentarse como **Superluna**. Si `details.apparent_size_percent` es numérico y alcanza el umbral, cambia el título y usa el resumen “La Luna llena se verá más grande de lo habitual.” No crea otro evento ni elimina sus datos: distancia, iluminación y porcentaje real permanecen en los detalles técnicos.

La portada y `eventos.php` solicitan `full_moon_observation` con
`max_difference_minutes=70`. Ambos usan `astronomyFullMoonObservationMoment()` y la
misma presentación para título, diferencia, salida/puesta lunar, explicación y
nubosidad en la hora relevante. `eventos.php` también ofrece filtros para
`moon_phase`, `apsis`, `conjunction`, `earthshine`, `libration` y `eclipse`.

Los eventos `earthshine` también tienen una única construcción compartida.
La API entrega los dos amaneceres anteriores y los dos atardeceres posteriores
a cada Luna nueva; si una oportunidad además cumple la regla observacional
histórica, `details.earthshine_visible=true`. La presentación combina ambos
conceptos en una sola tarjeta con iluminación, separación Sol-Luna, diferencia
y horarios de salida/puesta, intervalo útil y nubosidad. El día civil de Luna
nueva se descarta si la iluminación real es menor que `0,4 %`; el filtro usa el
valor recibido antes de formatearlo.

Para libraciones, la presentación pública muestra título amigable por subtipo, hora local, borde favorecido en texto, amplitud aproximada con un decimal y fase/iluminación como dato secundario opcional. No se exponen en la interfaz pública `schema_version`, `generation`, `publication`, kernels, frame ni metadatos internos de persistencia o cálculo.

Para eclipses, la presentación pública prioriza la visibilidad local (`not_visible`, `visible_partial`, `visible_total`, etc.) y muestra contactos horarios legibles por código. En eclipses solares, agrega magnitud, oscurecimiento y geometría local del máximo cuando existe. Si `near_central_path_boundary=true`, informa explícitamente que pequeñas variaciones de ubicación pueden cambiar duración y tipo central observado.

## Exportación a calendario

`includes/calendar-event.php` centraliza la elección del intervalo, las URLs de
proveedores y la serialización RFC 5545. Inicio, Eventos lunares y Eclipses muestran
una única acción **Agendar evento** sólo si existen inicio y fin confiables. El menú
abre los formularios de confirmación de Google Calendar u Outlook, o descarga el
`.ics` mediante `calendar-event.php` para Apple Calendar y otros clientes.

`assets/js/calendar-scheduler.js` controla el menú compartido: sólo mantiene uno
abierto, cierra al tocar fuera o al presionar Escape y devuelve el foco al botón.
En móvil se presenta como un panel inferior ancho para evitar desbordes.

Los instantes exactos de fases, conjunciones, ápsides y libraciones reciben una
duración editorial de 15 minutos. Las oportunidades de Luna fina o luz cenicienta
usan su intervalo útil completo. `full_moon_observation` comienza en el horario
lunar relevante y dura 30 minutos. Los eclipses usan, en orden, inicio/final visible
local, primer/último contacto disponible o, si sólo existe el máximo, una duración
de 60 minutos desde ese instante.

Para maximizar compatibilidad, `DTSTART` y `DTEND` se exportan en UTC. La zona activa
se conserva en `X-WR-TIMEZONE` y en `DESCRIPTION`; de este modo el calendario del
dispositivo convierte correctamente el evento, incluso al cruzar medianoche. No se
generan eventos de día completo.

## Navegación móvil por gestos

PHP emite sólo sección actual y URLs anterior/siguiente ya resueltas con `astronomyInternalUrl()`. En los extremos falta una URL y el recorrido no es circular. El script no se carga ni se emiten esos atributos si la función está deshabilitada o la sección tiene `swipe_enabled=false`.

`assets/js/mobile-swipe-navigation.js` actúa hasta 767 px y usa Touch Events como fuente principal. Los Pointer Events quedan como respaldo y diagnóstico; un flujo Touch activo impide procesar dos veces el mismo gesto. Todos los listeners de movimiento son pasivos y no llaman `preventDefault()`. CSS aplica `touch-action: pan-y pinch-zoom` al área navegable para reservar el gesto horizontal y conservar desplazamiento vertical y zoom.

Umbrales: 80 px horizontales, máximo 700 ms y relación horizontal/vertical de 1,5. Izquierda avanza y derecha retrocede. Se excluye un inicio sobre o dentro de `a`, `button`, `input`, `select`, `textarea`, `[contenteditable]`, `iframe` o `[data-swipe-navigation-ignore]`. Los contenedores completos de ambos mapas Leaflet llevan esta última marca, incluidos sus descendientes, controles y marcador. SVG y gráficos no interactivos permiten iniciar el gesto. Un contenedor con overflow horizontal sólo gana cuando realmente puede desplazarse en la dirección detectada.

El selector habilita `tapHold` de Leaflet con tolerancia de 10 px. `AstronomyMap.bindLongPress()` escucha únicamente el `contextmenu` simulado y validado por ese handler; no registra `pointerdown`, `pointermove` ni captura punteros sobre el contenedor. Leaflet cancela ante movimiento, segundo dedo, `touchend` o `touchcancel`. Al confirmar entrega el punto al mismo callback que usa `dragend`; el guardado y la geocodificación no están duplicados.

El aviso inicial es no bloqueante y usa la clave `aquellas-lunas-mobile-swipe-hint-seen-v1` en `localStorage`. `prefers-reduced-motion` elimina la transición previa a navegar.

El panel fijo requiere autorización de `canUseSiteDebugTools()` y `MOBILE_SWIPE_NAVIGATION_DEBUG_ENABLED=true`. Informa coordenadas y deltas efectivos, fuente, cantidad de movimientos, `touch-action`, exclusión, resultado y destino. No navega automáticamente: conserva `lastAcceptedDestination` y el botón **Ir al destino detectado** abre exactamente esa URL. El panel y sus controles usan `data-swipe-navigation-ignore`. Sin esa bandera el swipe navega normalmente.

Las regresiones de cancelación, continuidad Touch y persistencia del botón están en `tests/mobile-swipe-navigation.test.html` y `.js`; `tests/` se excluye del despliegue.

## Analytics, SEO, favicon y assets

`includes/analytics.php` contiene el ID fijo `G-GFZJ3D3MF3` y `renderAnalyticsTracking()` carga `gtag.js` una vez por petición. Se invoca en Inicio, Esta noche, Sol y Luna, Planificador, Eventos, Eclipses, Ubicación, Galería y Acerca. No existe variable de entorno, consentimiento propio ni supresión automática para localhost.

La verificación consiste en revisar el HTML o Network/Tag Assistant y confirmar `googletagmanager.com/gtag/js?id=G-GFZJ3D3MF3`. La web no guarda identificadores de Analytics en cookies propias ni registra payloads en PHP, pero Google puede aplicar su propia política y almacenamiento. Las páginas dinámicas usan `no-store`; esto no modifica la caché ni privacidad del script externo.

`assets/js/install-prompt.js` usa el helper seguro
`window.aquellasLunasTrackAnalyticsEvent()` definido por ese mismo bloque de
Analytics. Registra `pwa_install_open`, `pwa_install_prompt`,
`pwa_install_accepted`, `pwa_install_dismissed`, `pwa_ios_instructions`,
`pwa_favorite_help`, `pwa_promo_closed`, `pwa_embedded_browser_notice` y
`pwa_embedded_open_external`; la espera fallida de una intención explícita usa
`pwa_install_intent_unavailable`. Todos incluyen `source`, `platform`, `browser`,
`display_mode` y `action`. El clic sólo cuenta como intento; aceptación y
rechazo requieren `userChoice`, y `appinstalled` funciona como confirmación
alternativa sin duplicar una aceptación ya registrada.

`assets/js/content-trivia.js` reutiliza el mismo helper para medir exclusivamente
las trivias públicas de portada. `trivia_view` se emite una vez cuando la trivia
alcanza 25 % visible mediante `IntersectionObserver`; un `WeakSet` deduplica esa
vista por elemento durante la vida del documento. `trivia_answer` se emite sólo en
la primera elección porque `data-answered` bloquea respuestas posteriores;
`trivia_article_click` se emite al seguir el enlace sin prevenir la navegación.
Todos incluyen `trivia_codigo`; `articulo_slug` sólo cuando existe relación,
`correcta` y `opcion` sólo corresponden a la respuesta. La falta o bloqueo de GA4
no interrumpe la interacción.

El mismo script centraliza la detección conservadora de navegadores embebidos
de Instagram y Facebook mediante marcadores propios de sus User-Agent. En ese
contexto reemplaza el CTA de instalación por ayuda para abrir la URL actual en
un navegador normal. Android intenta Chrome mediante un `intent:` construido
sólo desde una URL HTTP(S) y conserva instrucciones visibles como fallback;
iOS no intenta abrir Safari automáticamente. El descarte de este aviso dura la
navegación actual mediante `sessionStorage`. La disponibilidad real de
`beforeinstallprompt` sigue gobernando la instalación en navegadores normales.
La tarjeta promocional de portada y la acción Instalar del menú comparten el
mismo handler y la misma decisión por plataforma. Ocultar la tarjeta durante
30 días no deshabilita la acción del menú; en modo instalado ambas invitaciones
quedan ocultas. Si la tarjeta no está visible, el menú muestra en la propia
navegación las instrucciones manuales necesarias para Safari/iOS.

El salto explícito desde Android agrega `install=1` con `URL.searchParams` sin
perder el path ni otros parámetros. Ya fuera del navegador embebido, ese valor
sólo activa una espera coordinada con `beforeinstallprompt`: nunca dispara el
prompt automáticamente. Cuando el evento está disponible presenta un `dialog`
con botón habilitado; si no llega a tiempo informa la indisponibilidad y deja
seguir navegando. Cerrar, rechazar, instalar o detectar modo standalone limpia
el parámetro mediante `history.replaceState()` sin recargar. iOS queda fuera de
este mecanismo y conserva sus instrucciones específicas.

`seo.php` genera metadatos, canonical y JSON-LD. `favicon-links.php` publica SVG, ICO, PNG 32×32 y Apple Touch Icon. `versionedAssetUrl()` agrega `?v=<filemtime>` a CSS, JavaScript, favicon y miniaturas lunares cuando el archivo existe; si no puede leerlo conserva la ruta original.

## Caché, errores y recuperación

Las fronteras comunes `astronomyDataResolve()`, `astronomyEvents()`, `homeSatelliteContext()` y los endpoints de series del Explorador registran opcionalmente una sola traza por operación completa. `includes/astronomy-trace.php` nunca instrumenta muestras internas y una falla de persistencia sólo se envía a `error_log`. `request_id` es único por operación y queda indexado para que un futuro reporte pueda referenciarlo; `session_trace_id` agrupa anónimamente las operaciones de una pestaña o sesión de navegador.

Las vistas dinámicas que llaman `sendDynamicNoCacheHeaders()` —Inicio, Esta noche, Sol y Luna, Planificador, Eventos, Eclipses, Ubicación y Galería— deshabilitan la caché. Los proxies JSON también usan `no-store`. Los assets estáticos conservan caché normal y cambian de URL al cambiar su `filemtime`.

La portada activa la protección visual reutilizable de
`includes/page-freshness.php` y `assets/js/page-freshness.js`. El instante de carga
se toma del contexto temporal común `window.siteTimeContext`: usa su instante fijo
cuando el reloj está simulado y `Date.now()` sólo en modo normal. Se conserva en el
nodo del aviso durante la vida de ese documento; si cambia el modo del reloj, la
base se reinicia para no comparar escalas distintas. Al cargar, en `visibilitychange` al volver a estado visible y en
`pageshow`, se compara el reloj actual del dispositivo con ese instante. Superadas
las tres horas definidas por `ASTRONOMY_PAGE_FRESHNESS_THRESHOLD_SECONDS`, aparece
un único banner persistente cuyo botón ejecuta una recarga normal. No hay timer,
consulta al servidor, actualización automática ni cambios de caché. El componente
es opt-in: otras páginas no lo cargan hasta que se evalúen individualmente.

El cliente reintenta una vez, después de 500 ms, errores de transporte y HTTP 502/503/504. Los errores se registran sin credenciales, cookies ni cuerpos completos. Las vistas mantienen navegación y muestran recuperación parcial; `page-recovery.js` limita recargas y sólo actúa si `data-api-state="error"`.
