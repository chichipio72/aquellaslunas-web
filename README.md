# Aquellas Lunas — web de astronomía

## Estado y alcance

Este repositorio contiene la web pública PHP de Aquellas Lunas y su área administrativa. Los cálculos astronómicos y eventos provienen de una API FastAPI/PostgreSQL separada; PHP valida las solicitudes y presenta sus respuestas. MySQL (`WEB_DB`) conserva contenido público y configuración administrativa, pero no duplica los cálculos ni los eventos de la API.

La web usa PHP, HTML, CSS y JavaScript sin frameworks. La API, su infraestructura y sus cálculos no forman parte de este repositorio.

Documentación complementaria:

- [arquitectura y comportamiento funcional](docs/arquitectura.md);
- [administración y Laboratorio Astronómico](docs/administracion-y-laboratorio.md);
- [reglas editoriales de presentación astronómica](docs/reglas-editoriales-astronomia.md);
- [configuración completa](docs/configuracion.md);
- [entorno local y validación](docs/entorno-local.md);
- [despliegue operativo](docs/despliegue.md);
- [estado verificado, despliegue y pendientes](docs/estado-actual.md).

## Arquitectura por entorno

### Desarrollo local

- Ruta del repositorio: `/srv/proyectos/astronomia/web`.
- Apache y PHP 8.5 en Docker.
- Servicio Compose: `web`.
- Contenedor: `web-astro`.
- Puerto: `18080:80`.
- URL: `http://localhost:18080/`.
- Reinicio: `unless-stopped`.
- Código montado en `/var/www/html`.
- Flujo de trabajo habitual: VS Code conectado a la mini PC mediante Remote SSH.
- Configuración Compose local en `.env`; `APP_ENV=local` identifica la mini PC y autoriza las herramientas técnicas.

### Producción

- Hosting cPanel con PHP 8.5 mediante Alt-PHP / CGI-FastCGI.
- Ruta física: `/home8/aquellaslunascom/public_html/astro`.
- URL: `https://aquellaslunas.com.ar/astro/`.
- Producción no usa Docker.

## Responsabilidades

### API

- cálculos astronómicos;
- endpoints diarios y de rango;
- generación dinámica de imágenes lunares;
- cálculo de orientación aparente;
- pregeneración de assets lunares para tablas.

### Web

- selección, validación y persistencia de una única ubicación global;
- consumo server-side de la API;
- presentación de datos y errores amigables;
- selección de miniaturas estáticas;
- proxy seguro de la imagen lunar aparente;
- comportamiento accesible y responsive.

## SEO y Analytics

- Analytics se configura en [includes/analytics.php](includes/analytics.php) y usa el ID fijo `G-GFZJ3D3MF3`. Se invoca en Inicio, Esta noche, Sol y Luna, Planificador, Eventos, Eclipses, Ubicación, Galería y Acerca del sitio.
- No existe actualmente una variable o clave externa para desactivar Analytics en localhost: la página local también carga `gtag.js` si tiene acceso a Internet. Para evitar tráfico durante una prueba hay que bloquear `googletagmanager.com` en el navegador o en la red; documentar una bandera no reflejaría el código actual.
- Se verifica en el HTML o en Network/Tag Assistant buscando `gtag/js?id=G-GFZJ3D3MF3`. PHP no almacena payloads de Analytics ni crea cookies propias para esa integración; el script externo queda sujeto a las políticas de Google.
- Los metadatos, canonical y JSON-LD se resuelven desde [includes/seo.php](includes/seo.php). Cada página define su propio título, descripción y ruta antes de cargar el encabezado común.
- Para agregar una página nueva, define un arreglo SEO con `aquellasLunasSeoPage(...)` y pasa ese arreglo a `renderSeoHead(...)` en el `<head>`.
- Cuando se agregue una nueva página pública, actualizar [sitemap.xml](sitemap.xml) con la URL absoluta correspondiente.

## Configuración de la API

`includes/api-config.php` resuelve y valida la URL base en este orden:

1. `ASTRONOMY_API_BASE_URL`, si contiene un valor.
2. `/home8/aquellaslunascom/config/astronomia.php`.

Solo acepta URLs HTTP o HTTPS válidas y elimina barras finales. En Docker Compose se usa:

```text
http://host.docker.internal:18000
```

Los perfiles de altura de la portada comparten `ALTITUDE_PROFILE_INTERVAL_MINUTES`. El valor predeterminado es 15 y se admiten enteros de 5 a 60. La variable de entorno tiene prioridad sobre `altitude_profile_interval_minutes` en el archivo externo de producción. El proxy PHP envía siempre el intervalo efectivo a la API y lo devuelve en su respuesta JSON.

La clasificación editorial de Superluna y la ventana del aviso de salida lunar se administran actualmente en `/admin/presentacion/reglas.php`. Sus defaults viven en `includes/editorial-configuration.php` y MySQL guarda únicamente overrides. Los cargadores históricos `SUPERMOON_MIN_APPARENT_SIZE_PERCENT` y `MOONRISE_NOTICE_MAX_MINUTES` permanecen por compatibilidad interna, pero no son la fuente operativa de esos dos criterios públicos.

La navegación táctil móvil se controla con `MOBILE_SWIPE_NAVIGATION_ENABLED`, su aviso inicial con `MOBILE_SWIPE_NAVIGATION_HINT_ENABLED` y el panel visual con `MOBILE_SWIPE_NAVIGATION_DEBUG_ENABLED`. El recorrido real es Inicio → Esta noche → Sol y Luna → Planificador → Eventos. Eclipses, Ubicación, Galería y Acerca quedan fuera; Galería además está temporalmente oculta del menú aunque conserva su URL directa.

La matriz completa, incluidos tipos y límites, está en [docs/configuracion.md](docs/configuracion.md).

En producción, el archivo externo está fuera de `public_html` y debe devolver:

```php
<?php

return [
    'astronomy_api_base_url' => 'https://api.aquellaslunas.com.ar',
    'altitude_profile_interval_minutes' => 15,
    'mobile_swipe_navigation_enabled' => true,
    'mobile_swipe_navigation_hint_enabled' => true,
    'mobile_swipe_navigation_debug_enabled' => false,
    'store_originals_path' => '/home8/aquellaslunascom/fotos_tienda/originales',
    'store_previews_path' => '/home8/aquellaslunascom/public_html/astro/assets/images/tienda/previews',
    'store_catalog_path' => '/home8/aquellaslunascom/fotos_tienda/catalogo',
    'store_admin_user' => 'usuario-administrativo',
    'store_admin_password_hash' => 'HASH_GENERADO_FUERA_DEL_REPOSITORIO',
    'web_db_host' => 'HOST_PRIVADO',
    'web_db_port' => 3306,
    'web_db_name' => 'BASE_WEB',
    'web_db_user' => 'USUARIO_WEB',
    'web_db_password' => 'VALOR_PRIVADO',
    'store_preview_tienda_max_size' => 800,
    'store_preview_tienda_jpeg_quality' => 72,
    'store_preview_contenido_max_size' => 400,
    'store_preview_contenido_jpeg_quality' => 72,
    'store_download_expiry_hours' => 72,
    'store_download_max_count' => 5,
    'mercado_pago_mode' => 'production',
    'mercado_pago_access_token' => 'VALOR_PRIVADO',
    'mercado_pago_public_key' => 'VALOR_PRIVADO',
    'mercado_pago_webhook_secret' => 'VALOR_PRIVADO',
    'mercado_pago_success_url' => 'https://aquellaslunas.com.ar/astro/tienda/pago-exitoso.php',
    'mercado_pago_pending_url' => 'https://aquellaslunas.com.ar/astro/tienda/pago-pendiente.php',
    'mercado_pago_failure_url' => 'https://aquellaslunas.com.ar/astro/tienda/pago-fallido.php',
    'mercado_pago_notification_url' => 'https://aquellaslunas.com.ar/astro/webhooks/mercado-pago.php',
];
```

La ruta absoluta funciona aunque la aplicación esté instalada dentro de `/public_html/astro`. No se confía en `.htaccess SetEnv` como única solución porque, bajo Alt-PHP/FastCGI, esa variable no se propagó correctamente a PHP.

Los errores de configuración, cURL, HTTP y JSON se registran en `error_log`; el visitante recibe un mensaje genérico. No se registran contraseñas, cookies, cuerpos completos ni secretos.

## Diagnóstico opcional de tiempos

`includes/api-client.php` centraliza las llamadas HTTP y registra para cada una la etiqueta pública del endpoint, estado HTTP, tiempo total observado por PHP/cURL, `X-Response-Time-Ms` y `Server-Timing` cuando la API los entrega.

El tiempo de API es el valor interno informado mediante `X-Response-Time-Ms`. El tiempo total incluye además conexión, red y transferencia hasta la web. La petición independiente de `moon-image.php` queda medida en su propio registro; las páginas HTML muestran al final únicamente las llamadas realizadas durante su renderizado.

El diagnóstico visible está autorizado automáticamente en local y, en producción, sólo para una sesión admin válida. Docker Compose lee el archivo local `.env`; un ejemplo sin secretos es:

```dotenv
APP_ENV=local
LOCAL_TIME_SIMULATION_ENABLED=true
MOBILE_SWIPE_NAVIGATION_ENABLED=true
MOBILE_SWIPE_NAVIGATION_HINT_ENABLED=true
MOBILE_SWIPE_NAVIGATION_DEBUG_ENABLED=false
STORE_ORIGINALS_PATH=/srv/proyectos/astronomia/web/storage/tienda/originales
STORE_PREVIEWS_PATH=/srv/proyectos/astronomia/web/assets/images/tienda/previews
STORE_CATALOG_PATH=/srv/proyectos/astronomia/web/storage/tienda/catalogo
STORE_DB_HOST=HOST_MYSQL
STORE_DB_PORT=3306
STORE_DB_NAME=BASE_TIENDA
STORE_DB_USER=
STORE_DB_PASSWORD=
WEB_DB_HOST=HOST_MYSQL
WEB_DB_PORT=3306
WEB_DB_NAME=BASE_WEB
WEB_DB_USER=
WEB_DB_PASSWORD=
WEB_PUSH_VAPID_PUBLIC_KEY=
WEB_PUSH_VAPID_PRIVATE_KEY=
WEB_PUSH_VAPID_SUBJECT=mailto:tu-correo@example.com
STORE_ADMIN_USER=
STORE_ADMIN_PASSWORD_HASH=
STORE_INITIAL_PRICE=
STORE_PREVIEW_TIENDA_MAX_SIZE=800
STORE_PREVIEW_TIENDA_JPEG_QUALITY=72
STORE_PREVIEW_CONTENIDO_MAX_SIZE=400
STORE_PREVIEW_CONTENIDO_JPEG_QUALITY=72
STORE_DOWNLOAD_EXPIRY_HOURS=72
STORE_DOWNLOAD_MAX_COUNT=5
MERCADO_PAGO_MODE=test
MERCADO_PAGO_ACCESS_TOKEN=
MERCADO_PAGO_PUBLIC_KEY=
MERCADO_PAGO_WEBHOOK_SECRET=
MERCADO_PAGO_SUCCESS_URL=https://aquellaslunas.com.ar/astro/tienda/pago-exitoso.php
MERCADO_PAGO_PENDING_URL=https://aquellaslunas.com.ar/astro/tienda/pago-pendiente.php
MERCADO_PAGO_FAILURE_URL=https://aquellaslunas.com.ar/astro/tienda/pago-fallido.php
MERCADO_PAGO_NOTIFICATION_URL=https://aquellaslunas.com.ar/astro/webhooks/mercado-pago.php
```

El piloto Web Push se prueba únicamente desde `/admin/notificaciones-prueba.php`. El envío PHP usa las dependencias reproducibles de `composer.lock`; generar `vendor/` antes de desplegar con:

```bash
docker run --rm -u 1000:1000 -v /srv/proyectos/astronomia/web:/app -w /app composer:2 install --no-dev --optimize-autoloader
```

Una variable exportada en la sesión de shell tiene prioridad sobre el valor del archivo `.env`.

Las rutas de tienda se resuelven con la misma prioridad: variable de entorno y luego claves `store_originals_path`, `store_previews_path` y `store_catalog_path` del archivo externo de producción. Deben ser absolutas; las tres carpetas deben existir y ser legibles, y previews/catálogo además escribibles. `loadStoreConfig()` informa fallos sin revelar rutas internas. Los procesos CLI sincronizan metadatos y generan previews; la galería pública consume únicamente esas previews. La referencia completa está en [docs/configuracion.md](docs/configuracion.md).

MySQL usa `STORE_DB_HOST`, `STORE_DB_PORT`, `STORE_DB_NAME`, `STORE_DB_USER` y `STORE_DB_PASSWORD`, con claves externas `store_db_*` equivalentes. `loadStoreDatabaseConfig()` exige los cinco valores y valida el puerto. `getStoreDatabaseConnection()` crea una única conexión PDO lazy por request, con `utf8mb4`, excepciones, resultados asociativos y prepares nativos. Usuario, contraseña y DSN nunca se versionan ni se muestran en errores.

El precio inicial se configura mediante `STORE_INITIAL_PRICE` o `store_initial_price` y debe ser positivo. `scripts/sync-store-photos.php` sincroniza JPG/JPEG por SHA-256, admite `--dry-run`, no genera previews y nunca actualiza, elimina ni desactiva registros existentes.

Los derivados de tienda usan 800 px, calidad 72 y una marca semitransparente repetida con el ícono de Instagram y `aquellas_lunas`; los de contenido usan 400 px, calidad 72 y no llevan marca. `scripts/generate-store-previews.php` los guarda bajo `tienda/` y `contenido/`, valida el original por SHA-256, respeta orientación EXIF y admite `--dry-run` y `--force`. No hay endpoint público para ese proceso.

`galeria.php` presenta las fotos disponibles que ya tienen `archivo_preview_tienda`, ordenadas por creación descendente. La cuadrícula y el modal usan siempre esa versión comercial; no exponen la variante de contenido, originales ni rutas internas.

El portal privado vive bajo `admin/` y no aparece en la navegación pública. Su menú compartido contiene Inicio, Contenidos, Visibilidad de secciones, Visibilidad de eventos, Galería y Laboratorio; “Cerrar sesión” permanece separado. Autentica contra `STORE_ADMIN_USER` y `STORE_ADMIN_PASSWORD_HASH`, usa la sesión `aquellas_lunas_admin` y exige CSRF en todos los cambios. Contenidos, visibilidad y reglas escriben en `WEB_DB`; el laboratorio sólo consulta `datos_astronomicos`. La arquitectura administrativa completa se describe en [Administración y Laboratorio Astronómico](docs/administracion-y-laboratorio.md).

La galería permite listar, ocultar/publicar, administrar precios y editar título, descripción y palabras clave de una foto. Los campos editoriales opcionales se guardan como texto plano o `NULL`; no se modifican EXIF, JSON técnico, monedas, previews, archivos ni historial de pedidos.

Mercado Pago Checkout Pro se integra server-side sin SDK. La galería crea pedidos pendientes y preferencias sin confiar en importes del navegador. `webhooks/mercado-pago.php` acepta sólo POST, valida `x-signature`, consulta el pago real por API y confirma en una transacción monto, moneda y `external_reference`. Un pago aprobado actualiza `pagos`/`pedidos` y crea una única fila en `descargas`; todavía no existe descarga pública ni email. En modo `test`, un secreto vacío sólo permite mocks desde loopback; toda notificación externa se rechaza. Las páginas de retorno continúan siendo informativas y nunca aprueban pedidos.

El diagnóstico se muestra automáticamente en local. En producción se muestra sólo al navegador que conserva una sesión administrativa válida; no existe un flag adicional de timings.

Los nombres públicos usados actualmente son `moon instant`, `daily`, `home phases`, `home upcoming`, `range`, `events` y `moon image`. Mediciones locales observadas como referencia, no como garantía de rendimiento:

- `daily`: aproximadamente 57 ms;
- `home phases`: aproximadamente 90 ms;
- `home upcoming`: aproximadamente 288 ms.

En producción, una sesión anónima o incógnita no debe ver el diagnóstico; la autorización no se comparte con otros navegadores.

El panel del swipe requiere `canUseSiteDebugTools()` y `MOBILE_SWIPE_NAVIGATION_DEBUG_ENABLED=true`. Informa fuente Touch/Pointer, movimientos, coordenadas, deltas, `touch-action`, exclusiones, resultado y destino. Sin autorización el panel no se renderiza.

## Reloj simulado local

`LOCAL_TIME_SIMULATION_ENABLED=true` habilita el control en local. En producción se
habilita únicamente para una sesión admin válida. El helper `includes/current-datetime.php`
centraliza el instante mediante `get_current_datetime()` y persiste en sesión una
fecha y hora local. La portada, consultas astronómicas, filtros de eventos pasados,
fechas predeterminadas y marcadores usan ese valor; **Usar hora real** lo elimina.

El valor se interpreta nuevamente en la zona de la ubicación activa y se conserva en la sesión separada `aquellas_lunas_local`. Una cookie de fecha inventada por el cliente no se acepta.

## Laboratorio visual local

`pruebas-visuales.php` permite evaluar componentes experimentales dentro del diseño
real del sitio y sólo responde cuando `canUseSiteDebugTools()` es verdadero. La primera
prueba compara tres composiciones con publicaciones públicas insertadas mediante el
embed oficial de Instagram. Las URLs se editan en el arreglo `$instagramPosts`, cerca
del inicio de esa página; el renderer acepta la URL sencilla con la ruta de la cuenta
y la normaliza al permalink canónico requerido por el embed.

**Galería** depende de `menu.gallery.enabled`. **Pruebas visuales** aparece en local
o para un administrador autenticado y responde 404 para los demás.

La detección general está en `includes/api-config.php`: `appEnvironment()`,
`isLocalEnvironment()`, `isProductionEnvironment()` y `canUseSiteDebugTools()`.
`APP_ENV` distingue el entorno técnico; la publicación editorial depende de
`admin_configuracion_sitio`.

## Compatibilidad con PHP del hosting

Producción ejecuta PHP 8.5 y no garantiza la extensión mbstring. `capitalizeVisibleText()` usa mbstring cuando está disponible y un fallback UTF-8 seguro para caracteres españoles cuando no lo está. El proyecto debe seguir funcionando sin esa extensión.

No se usa `curl_close()`: está obsoleta en PHP 8.5 y no tiene efecto desde PHP 8.0. El cliente obtiene cuerpo, información, cabeceras y errores antes de dejar que PHP libere el recurso automáticamente.

## Convención de textos visibles

Los textos visibles comienzan con mayúscula inicial. `includes/presentation.php` aporta `capitalizeVisibleText()`, que modifica únicamente el primer carácter mediante funciones UTF-8 y conserva el resto de la frase. No se aplica a parámetros, identificadores, rutas, nombres de archivos ni URLs.

## Ubicación global

`includes/location-context.php` es la fuente única de verdad. Valida las cookies y
siempre devuelve `name`, `latitude`, `longitude`, `timezone` y `mode`; si no hay una
ubicación completa y válida usa Buenos Aires. Inicio, Esta noche, Sol y Luna, Planificador, Eventos y Eclipses consumen esa
estructura y no aceptan coordenadas propias.

La configuración pública vive en `ubicacion.php`. `assets/js/location.js` concentra
geolocalización y menú; `assets/js/astro-map.js` adapta Leaflet al marcador del
observador. Una futura página local debe usar `astronomyLocationContext()`.

Cookies propias (400 días, ruta `/`, `SameSite=Lax`): `astro_latitude`,
`astro_longitude`, `astro_timezone`, `astro_location_mode`, `astro_location_name`,
`astro_location_confirmed` y `astro_location_intro_seen`. La confirmación identifica
la fuente de verdad; la última sólo controla la ayuda de primera visita.
Los modos finales son `default`, `geolocation` y `manual`; el valor histórico `custom`
sigue siendo válido y se interpreta como `manual`.

Leaflet 1.9.4 se carga desde unpkg; mapa, búsqueda y geocodificación inversa usan
OpenStreetMap/Nominatim sin clave. El mapa representa sólo la ubicación del observador:
las futuras líneas de azimut deben agregarse como capas independientes.

## Secciones del sitio

### Planificador del Sol y la Luna

`planificador.php` dibuja sobre Leaflet los azimuts de salida, posición instantánea y
puesta del Sol y la Luna para la ubicación global. La consulta viaja únicamente a
`astronomy-directions.php`, que valida y consume server-side
`GET /v1/astronomy/directions`.

La página consulta una vez al cargar. La edición manual de fecha y hora permanece
separada del último mapa aplicado hasta usar **Mostrar direcciones** o **Actualizar
mapa**. En cambio, **Usar ahora** y los accesos a salida/puesta del Sol o la Luna
completan ambos controles y ejecutan inmediatamente el mismo flujo de consulta, una
sola vez. Los accesos se generan únicamente para eventos existentes en el día
actualmente aplicado y el elegido permanece destacado junto con un resumen del momento.
Cuando una edición manual difiere, el overlay informa qué fecha y hora siguen dibujadas,
bloquea temporalmente Leaflet y permite actualizar. Durante carga y error conserva las
últimas líneas válidas.
Las seis capas se pueden ocultar sin red. Las posiciones instantáneas bajo el horizonte
y eventos de salida/puesta nulos se explican sin dibujar línea. El azimut se mide desde
el norte en sentido horario; la altura se muestra como dato y la longitud de las líneas
es sólo visual.

### El cielo de esta noche

La portada consulta server-side una sola vez
`/v1/astronomy/tonight?detail=summary` con la fecha, ubicación y zona horaria activas.
La tarjeta enlazada responde cuántos planetas serán visibles y describe sólo
`visible_now`, `visible_later` y, mientras la noche está en curso, `visible_earlier`.
Nunca muestra `not_visible_tonight`, magnitud ni dificultad. Si no hay planetas conserva
la tarjeta con un estado vacío; si falla sólo degrada su contenido.

`cielo-de-esta-noche.php` hace una única consulta `detail=full` y separa Planetas,
Luna, Estrellas y Otros objetos. `includes/tonight.php` valida la estructura mínima y
centraliza horarios que cruzan medianoche, dirección actual, cercanía visual con la Luna
y ayudas de observación. No recalcula astronomía ni presenta `moon_proximity` como
conjunción. El catálogo de veinte estrellas, su orden y las constelaciones de estrellas,
cúmulos, planetas y Luna provienen exclusivamente de la API. La vista muestra
`constellation.name` como dato secundario, respeta el orden recibido y omite por completo
categorías vacías; no mantiene listas propias de estrellas u objetos. La página usa
no-cache, timeout/reintento del cliente común y recuperación mediante
`assets/js/page-recovery.js`.

Después del render astronómico, la misma página carga mediante
`assets/js/cloud-cover.js` un único pronóstico Open-Meteo con `cloud_cover`,
`cloud_cover_low`, `cloud_cover_mid` y `cloud_cover_high`. La respuesta se recorta a
`night.start`–`night.end` y se presenta como barras horarias; cada barra abre un popover
accesible con el total y únicamente las capas válidas. La caché compartida usa ubicación,
zona horaria y fecha nocturna, con vigencia de 30 minutos. Error, timeout o datos
inválidos eliminan toda la sección sin afectar la página.

Después de cada cálculo, cuatro accesos toman las horas de salida y puesta directamente
de esa misma respuesta; sólo cambian el campo Hora. Si se edita la fecha se ocultan hasta
aplicar el nuevo día. `astronomy-featured-dates.php` compone dos consultas server-side
al endpoint de eventos —seis meses anteriores y doce posteriores— y entrega únicamente
las cuatro fases principales, agrupadas en próximas y recientes. PHP renderiza un
`<select>` completo como fallback; JavaScript lo mejora con un panel que muestra ocho
opciones iniciales por grupo. Elegir o expandir opciones no consulta la API.

### Inicio

`index.php` presenta una portada visual organizada en:

- encabezado compacto con fecha, ubicación y acciones de geolocalización;
- Luna aparente como elemento principal, con fase, iluminación, salida, puesta y tamaño relativo;
- resumen solar con salida, puesta, duración del día y perfil de altura de 24 horas;
- perfiles asíncronos de altura del Sol y de la Luna, sin bloquear el HTML inicial ni reemplazar sus horarios y textos;
- “Próximas fases”, con una fecha para cada una de las cuatro fases principales;
- “Lo próximo”, con hasta cinco fases, ápsides, conjunciones, ventanas de luz cenicienta u oportunidades de observación de Luna llena;
- accesos directos a los próximos 30 días y a todas las efemérides.

La portada consulta `/v1/astronomy/daily` para el estado actual y separa las efemérides en dos consultas especializadas:

- `home phases`: desde 35 días antes de hoy y durante 80 días, exclusivamente con `types=moon_phase`. Conserva para la interfaz la primera ocurrencia futura de cada fase principal y aporta las Lunas nuevas anterior y siguiente para clasificar la situación actual.
- `home upcoming`: busca progresivamente los días 1–7, 8–14 y 15–30 con `types=moon_phase,apsis,conjunction,earthshine,full_moon_observation` y `max_difference_minutes=70`. Después de cada tramo descarta eventos pasados o no mostrables, deduplica, ordena y detiene las consultas al reunir los seis resultados que admite la portada. La presentación detallada y la consulta normal de `eventos.php` no cambian.

No existe una tercera consulta amplia con todos los tipos. Las mediciones aparecen separadas con esos mismos nombres en el diagnóstico opcional.

Las fases pueden aparecer en “Próximas fases” y también en la secuencia cronológica de “Lo próximo”, donde compiten por los primeros cinco lugares junto con los demás eventos. Las consultas `home phases` y `home upcoming` fallan independientemente: una sección puede seguir visible aunque la otra no esté disponible. Si falla la consulta diaria, los bloques futuros pueden seguir funcionando. La imagen aparente mantiene su fallback independiente en `moon-image.php`.

#### Perfiles diarios de Sol y Luna

Las tarjetas principales muestran una escala civil local de 00 a 24. PHP entrega primero los horarios, textos y un espacio reservado; `assets/js/home-sky.js` solicita después cada perfil por separado al proxy del sitio. El Sol compara invierno, hoy y verano; la Luna compara ayer, hoy y mañana. La línea principal conserva también las alturas negativas y su relleno se divide en regiones independientes, recortadas a 0° mediante interpolación lineal.

El marcador blanco interpola su altura entre muestras. Con reloj real se actualiza una vez por minuto sin repetir la solicitud. Con `debug_now` permanece fijo en el instante simulado. Cada SVG contiene título y descripción accesibles, y las series secundarias combinan color con distintos patrones de trazo.

`altitude-profile.php` es un proxy JSON del mismo origen que sólo admite `GET` y usa `Cache-Control: no-store`. Otros métodos responden 405 y `Allow: GET`. Valida la solicitud y la respuesta y llama a la API desde PHP, por lo que la URL interna no se expone. Sol y Luna se solicitan de manera independiente. El intervalo común se configura con `ALTITUDE_PROFILE_INTERVAL_MINUTES`, prioridad entorno → configuración externa → 15, y admite enteros de 5 a 60.

El gráfico solar conserva salida, puesta y duración, y compara invierno, hoy y verano. El lunar compara ayer, hoy y mañana; está en una fila inferior propia de la tarjeta, bajo la imagen y el texto, y ocupa su ancho útil sin reducir nuevamente la Luna. La leyenda no muestra “Muestras cada 15 min”.

#### Situación actual de la Luna

La portada realiza una única consulta adicional server-side a `/v1/moon/instant`, usando la misma latitud, longitud, zona horaria y el instante actual. El navegador nunca consulta ese endpoint. PHP valida `observer.altitude_degrees`, `observer.azimuth_degrees`, `observer.above_horizon` y `datetime.local`; si la petición falla o falta algún campo, omite solamente la frase y mantiene la tarjeta, los perfiles y el resto de la página.

Cuando `above_horizon` es falso, PHP busca una salida estrictamente futura. Usa primero `moon.rise` del día y, si esa hora ya pasó o no existe, consulta condicionalmente el día siguiente. La ventana efectiva `home.moonrise.max_minutes` usa el default editorial 120 o su override de MySQL. Las reglas son:

1. Fuera de la ventana: “No está sobre el horizonte.”
2. Entre 60 minutos y el límite: “La Luna saldrá a las HH:MM.”
3. Entre 15 y 59: “La Luna saldrá en X minutos.”
4. Entre 5 y 14: “La Luna está por salir.”
5. Menos de 5: “Preparate: la Luna está por salir.”

Los últimos tres estados usan `moonrise-notice--soon`, `--imminent` y `--now`. Son bandas sobrias; sólo `--now` pulsa suavemente el borde y la animación se elimina con `prefers-reduced-motion`.

Cuando está sobre el horizonte, la frase aplica estas reglas:

1. A no más de un día de la Luna nueva real más cercana: está sobre el horizonte, pero es prácticamente imposible verla.
2. A más de uno y hasta tres días: está muy finita y cuesta encontrarla a simple vista.
3. En los demás casos combina altura y dirección.

La Luna nueva más cercana se elige por diferencia absoluta entre `datetime.local` y todos los eventos `new_moon` de la consulta ampliada, que incluye normalmente la anterior y la siguiente. Sólo si no existe ningún evento válido se usa `phase.age_days` como respaldo, con el mes sinódico medio de 29,530588 días.

La altura se clasifica así: hasta 1° “apenas sobre el horizonte”; más de 1° y hasta 10° “muy baja”; más de 10° y hasta 25° “baja”; más de 25° y hasta 55° sin adjetivo; más de 55° “alta”. El acimut se normaliza al rango 0°–360° y se divide en ocho sectores de 45° centrados en norte, noreste, este, sudeste, sur, sudoeste, oeste y noroeste. La interfaz no muestra grados ni términos técnicos.

### Sol y Luna

`sol-y-luna.php` consulta `/v1/astronomy/range`. Incluye:

- formulario compacto con fecha inicial, ubicación, cantidad de días y botón Calcular;
- acciones para geolocalización y regreso a Buenos Aires;
- opciones avanzadas plegables para latitud, longitud y zona horaria;
- tabla con fecha compacta, horarios, duración del día, iluminación y edad lunar;
- miniatura lunar estática por fila;
- bandas de visibilidad construidas exclusivamente desde `sun.visibility_intervals` y `moon.visibility_intervals` del día civil local;
- escala de 00 a 24 y soporte para múltiples intervalos;
- popover accesible con fecha, salidas, puestas y resumen de visibilidad.

En escritorio la tabla usa columnas proporcionales. En móvil cada día se convierte en una tarjeta de dos líneas: resumen compacto y barra superpuesta de 24 horas. La tabla no genera scroll horizontal ni recorta datos.

Los encabezados y resúmenes usan `☀` para el Sol, `🌙` para la Luna, `↑` para salida y `↓` para puesta. El significado también se expresa mediante `aria-label`, títulos o texto visible; el color no es la única señal.

### Eventos

`eventos.php` consulta `/v1/astronomy/events` desde PHP. El formulario conserva en la URL la fecha inicial, el rango de 1 a 366 días y los filtros seleccionados; la ubicación usa las mismas cookies, geolocalización y fallback de Buenos Aires que el resto del sitio.

Los filtros disponibles son:

- fases lunares (`moon_phase`);
- perigeo y apogeo (`apsis`);
- conjunciones (`conjunction`);
- luz cenicienta (`earthshine`);
- libraciones destacadas (`libration`);
- eclipses lunares y solares (`eclipse`).

Los resultados se agrupan por fecha local y se presentan como eventos, no como datos técnicos. Las conjunciones son acercamientos geométricos: la indicación de visibilidad aclara si ambos cuerpos estaban simultáneamente sobre el horizonte desde la ubicación consultada. Luna fina y luz cenicienta se integran en una sola tarjeta por oportunidad: dos amaneceres antes y dos atardeceres después de Luna nueva, conservando la ventana histórica de luz cenicienta cuando también se cumple.

`includes/event-presentation.php` transforma los eventos de la API para la portada y para esta página mediante un único contrato de título, resumen, decisión y etiqueta horaria, detalles técnicos y explicación. La portada consume la versión compacta. En la página de eventos, los valores técnicos quedan ocultos en reposo y se consultan mediante el botón accesible **Datos técnicos**, que abre un popover nativo con una lista descriptiva.

Los eventos con hora confiable ofrecen **Agendar evento**, con opciones para Google
Calendar, Outlook y Apple Calendar/otros mediante `.ics`. El helper
`includes/calendar-event.php` comparte criterios entre Inicio, Eventos lunares y
Eclipses; el endpoint `calendar-event.php` entrega un `.ics` compatible con clientes
móviles y de escritorio.

El contrato común devuelve `title`, `summary`, `show_time`, `time_label`, `technical_details`, `explanation`, `public_details`, `contact_points` y `alert`. Centraliza las reglas humanas para conjunciones según separación, perigeo y apogeo, luz cenicienta, cuartos lunares, `full_moon_observation`, libraciones destacadas y eclipses. En libraciones (`type=libration`) la salida pública evita detalles internos de cálculo: presenta borde favorecido, amplitud aproximada y fase/iluminación sólo si están disponibles.

Las libraciones públicas usan textos amigables por subtipo:

- `libration_east`: “Libración favorable hacia el este”;
- `libration_west`: “Libración favorable hacia el oeste”;
- `libration_north`: “Libración favorable hacia el norte”;
- `libration_south`: “Libración favorable hacia el sur”.

La amplitud se muestra redondeada a un decimal con coma (`7,0°`). No se expone frame, kernels, schema, políticas internas ni claves de persistencia.

En eclipses (`type=eclipse`) la salida pública usa clasificación local de visibilidad, magnitudes y contactos ordenados por código (`P1/U1/U2/MAX/U3/U4/P4` en lunar y `C1/C2/MAX/C3/C4` en solar cuando existen). Si la API marca `near_central_path_boundary`, se muestra una advertencia visible para evitar interpretaciones rígidas de la fase central.

### Eclipses

`eclipses.php` consulta server-side `/v1/astronomy/events` exclusivamente con
`types=eclipse`, admite bloques de hasta cinco años y filtra por tipo y visibilidad
desde la ubicación global. El formulario limita la fecha final y el servidor rechaza
el exceso antes de consultar la API. El listado muestra sólo el resumen; el detalle
se abre en un `<dialog>` y separa **Datos generales**, **Desde tu ubicación** y
**Visibilidad mundial**.

Para lunares consume `details.eclipse_global`/`details.eclipse_local`; para solares, `details.solar_eclipse_global`/`details.solar_eclipse_local`. El bloque global puede incluir `visibility_map` con `status`, `available`, `source`, `catalog_url`, `source_url`, `local_filename`, `retrieved_at`, `map_kind` y `attribution`. La sección mundial exige `available === true`, `status === "available"` y un nombre simple seguro. La imagen usa `versionedAssetUrl("assets/images/eclipses/<local_filename>")`: resuelve `/assets/...` en Docker y `/astro/assets/...` en producción. `source_url` nunca se usa como imagen; sólo puede aparecer como enlace discreto. Metadatos ausentes, estados `not_found`, `ambiguous` o `download_error` y nombres inseguros ocultan la sección.

En `moon_phase/full_moon`, y sólo allí, el helper compara `details.apparent_size_percent` con el valor efectivo `event.supermoon.min_percent` (default 105, rango administrativo 90–120). Al alcanzar el umbral usa el nombre y el resumen editoriales efectivos. Es el mismo evento: no se agrega ni duplica y el porcentaje real sigue en datos técnicos.

Si no hay coincidencias se muestra un estado vacío; los fallos de configuración, cURL, HTTP o JSON se registran sin exponer detalles técnicos al visitante.

Inicio muestra la cobertura nubosa actual en “La Luna ahora”, e inicio y `eventos.php` enriquecen los eventos futuros de los próximos siete días con cobertura nubosa horaria de Open-Meteo. `assets/js/cloud-cover.js` se ejecuta después del render y hace una única consulta por ubicación con `current=cloud_cover` y los campos horarios `cloud_cover`, `cloud_cover_low`, `cloud_cover_mid` y `cloud_cover_high`, reutilizada mediante memoria y `sessionStorage`. El total se muestra solo y, cuando las tres capas son válidas, un botón informativo abre su distribución sin sumar porcentajes ni emitir valoraciones. La caché meteorológica vence a los 30 minutos. Un fallo, respuesta inválida o timeout de cinco segundos elimina silenciosamente los destinos; nunca bloquea astronomía, navegación ni controles.

### Navegación y encabezado

Las páginas públicas usan `includes/site-header.php`: muestran **Aquellas Lunas**, la
localidad global enlazada y un panel de menú. `includes/site-sections.php` centraliza
orden, URL, estado activo y participación en swipe. Inicio, Esta noche, Sol y Luna,
Planificador y Eventos forman el recorrido no circular; las demás páginas quedan fuera.

En pantallas de hasta 767 px, izquierda avanza y derecha retrocede. Touch Events sigue la trayectoria principal; Pointer Events sirve de respaldo y diagnóstico, sin doble decisión. Se mantienen 80 px, 700 ms y relación 1,5. `touch-action: pan-y pinch-zoom`, listeners pasivos y ausencia de `preventDefault()` preservan scroll vertical y zoom.

Se excluyen inicios sobre `a`, `button`, `input`, `select`, `textarea`, `[contenteditable]`, `iframe` o un ancestro `[data-swipe-navigation-ignore]`. Los mapas Leaflet de Ubicación y Planificador marcan todo su contenedor con este último atributo, por lo que arrastre, zoom, marcador y controles nunca alimentan el swipe global. SVG y perfiles de altura permiten swipe. Los contenedores con scroll horizontal se respetan únicamente si todavía pueden moverse en la dirección del gesto. El aviso inicial usa `localStorage` y puede deshabilitarse por separado.

### Acerca del sitio

`acerca-del-sitio.php` explica el origen del proyecto, su relación con la comunidad de Instagram y el propósito de acercar información astronómica clara a público general. La URL anterior `acerca.php` responde con una redirección 301 al nombre nuevo.

## Ubicación del visitante

Valores predeterminados:

- nombre: Buenos Aires;
- latitud: `-34.53`;
- longitud: `-58.48`;
- zona horaria: `America/Argentina/Buenos_Aires`.

La acción “Usar mi ubicación” existe sólo en `ubicacion.php`. JavaScript actualiza el
mapa y PHP valida los valores al guardar. El navegador nunca llama a la API de cálculos.

La selección se conserva durante 400 días con:

- `astro_latitude`;
- `astro_longitude`;
- `astro_timezone`;
- `astro_location_mode`;
- `astro_location_name`;
- `astro_location_confirmed`.

`astro_location_intro_seen` tiene la misma duración, pero sólo evita repetir la ayuda:
no confirma ni reemplaza una ubicación.

“Usar Buenos Aires” restaura los valores en el mapa; “Guardar ubicación” los aplica. Además del arrastre principal del marcador, el `TapHold` nativo de Leaflet permite mantener pulsada una zona no interactiva para moverlo y ejecutar el mismo flujo de geocodificación y actualización de campos. Leaflet controla la tolerancia de 10 px, cancelación por movimiento, segundo dedo y fin del gesto sin agregar una capa propia de eventos sobre el mapa.
Si la geolocalización falla, se conserva la ubicación activa y se informa el error.

## Imágenes de fases lunares

El sitio usa dos estrategias diferentes.

### 1. Miniaturas estáticas para la tabla

La colección se genera originalmente desde el proyecto de la API, cuyo directorio de salida previsto es:

```text
/srv/proyectos/astronomia/api/generated/moon-table/
```

La web conserva una copia propia e independiente en:

```text
assets/images/moon-phases/
```

Para actualizar esa copia desde el proyecto de la API:

```bash
cp -f /srv/proyectos/astronomia/api/generated/moon-table/*.png \
  /srv/proyectos/astronomia/web/assets/images/moon-phases/
```

Características:

- 404 imágenes;
- 48×48 píxeles;
- PNG RGBA;
- nombres inmutables `moon_NNN_DIRECTION_HEMISPHERE.png`;
- ejemplo: `moon_067_waxing_south.png`.

`includes/moon-images.php` redondea la iluminación, deriva `waxing` o `waning` desde `age_days` y elige `south` o `north` según la latitud:

```php
$percent = (int) round($illuminationPercent);
$direction = moonPhaseDirectionFromAge($ageDays);
$hemisphere = $latitude < 0 ? 'south' : 'north';
$filename = sprintf('moon_%03d_%s_%s.png', $percent, $direction, $hemisphere);
```

La tabla no solicita una imagen a la API por fila. Sirve estos PNG como assets estáticos y `versionedAssetUrl()` agrega la versión de `filemtime`; son aptos para caché larga porque una modificación cambia la URL. Si falta un archivo, el helper registra el error y reserva un espacio controlado.

### 2. Imagen dinámica en la portada

La portada carga `moon-image.php`, un proxy PHP del mismo origen. El proxy usa la configuración centralizada y consulta:

```text
/v1/moon/image
```

Parámetros:

- `orientation=apparent`;
- `latitude`;
- `longitude`;
- `timezone`;
- `datetime` ISO 8601 con offset;
- `size=240`.
- `terminator_softness`, definido en `moon-image.php` mediante `MOON_TERMINATOR_SOFTNESS` y limitado por la API al rango `0.0`–`0.2`.

La API deriva la fase del instante y calcula la inclinación aparente para el observador. CSS controla el tamaño visual. Una respuesta válida se cachea 15 minutos desde el proxy. Ante parámetros inválidos, error de configuración, cURL, HTTP o tipo de contenido, se sirve `moon_000_waxing_south.png` como fallback con `no-store`, sin perder los datos textuales.

## Caché y versionado de assets

Las miniaturas lunares tienen nombres estables y reciben query strings individuales basados en `filemtime`. El proxy lunar establece sus propias cabeceras de caché.

Las vistas dinámicas que usan `sendDynamicNoCacheHeaders()` —Inicio, Esta noche, Sol y Luna, Planificador, Eventos, Eclipses, Ubicación y Galería— envían directivas privadas `no-store/no-cache`. Los endpoints JSON auxiliares también deshabilitan caché. Los assets estáticos usan `versionedAssetUrl()`, que añade `?v=<filemtime>` cuando el archivo existe.

Estas directivas no se aplican a `assets/`: CSS, JavaScript, iconos y las miniaturas lunares estáticas mantienen el comportamiento normal de caché de Apache/navegador. Una imagen válida de `moon-image.php` conserva su caché pública de 15 minutos; su fallback de error usa `no-store`.

El cliente PHP realiza como máximo dos intentos. Reintenta una sola vez, después de 500 ms, cuando cURL no puede completar la conexión o cuando la API responde 502, 503 o 504. No reintenta respuestas funcionales como 400. Los HTTP no exitosos, cuerpos inválidos y JSON inválido nunca se convierten en datos vacíos exitosos. Cuando el diagnóstico está activo muestra intentos, presencia y código de error cURL, HTTP final, validez JSON y si se renderizaron datos correctos o un estado de error.

Ante una falla, la vista mantiene disponible el resto del sitio y muestra un aviso discreto con el botón **Reintentar**, que vuelve a solicitar la página al servidor. `assets/js/page-recovery.js` también reintenta solamente si el documento está en estado de error: al recuperarlo desde bfcache mediante `pageshow`, o al volver a verlo después de al menos cinco segundos oculto mediante `visibilitychange`. Un límite de diez segundos evita recargas repetidas. Las páginas sanas no se recargan al alternar aplicaciones.

`includes/asset-url.php` centraliza el versionado automático de recursos propios. CSS, JavaScript y miniaturas lunares locales conservan su política normal de caché, pero sus URLs incorporan `?v=<filemtime>` y cambian cuando cambia el archivo. Si el recurso no existe o no puede leerse su fecha, el helper devuelve la ruta original.

## Diseño y accesibilidad

- tema oscuro;
- encabezado responsive con marca, subtítulo y menú activo;
- favicon SVG, ICO, PNG y Apple Touch Icon;
- formulario compacto;
- tabla de tipografía y espaciado reducidos;
- tabla sin scroll horizontal: filas tabulares en escritorio y bloques verticales en móvil;
- miniatura y gráfico en una misma celda;
- CSS y JavaScript externos, sin frameworks;
- popover de Cielo reutilizable, no modal y estructurado;
- iconos compactos y flechas semánticas con colores independientes para Sol, Luna, salida y puesta.

El popover abre con puntero, foco o toque, se cierra con Escape o interacción exterior, mantiene una sola instancia y contiene una zona vacía preparada para un futuro minigráfico.

## Estructura principal

```text
.
├── index.php
├── cielo-de-esta-noche.php
├── sol-y-luna.php
├── planificador.php
├── eventos.php
├── eclipses.php
├── ubicacion.php
├── acerca-del-sitio.php
├── galeria.php
├── altitude-profile.php
├── moon-image.php
├── includes/
│   ├── api-config.php
│   ├── api-client.php
│   ├── store-database.php
│   ├── store-photo-sync.php
│   ├── store-preview-generator.php
│   ├── store-gallery.php
│   ├── current-datetime.php
│   ├── site-sections.php
│   ├── tonight.php
│   ├── analytics.php
│   ├── asset-url.php
│   ├── favicon-links.php
│   ├── seo.php
│   ├── event-presentation.php
│   ├── home-sky.php
│   ├── presentation.php
│   └── moon-images.php
├── admin/
│   ├── contenidos/                 # editor e importación JSON
│   ├── configuracion-sitio/        # visibilidad de secciones
│   ├── presentacion/               # tipos de eventos y reglas/mensajes
│   ├── fotos.php
│   └── laboratorio-astronomico.php
├── assets/
│   ├── css/styles.css
│   ├── js/location.js
│   ├── js/home-sky.js
│   ├── js/mobile-swipe-navigation.js
│   ├── js/page-recovery.js
│   ├── js/events.js
│   ├── js/eclipses.js
│   ├── js/planner.js
│   ├── js/sky-timeline.js
│   ├── js/sky-popover.js
│   ├── js/gallery.js
│   ├── images/moon-phases/       # 404 PNG versionados
│   ├── images/eclipses/          # GIF generados e ignorados por Git
│   └── images/tienda/previews/   # derivados generados
├── scripts/
│   ├── check-store-database.php
│   ├── sync-store-photos.php
│   ├── generate-store-previews.php
│   ├── desplegar.sh
│   └── php-container
├── docs/
│   ├── arquitectura.md
│   ├── configuracion.md
│   ├── entorno-local.md
│   └── despliegue.md
├── apache/astro.conf
├── Dockerfile
├── docker-compose.yml
└── php.ini
```

## Desarrollo y verificaciones

```bash
cd /srv/proyectos/astronomia/web
docker compose up -d --build
docker compose ps
docker compose logs --tail=100
```

Usar `--build` cuando cambien la imagen o sus archivos de construcción (`Dockerfile`, `php.ini` o configuración de Apache). Para aplicar cambios de `.env` o variables de Compose sin reconstruir la imagen alcanza con:

```bash
docker compose up -d --force-recreate web
```

Abrir `http://localhost:18080/` y `http://localhost:18080/sol-y-luna.php`.

Eventos: `http://localhost:18080/eventos.php`. Para una consulta compartible, los parámetros `start_date`, `days` y `types[]` permanecen en la query string.

Validar PHP:

```bash
docker compose exec -T web php -l index.php
docker compose exec -T web php -l sol-y-luna.php
docker compose exec -T web php -l eventos.php
docker compose exec -T web php -l acerca.php
docker compose exec -T web php -l acerca-del-sitio.php
docker compose exec -T web php -l altitude-profile.php
docker compose exec -T web php -l moon-image.php
docker compose exec -T web php -l includes/api-config.php
docker compose exec -T web php -l includes/api-client.php
docker compose exec -T web php -l includes/current-datetime.php
docker compose exec -T web php -l includes/event-presentation.php
docker compose exec -T web php -l includes/presentation.php
docker compose exec -T web php -l includes/moon-images.php
docker compose exec -T web php -l includes/site-sections.php
```

Verificar respuestas y assets:

```bash
curl -fsS -o /dev/null -w '%{http_code}\n' http://localhost:18080/
curl -fsS -o /dev/null -w '%{http_code}\n' http://localhost:18080/sol-y-luna.php
curl -fsS -o /dev/null -w '%{http_code}\n' 'http://localhost:18080/eventos.php?start_date=2026-07-19&days=30&filters_submitted=1&types[]=moon_phase&types[]=apsis&types[]=conjunction&types[]=earthshine'
find assets/images/moon-phases -maxdepth 1 -name 'moon_*.png' -type f | wc -l
```

Probar el despliegue sin transferir:

```bash
./scripts/desplegar.sh --list-local
export ASTRONOMY_FTP_PASSWORD='contraseña'
./scripts/desplegar.sh --dry-run
unset ASTRONOMY_FTP_PASSWORD
```

Los mapas mundiales de eclipses se guardan como assets generados en `assets/images/eclipses/`: no se versionan, pero el mirror de despliegue sí los incluye y no borra los ya presentes en el hosting. La verificación posterior se documenta en `docs/despliegue.md`.

La guía detallada del entorno está en `docs/entorno-local.md` y el procedimiento FTPS en `docs/despliegue.md`.

## Pendientes reales

- configurar explícitamente caché larga para miniaturas en el servidor si cPanel no la aplica;
- agregar automatización de pruebas de navegador para responsive, popover y geolocalización;
- agregar una opción explícita si se decide impedir que Analytics cargue en desarrollo local.
- implementar la descarga pública y el envío al comprador; hoy un pago aprobado sólo crea el permiso en base;
- retirar el diagnóstico temporal detallado del webhook después de confirmar el recorrido productivo;
- verificar externamente qué cambios locales ya están desplegados: el árbol Git no demuestra el estado del hosting.
