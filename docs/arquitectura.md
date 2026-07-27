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
- `asset-url.php`: query `v=<filemtime>` para assets locales.
- `analytics.php`, `seo.php` y `favicon-links.php`: cabecera pública.
- `moon-images.php`: miniaturas lunares estáticas.

## Eclipses y mapas mundiales

`eclipses.php` valida el formulario, limita la búsqueda exclusiva de eclipses a diez años y consume server-side `/v1/astronomy/events?types=eclipse`. El listado no renderiza mapas. Cada evento contiene un `<template>` que `assets/js/eclipses.js` clona dentro de un `<dialog>`.

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

## Administración privada de fotos

`admin/login.php`, `admin/fotos.php` y `admin/logout.php` forman un área no enlazada desde el sitio público. `includes/store-admin-auth.php` centraliza cookie HttpOnly/SameSite=Lax, sesión, autenticación con `password_verify()`, regeneración del ID, CSRF y destrucción completa. Todas sus respuestas usan `no-store` y `X-Robots-Tag: noindex`.

`includes/store-admin-photos.php` consulta todas las fotos mediante la conexión PDO compartida y modifica con sentencias preparadas sólo disponibilidad, precio y los campos editoriales `titulo`, `descripcion` y `palabras_clave`. Estos últimos son opcionales, se recortan, validan a 255/5000/2000 caracteres y se guardan como texto o `NULL`. No interpreta HTML ni actualiza EXIF, `metadatos_json`, monedas o previews. La asignación múltiple de precios sigue siendo transaccional; el portal no muestra hashes, originales o nombres de archivo, no borra y no toca `pedido_fotos`.

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

`includes/event-presentation.php` es compartido por portada y `eventos.php`. Devuelve `title`, `summary`, `show_time`, `time_label`, `technical_details`, `explanation`, `public_details`, `contact_points` y `alert` para:

- fases y cuartos lunares;
- perigeo y apogeo;
- conjunciones y visibilidad simultánea;
- ventanas de luz cenicienta;
- oportunidades `full_moon_observation`;
- libraciones destacadas (`libration`);
- eclipses (`eclipse`) lunares y solares;
- clasificación editorial de Superluna.

Sólo `moon_phase/full_moon` puede presentarse como **Superluna**. Si `details.apparent_size_percent` es numérico y alcanza el umbral, cambia el título y usa el resumen “La Luna llena se verá más grande de lo habitual.” No crea otro evento ni elimina sus datos: distancia, iluminación y porcentaje real permanecen en los detalles técnicos.

La portada consulta 30 días con `types=moon_phase,apsis,conjunction,earthshine,full_moon_observation` y `max_difference_minutes=70`. `eventos.php` ofrece filtros para `moon_phase`, `apsis`, `conjunction`, `earthshine`, `libration` y `eclipse`; presenta sus datos técnicos mediante un popover accesible cuando corresponde.

Para libraciones, la presentación pública muestra título amigable por subtipo, hora local, borde favorecido en texto, amplitud aproximada con un decimal y fase/iluminación como dato secundario opcional. No se exponen en la interfaz pública `schema_version`, `generation`, `publication`, kernels, frame ni metadatos internos de persistencia o cálculo.

Para eclipses, la presentación pública prioriza la visibilidad local (`not_visible`, `visible_partial`, `visible_total`, etc.) y muestra contactos horarios legibles por código. En eclipses solares, agrega magnitud, oscurecimiento y geometría local del máximo cuando existe. Si `near_central_path_boundary=true`, informa explícitamente que pequeñas variaciones de ubicación pueden cambiar duración y tipo central observado.

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

`seo.php` genera metadatos, canonical y JSON-LD. `favicon-links.php` publica SVG, ICO, PNG 32×32 y Apple Touch Icon. `versionedAssetUrl()` agrega `?v=<filemtime>` a CSS, JavaScript, favicon y miniaturas lunares cuando el archivo existe; si no puede leerlo conserva la ruta original.

## Caché, errores y recuperación

Las vistas dinámicas que llaman `sendDynamicNoCacheHeaders()` —Inicio, Esta noche, Sol y Luna, Planificador, Eventos, Eclipses, Ubicación y Galería— deshabilitan la caché. Los proxies JSON también usan `no-store`. Los assets estáticos conservan caché normal y cambian de URL al cambiar su `filemtime`.

El cliente reintenta una vez, después de 500 ms, errores de transporte y HTTP 502/503/504. Los errores se registran sin credenciales, cookies ni cuerpos completos. Las vistas mantienen navegación y muestran recuperación parcial; `page-recovery.js` limita recargas y sólo actúa si `data-api-state="error"`.
