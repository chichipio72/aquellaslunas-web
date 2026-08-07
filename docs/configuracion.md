# Configuración

## Prioridad

Salvo que se indique otra cosa, la prioridad es:

1. variable de entorno no vacía;
2. clave equivalente en `/home8/aquellaslunascom/config/astronomia.php`;
3. valor predeterminado del código.

El archivo externo está fuera de `public_html`. `loadAstronomyProductionConfig()` debe devolver un arreglo; las opciones inválidas producen una excepción que se registra y se maneja según el componente.

`APP_ENV` es la excepción deliberada: se lee únicamente del entorno. Solo el valor
`local` habilita capacidades locales. Si falta, está vacío o contiene cualquier otro
valor, `appEnvironment()` devuelve `production`.

## Matriz de opciones

| Variable | Clave externa | Predeterminado | Validación | Uso |
|---|---|---:|---|---|
| `APP_ENV` | — | `production` | únicamente `local`; todo otro valor es producción | entorno general de ejecución |
| `ASTRONOMY_API_BASE_URL` | `astronomy_api_base_url` | sin valor válido | URL HTTP/HTTPS | API server-side |
| `ASTRONOMY_EVENT_SOURCE_MOON_PHASE` | — | `api` | `api`, `database`, `php`, `auto` o `compare` | fallback cuando no existe selección administrativa persistida para fases |
| `ASTRONOMY_EVENT_SOURCE_LUNAR_APSIS` | — | `api` | `api`, `database`, `php`, `auto` o `compare` | fallback administrativo para ápsides lunares |
| `ASTRONOMY_EVENT_SOURCE_LUNAR_ORBIT` | — | `api` | `api`, `database`, `php`, `auto` o `compare` | fallback administrativo para nodos lunares |
| `ASTRONOMY_EVENT_SOURCE_LUNAR_LIBRATION` | — | `api` | `api`, `database`, `php`, `auto` o `compare` | fallback administrativo para libraciones |
| `ASTRONOMY_EVENT_SOURCE_LUNAR_CONJUNCTION` | — | `api` | `api`, `database`, `php`, `auto` o `compare` | fallback administrativo para conjunciones lunares |
| `ASTRONOMY_EVENT_SOURCE_ECLIPSE` | — | `api` | `api`, `database`, `php`, `auto` o `compare` | fallback administrativo para eclipses solares y lunares |
| `ASTRONOMY_REVERSE_GEOCODER_URL` | — | URL de Nominatim | URL HTTPS | geocodificación inversa server-side |
| `ASTRONOMY_REVERSE_GEOCODER_USER_AGENT` | — | identificación de Aquellas Lunas | texto | `User-Agent` para Nominatim |
| `LOCAL_TIME_SIMULATION_ENABLED` | `local_time_simulation_enabled` | `false` | booleano mediante `filter_var` | control y sesión local de simulación temporal |
| `ALTITUDE_PROFILE_INTERVAL_MINUTES` | `altitude_profile_interval_minutes` | `15` | entero 5–60 | Sol y Luna, mismo intervalo |
| `SUPERMOON_MIN_APPARENT_SIZE_PERCENT` | `supermoon_min_apparent_size_percent` | `105` | número finito 90–120 | compatibilidad histórica; el criterio público efectivo se administra como `event.supermoon.min_percent` |
| `MOONRISE_NOTICE_MAX_MINUTES` | `moonrise_notice_max_minutes` | `120` | entero 1–1440 | compatibilidad histórica; la ventana pública efectiva se administra como `home.moonrise.max_minutes` |
| `MOBILE_SWIPE_NAVIGATION_ENABLED` | `mobile_swipe_navigation_enabled` | `true` | `true/false`, `1/0`, `yes/no`, `on/off` | script y contexto swipe |
| `MOBILE_SWIPE_NAVIGATION_HINT_ENABLED` | `mobile_swipe_navigation_hint_enabled` | `true` | los mismos booleanos | aviso inicial localStorage |
| `MOBILE_SWIPE_NAVIGATION_DEBUG_ENABLED` | `mobile_swipe_navigation_debug_enabled` | `false` | los mismos booleanos | panel visual, combinado con timings |
| `STORE_ORIGINALS_PATH` | `store_originals_path` | sin valor | directorio absoluto, existente y legible | originales privados de la tienda |
| `STORE_PREVIEWS_PATH` | `store_previews_path` | sin valor | directorio absoluto, existente, legible y escribible | previews públicos de la tienda |
| `STORE_CATALOG_PATH` | `store_catalog_path` | sin valor | directorio absoluto, existente, legible y escribible | catálogo privado de la tienda |
| `STORE_DB_HOST` | `store_db_host` | sin valor | texto no vacío | host MySQL de la tienda |
| `STORE_DB_PORT` | `store_db_port` | sin valor | entero 1–65535 | puerto MySQL de la tienda |
| `STORE_DB_NAME` | `store_db_name` | sin valor | texto no vacío | base MySQL de la tienda |
| `STORE_DB_USER` | `store_db_user` | sin valor | texto no vacío | usuario MySQL, secreto |
| `STORE_DB_PASSWORD` | `store_db_password` | sin valor | texto no vacío | contraseña MySQL, secreto |
| `WEB_DB_HOST` | `web_db_host` | sin valor | texto no vacío | MySQL de contenidos, configuración editorial y laboratorio |
| `WEB_DB_PORT` | `web_db_port` | `3306` | entero 1–65535 | puerto de `WEB_DB` |
| `WEB_DB_NAME` | `web_db_name` | sin valor | texto no vacío | nombre de `WEB_DB` |
| `WEB_DB_USER` | `web_db_user` | sin valor | texto no vacío | usuario de `WEB_DB`, secreto |
| `WEB_DB_PASSWORD` | `web_db_password` | sin valor | texto no vacío | contraseña de `WEB_DB`, secreto |
| `WEB_PUSH_VAPID_PUBLIC_KEY` | `web_push_vapid_public_key` | sin valor | Base64 URL de clave pública P-256 | suscripción desde el piloto administrativo; único valor VAPID entregado al navegador |
| `WEB_PUSH_VAPID_PRIVATE_KEY` | `web_push_vapid_private_key` | sin valor | Base64 URL privada P-256 | emisor administrativo en hosting y CLI de la mini PC; nunca se entrega al navegador |
| `WEB_PUSH_VAPID_SUBJECT` | `web_push_vapid_subject` | sin valor | `mailto:` válido o URL HTTPS | identidad VAPID compartida por portada y emisor |
| `STORE_ADMIN_USER` | `store_admin_user` | sin valor | texto no vacío, máximo 190 caracteres | usuario del portal privado |
| `STORE_ADMIN_PASSWORD_HASH` | `store_admin_password_hash` | sin valor | hash reconocido por PHP | autenticación mediante `password_verify()` |
| `STORE_INITIAL_PRICE` | `store_initial_price` | sin valor | decimal mayor que 0, hasta 8 enteros y 2 decimales | precio de fotos nuevas |
| `STORE_PREVIEW_TIENDA_MAX_SIZE` | `store_preview_tienda_max_size` | `800` | entero 320–8000 | lado mayor del derivado comercial con marca |
| `STORE_PREVIEW_TIENDA_JPEG_QUALITY` | `store_preview_tienda_jpeg_quality` | `72` | entero 30–95 | calidad JPEG comercial |
| `STORE_PREVIEW_CONTENIDO_MAX_SIZE` | `store_preview_contenido_max_size` | `400` | entero 320–8000 | lado mayor del derivado editorial sin marca |
| `STORE_PREVIEW_CONTENIDO_JPEG_QUALITY` | `store_preview_contenido_jpeg_quality` | `72` | entero 30–95 | calidad JPEG editorial |
| `STORE_DOWNLOAD_EXPIRY_HOURS` | `store_download_expiry_hours` | `72` | entero 1–8760 | vigencia del permiso generado tras un pago aprobado |
| `STORE_DOWNLOAD_MAX_COUNT` | `store_download_max_count` | `5` | entero 1–100 | máximo futuro de usos del permiso |
| `MERCADO_PAGO_MODE` | `mercado_pago_mode` | sin valor | `test` o `production` | entorno de Checkout Pro |
| `MERCADO_PAGO_ACCESS_TOKEN` | `mercado_pago_access_token` | sin valor | secreto no vacío | credencial privada server-side |
| `MERCADO_PAGO_PUBLIC_KEY` | `mercado_pago_public_key` | sin valor | valor no vacío | clave pública, todavía no emitida al navegador |
| `MERCADO_PAGO_WEBHOOK_SECRET` | `mercado_pago_webhook_secret` | sin valor | puede estar vacío en `test`; obligatorio en `production` | firma HMAC de notificaciones |
| `MERCADO_PAGO_SUCCESS_URL` | `mercado_pago_success_url` | sin valor | URL HTTPS | retorno exitoso futuro |
| `MERCADO_PAGO_PENDING_URL` | `mercado_pago_pending_url` | sin valor | URL HTTPS | retorno pendiente futuro |
| `MERCADO_PAGO_FAILURE_URL` | `mercado_pago_failure_url` | sin valor | URL HTTPS | retorno fallido futuro |
| `MERCADO_PAGO_NOTIFICATION_URL` | `mercado_pago_notification_url` | sin valor | URL HTTPS | receptor público de pagos |

Cuando un grupo está configurado en `api`, una falla técnica de FastAPI activa la cadena interna API → MariaDB → PHP. Esto incluye `eclipse`. Una respuesta API válida sin eventos no activa fallback. `database` consulta sólo MariaDB; `php`, sólo el motor portable; `auto` usa MariaDB cuando el intervalo completo cae dentro de 1900–2050 y PHP fuera de esa cobertura; `compare` devuelve MariaDB como resultado principal y calcula PHP sólo para diagnóstico. En eclipses de MariaDB, las circunstancias globales persistidas se enriquecen con circunstancias locales PHP para el observador.

Los cálculos generales se guardan en `admin_configuracion_sitio` con claves
`astronomy.data_source.daily`, `.range`, `.directions`, `.moon_instant`,
`.altitude_profile` y `.tonight`; aceptan `api` o `php`, y la no elegida queda
como fallback. `astronomy.data_source.moon_image` acepta `static` o `api`.

La entrada pública **Explorador astronómico** usa la clave
`menu.explorer.enabled`, administrada en **Visibilidad de secciones**. Su valor
predeterminado es `true`. Desactivarla oculta sólo el enlace del menú y no
bloquea `/explorador/` ni sus endpoints.

`MERCADO_PAGO_NOTIFICATION_URL` documenta y valida la URL registrada centralmente en **Tus integraciones → Webhooks**. No se incluye como `notification_url` al crear preferencias, para no reemplazar el canal firmado configurado en el panel.

Los tiempos de API se renderizan automáticamente para `canUseSiteDebugTools()`. `LOCAL_TIME_SIMULATION_ENABLED` es el interruptor técnico local; en producción la sesión admin válida autoriza el simulador. No existe una opción para Analytics: el ID `G-GFZJ3D3MF3` está definido en `includes/analytics.php` y se carga también en local.

## Docker Compose y `.env`

`docker-compose.yml` publica las variables enumeradas en su bloque `environment`. La URL local de API queda fijada en `http://host.docker.internal:18000`; `ALTITUDE_PROFILE_INTERVAL_MINUTES` usa fallback Compose `15`. Las variables `ASTRONOMY_REVERSE_GEOCODER_*` figuran en `.env.example`, pero el Compose actual no las reenvía; PHP usa sus predeterminados mientras esa asignación no exista.

El `.env` local deseado contiene:

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
WEB_DB_HOST=
WEB_DB_PORT=3306
WEB_DB_NAME=
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

Después de cambiar `.env` o variables de Compose hay que recrear el servicio, sin reconstruir la imagen:

```bash
docker compose up -d --force-recreate web
```

## Web Push MVP

El piloto no ofrece suscripción pública. `/admin/notificaciones-prueba.php` carga la clave pública para suscribir el dispositivo autenticado y usa la clave privada sólo server-side al enviar. `loadWebPushServerConfig()` exige las tres claves; la privada nunca se serializa en HTML.

Generar una única pareja VAPID con OpenSSL y conservarla sin rotarla mientras existan suscripciones:

```bash
openssl ecparam -genkey -name prime256v1 -out /tmp/aquellas-lunas-vapid.pem
openssl ec -in /tmp/aquellas-lunas-vapid.pem -pubout -outform DER | tail -c 65 | base64 | tr -d '=' | tr '/+' '_-'
openssl ec -in /tmp/aquellas-lunas-vapid.pem -outform DER | tail -c +8 | head -c 32 | base64 | tr -d '=' | tr '/+' '_-'
```

La primera salida es `WEB_PUSH_VAPID_PUBLIC_KEY` y la segunda `WEB_PUSH_VAPID_PRIVATE_KEY`. El PEM temporal también es secreto y debe eliminarse de forma segura después de guardar las dos claves. En producción agregar `web_push_vapid_public_key`, `web_push_vapid_private_key` y `web_push_vapid_subject` al arreglo privado externo. El navegador recibe únicamente la pública.

La tabla se prepara una vez con:

```bash
docker exec web-astro php /var/www/html/scripts/migrations/create-web-push-subscriptions.php
```

El envío manual requiere Python 3.10+ y las dependencias de `requirements.txt` (`pywebpush` 2.x, `mysql-connector-python` y `python-dotenv`):

```bash
.venv/bin/pip install -r requirements.txt
.venv/bin/python scripts/send-test-push.py
.venv/bin/python scripts/send-test-push.py --id 1
```

El panel del hosting usa las dependencias PHP bloqueadas en `composer.lock`. Prepararlas antes de desplegar:

```bash
docker run --rm -u 1000:1000 -v /srv/proyectos/astronomia/web:/app -w /app composer:2 install --no-dev --optimize-autoloader
```

Cuando se actualicen dependencias, el despliegue debe ejecutarse con `--include-vendor` para incluir `vendor/autoload.php` y `vendor/`. Composer no necesita estar instalado en cPanel.

La suscripción puede comprobarse sin consultar ni mostrar sus claves:

```sql
SELECT id, active, created_at, updated_at, last_success_at, last_error_at
FROM web_push_subscriptions
ORDER BY id DESC;
```

Usar `--build` sólo si cambian `Dockerfile`, `php.ini` o Apache.

## Configuración externa de producción

Ejemplo completo:

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
    'web_push_vapid_public_key' => 'CLAVE_PUBLICA_BASE64URL',
    'web_push_vapid_private_key' => 'CLAVE_PRIVADA_BASE64URL',
    'web_push_vapid_subject' => 'mailto:tu-correo@example.com',
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

`loadStoreConfig()` resuelve las tres rutas con la prioridad general de esta página y valida el conjunto antes de devolverlo. Originales sólo requiere lectura; previews y catálogo requieren lectura y escritura. Ante cualquier problema lanza un error genérico que no revela nombres de rutas ni valores internos.

`loadStoreDatabaseConfig()` resuelve las cinco opciones MySQL con la misma prioridad, exige que ninguna esté vacía y valida el puerto entre 1 y 65535. El cargador no conecta por sí mismo; `getStoreDatabaseConnection()` consume su resultado cuando una conexión es necesaria. Usuario y contraseña se completan exclusivamente en el `.env` local ignorado y en el archivo externo privado de producción; no deben escribirse en documentación, logs ni respuestas públicas.

`loadWebDatabaseConfig()` y `getWebDatabaseConnection()` resuelven de igual forma `WEB_DB`. Esta conexión sirve al contenido público y su editor, visibilidad de secciones, tipos de eventos, reglas editoriales y Laboratorio. Usa `utf8mb4`, excepciones, fetch asociativo y prepares nativos. No es PostgreSQL ni reemplaza la API astronómica.

`loadStoreAdminConfig()` resuelve usuario y hash administrativo con la misma prioridad. No existe contraseña predeterminada ni tabla de usuarios. El hash se genera con `password_hash()` y se valida con `password_verify()`; el valor real y la contraseña no deben versionarse ni aparecer en mensajes.

En producción deben agregarse al arreglo externo las claves `store_db_host`, `store_db_port`, `store_db_name`, `store_db_user` y `store_db_password` con los valores reales administrados fuera del repositorio. Los valores no se reproducen en esta documentación.

`loadStoreInitialPrice()` resuelve `STORE_INITIAL_PRICE` o `store_initial_price`, exige un valor positivo compatible con `DECIMAL(10,2)` y lo normaliza a dos decimales. No tiene predeterminado: el precio es una decisión comercial explícita.

Los cuatro cargadores de previews resuelven por separado tamaño y calidad de tienda/contenido. Sus valores predeterminados son 800/72 y 400/72; las claves externas son las equivalentes `store_preview_tienda_*` y `store_preview_contenido_*`.

`loadMercadoPagoConfig()` exige modo, access token, public key y cuatro URLs. Sólo acepta `test` o `production`, y todas las URLs deben ser HTTPS sin credenciales embebidas. El secreto webhook puede estar vacío únicamente en modo `test`; en `production` es obligatorio. Token, public key y secreto webhook se devuelven exactamente como fueron configurados. El cargador no conecta, no crea preferencias y no publica ningún valor al navegador; cualquier falla produce el mismo error genérico. Sin secreto, el receptor sólo admite mocks en modo `test` originados desde loopback y rechaza cualquier notificación externa.

`loadStoreDownloadExpiryHours()` y `loadStoreDownloadMaxCount()` aplican entorno → configuración externa → 72/5 y validan los rangos de la tabla. Estos valores sólo crean permisos en `descargas`; todavía no existe un endpoint público para consumirlos.

`APP_ENV` puede omitirse en producción: ausencia, vacío y valores desconocidos se interpretan como producción. Timings y simulación requieren allí una sesión admin válida. El panel de navegación táctil requiere además `mobile_swipe_navigation_debug_enabled => true`.

La detección está centralizada en `appEnvironment()`, `isLocalEnvironment()` e
`isProductionEnvironment()` dentro de `includes/api-config.php`.
`isContentEnabled()` consulta `content.enabled` en `admin_configuracion_sitio`; no
depende de `APP_ENV` ni de una bandera de publicación por entorno.

Los parámetros y textos de presentación no se agregan a este archivo externo: sus defaults cerrados viven en `includes/editorial-configuration.php` y los overrides en `admin_parametros_editoriales` y `admin_textos_editoriales`. La visibilidad y los nombres de eventos siguen el mismo patrón mediante su catálogo PHP y las tablas `admin_tipos_eventos*`.

## Reloj simulado

En local el reloj exige `LOCAL_TIME_SIMULATION_ENABLED=true`. En producción esa
bandera no concede acceso ni bloquea al administrador: una sesión admin válida es
la única puerta. La fecha se guarda en `aquellas_lunas_local`, separada de
`aquellas_lunas_admin`, y los POST usan CSRF. **Usar hora real** elimina la simulación.

Con simulación, consultas, fechas predeterminadas, selección editorial y marcadores usan el instante indicado. Los perfiles se solicitan una vez y el marcador no crea temporizador. Cachés, logs, sesiones y expiraciones de seguridad conservan el reloj real.

## Trazabilidad astronómica

La opción `astronomy.trace.enabled` de Configuración del sitio habilita el registro reproducible de consultas de alto nivel en `astronomy_request_log`. Está deshabilitada por defecto. El navegador recibe una cookie de sesión HttpOnly con un identificador aleatorio, sin reutilizar IDs reales de sesión ni guardar IP, user-agent o identidad.

La retención predeterminada es de 30 días. La limpieza se ejecuta manualmente con `php scripts/cleanup-astronomy-request-log.php --days=30`; `ASTRONOMY_TRACE_RETENTION_DAYS` permite cambiar ese valor para la invocación CLI. No existe cron automático para esta limpieza.
