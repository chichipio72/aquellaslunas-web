# Arquitectura de la web

## Alcance

Este repositorio contiene la capa web pública de Aquellas Lunas. Usa PHP 8.5, HTML, CSS y JavaScript sin frameworks. Los cálculos astronómicos pertenecen a una API separada; PHP valida entradas, consume esa API desde el servidor y evita publicar su URL interna en el navegador.

Las páginas públicas son:

- `index.php`: portada;
- `sol-y-luna.php`: rango diario de Sol y Luna;
- `eventos.php`: efemérides filtrables;
- `eclipses.php`: búsqueda y detalle de eclipses;
- `planificador.php`: planificación cartográfica del Sol y la Luna;
- `ubicacion.php`: configuración de la ubicación global;
- `galeria.php`: fotografías disponibles de la tienda;
- `acerca-del-sitio.php`: contenido institucional;
- `acerca.php`: redirección 301 compatible hacia la página institucional actual.

## Ubicación, encabezado y registro de secciones

`includes/location-context.php` valida y entrega la única ubicación activa. Las páginas
locales no leen cookies ni procesan geolocalización por separado. La estructura incluye
nombre, latitud, longitud, zona IANA y origen. `includes/site-header.php` muestra la
localidad enlazada a `ubicacion.php`; `site-footer.php` centraliza la atribución.

`ubicacion.php` es el único punto de escritura. PHP valida antes de emitir cookies.
`assets/js/location.js` pide permisos y maneja errores; `assets/js/astro-map.js` crea el
mapa Leaflet, busca con Nominatim y mantiene el marcador del observador. Las futuras
capas o líneas de azimut deben permanecer separadas de ese marcador.

`includes/site-sections.php` es la fuente única para el orden, etiqueta, URL, presencia en menú y presencia en swipe de cada sección. `renderAstronomySiteNavigation()` genera el menú y aplica `class="is-active"` y `aria-current="page"` a la página actual. `astronomyMobileSwipeContext()` filtra el mismo registro por `swipe_enabled`.

El recorrido táctil actual es Inicio → Esta noche → Sol y Luna → Planificador → Eventos. Eclipses, Ubicación y Acerca tienen `swipe_enabled=false`. Galería también queda fuera y tiene `menu_enabled=false` mientras continúa en pruebas, aunque conserva acceso directo.

Las vistas públicas invocan el encabezado común con la marca **Aquellas Lunas** y el subtítulo **Una Luna diferente cada noche**. El menú está centralizado en el registro de secciones.

## Includes compartidos

- `api-config.php`: carga y valida configuración.
- `api-client.php`: cURL, reintento controlado, métricas y cabeceras dinámicas sin caché.
- `current-datetime.php`: reloj editorial real/simulado. En el entorno local habilitado persiste una fecha y hora de pared en sesión; producción usa siempre el reloj real.
- `astronomy-icon.php`: mapeo visual compartido para fases y eventos secundarios. Traduce `moon_phase/new_moon|first_quarter|full_moon|last_quarter`, `earthshine`, `conjunction`, `libration_*`, `apsis/perigee|apogee` y cualquier `eclipse` a clases `astro-icon--*`; los tipos desconocidos usan `astro-icon--generic`. Recibe la latitud para orientar los cuartos y la luz cenicienta según hemisferio.
- `site-sections.php`: registro, menú y contexto de swipe resuelto por PHP.
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
`[[imagen]]`, `[[esquema]]` y `[[trivia]]`. El renderizador Markdown propio implementa
un subconjunto deliberadamente pequeño y escapa primero todo texto. Los componentes
futuros se emiten como marcadores visibles, sin ejecutar contenido arbitrario.

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
portada pide al mismo catálogo una trivia y una entrada “Sabías que…” aleatorias; las
opciones de trivia se mezclan sobre una copia. En modo usuario sólo participan
entidades válidas y visibles. En debug local, los errores se sustituyen por bloques
de diagnóstico.

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
estrecha (aprox. 800–850 px). Es la única excepción a la regla general de márgenes
uniformes del sitio. Dentro de ese ancho, la imagen principal se muestra centrada en
un contenedor ajustado al tamaño real de la fotografía, con un margen interno breve.
Como excepción editorial, cuando sobra espacio horizontal la imagen principal puede
ampliarse de forma proporcional hasta un 30 % sobre su tamaño natural, sin recorte
y sin deformación. El editor
conserva el original como referencia y muestra aparte una preview 16:9 fiel al
resultado público; habilita el selector de foco cuando hay recorte. Una relación fuera
del rango tolerante 1.70–1.85 produce una advertencia no bloqueante.

Ninguna presentación amplía un archivo por encima de sus dimensiones naturales.
El resolver expone ancho y alto reales como límites CSS; hero, índice, componentes
internos y previews pueden reducir la fotografía con `contain`, pero nunca aplican
zoom. Si el marco disponible es mayor, la imagen queda centrada y el espacio
restante permanece libre.

La única excepción es la imagen principal de la página editorial individual: allí puede
escalar hasta 1.3x de su ancho natural cuando el ancho disponible lo permita.

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

## Eclipses y mapas mundiales

`eclipses.php` valida el formulario, limita la búsqueda exclusiva de eclipses a cinco
años y consume server-side `/v1/astronomy/events?types=eclipse`. El límite se refleja
en los controles del navegador y se vuelve a comprobar antes de cualquier consulta a
la API; un exceso muestra “El intervalo máximo de consulta es de 5 años.” El listado
no renderiza mapas. Cada evento contiene un `<template>` que `assets/js/eclipses.js`
clona dentro de un `<dialog>`.

El detalle se implementa en `includes/eclipse-detail-component.php`. Ese componente
construye el modelo, el identificador estable, el botón disparador, la plantilla y el
único `<dialog>`. Lo consumen tanto `eclipses.php` como las tarjetas `type=eclipse`
de `eventos.php`; estas últimas usan el evento ya recibido por la consulta general y
no realizan otra petición. `assets/js/eclipses.js` resuelve la plantilla por el
identificador del eclipse. Mapas no disponibles producen `null` y no renderizan
sección ni espacio residual. La agenda se construye con el helper común de calendario.

## Visibilidad de esta noche

Inicio consume `GET /v1/astronomy/tonight` con `detail=summary`; la página
`cielo-de-esta-noche.php` usa `detail=full`. En ambos casos PHP envía la fecha local
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

Los GIF se generan desde la operación de la API pero FastAPI no los sirve. Se depositan en `assets/images/eclipses/`, se ignoran en Git salvo `.gitkeep` y se transfieren mediante el mirror FTPS. Apache debe poder leerlos (`0644` en local).

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

`admin/` y `admin/index.php` son el punto de entrada del panel privado, no enlazado desde el sitio público. El panel ofrece acceso a la galería/tienda, al Laboratorio Astronómico y al editor de Contenidos (`/admin/contenidos/`). `includes/store-admin-navigation.php` comparte entre módulos la navegación Inicio, Contenidos, Galería, Laboratorio y el formulario de cierre de sesión.

`includes/store-admin-auth.php` conserva la autenticación histórica: cookie de sesión `aquellas_lunas_admin`, estado `store_admin_authenticated`, cookie HttpOnly/SameSite=Lax, validación mediante `password_verify()`, regeneración del ID, CSRF y destrucción completa. `admin/login.php` dirige al panel general después de autenticar y `admin/logout.php` mantiene el cierre por POST. Todas las respuestas administrativas usan `no-store` y `X-Robots-Tag: noindex`.

La ruta canónica de contenidos es `/admin/contenidos/`. La navegación calcula rutas válidas según la ubicación del script actual en `/admin` para evitar prefijos relativos frágiles.

`includes/store-admin-photos.php` consulta todas las fotos mediante la conexión PDO compartida y modifica con sentencias preparadas sólo disponibilidad, precio y los campos editoriales `titulo`, `descripcion` y `palabras_clave`. Estos últimos son opcionales, se recortan, validan a 255/5000/2000 caracteres y se guardan como texto o `NULL`. No interpreta HTML ni actualiza EXIF, `metadatos_json`, monedas o previews. La asignación múltiple de precios sigue siendo transaccional; el portal no muestra hashes, originales o nombres de archivo, no borra y no toca `pedido_fotos`.

El editor de Contenidos en `/admin/contenidos/` persiste en MySQL con transacciones únicas sobre `contenido_articulos`, `contenido_articulos_palabras_clave`, `contenido_articulos_relaciones`, `contenido_trivias`, `contenido_trivia_opciones` y `contenido_sabias_que`. El importador histórico `scripts/migrations/import-content-to-web-db.php` se conserva sólo como referencia de migración inicial y no forma parte del flujo operativo actual.

### Modo Administrador

La sesión administrativa habilitará progresivamente capacidades adicionales sobre el sitio público sólo para usuarios autenticados en admin (por ejemplo enlaces de edición, accesos rápidos, depuración y mantenimiento), sin depender de `APP_ENV=local`.

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

El panel fijo requiere `ASTRONOMY_SHOW_TIMINGS=true` y `MOBILE_SWIPE_NAVIGATION_DEBUG_ENABLED=true`. Informa coordenadas y deltas efectivos, fuente, cantidad de movimientos, `touch-action`, exclusión, resultado y destino. No navega automáticamente: conserva `lastAcceptedDestination` y el botón **Ir al destino detectado** abre exactamente esa URL. El panel y sus controles usan `data-swipe-navigation-ignore`. Timings sin esa bandera conserva reloj y métricas generales, pero el swipe navega normalmente.

Las regresiones de cancelación, continuidad Touch y persistencia del botón están en `tests/mobile-swipe-navigation.test.html` y `.js`; `tests/` se excluye del despliegue.

## Analytics, SEO, favicon y assets

`includes/analytics.php` contiene el ID fijo `G-GFZJ3D3MF3` y `renderAnalyticsTracking()` carga `gtag.js` una vez por petición. Se invoca en Inicio, Esta noche, Sol y Luna, Planificador, Eventos, Eclipses, Ubicación, Galería y Acerca. No existe variable de entorno, consentimiento propio ni supresión automática para localhost.

La verificación consiste en revisar el HTML o Network/Tag Assistant y confirmar `googletagmanager.com/gtag/js?id=G-GFZJ3D3MF3`. La web no guarda identificadores de Analytics en cookies propias ni registra payloads en PHP, pero Google puede aplicar su propia política y almacenamiento. Las páginas dinámicas usan `no-store`; esto no modifica la caché ni privacidad del script externo.

`assets/js/install-prompt.js` usa el helper seguro
`window.aquellasLunasTrackAnalyticsEvent()` definido por ese mismo bloque de
Analytics. Registra `pwa_install_open`, `pwa_install_prompt`,
`pwa_install_accepted`, `pwa_install_dismissed`, `pwa_ios_instructions`,
`pwa_favorite_help` y `pwa_promo_closed`, con `source`, `platform`, `browser`,
`display_mode` y `action`. El clic sólo cuenta como intento; aceptación y
rechazo requieren `userChoice`, y `appinstalled` funciona como confirmación
alternativa sin duplicar una aceptación ya registrada.

`seo.php` genera metadatos, canonical y JSON-LD. `favicon-links.php` publica SVG, ICO, PNG 32×32 y Apple Touch Icon. `versionedAssetUrl()` agrega `?v=<filemtime>` a CSS, JavaScript, favicon y miniaturas lunares cuando el archivo existe; si no puede leerlo conserva la ruta original.

## Caché, errores y recuperación

Las vistas dinámicas que llaman `sendDynamicNoCacheHeaders()` —Inicio, Esta noche, Sol y Luna, Planificador, Eventos, Eclipses, Ubicación y Galería— deshabilitan la caché. Los proxies JSON también usan `no-store`. Los assets estáticos conservan caché normal y cambian de URL al cambiar su `filemtime`.

El cliente reintenta una vez, después de 500 ms, errores de transporte y HTTP 502/503/504. Los errores se registran sin credenciales, cookies ni cuerpos completos. Las vistas mantienen navegación y muestran recuperación parcial; `page-recovery.js` limita recargas y sólo actúa si `data-api-state="error"`.
