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
| `CONTENT_ENABLED_IN_PRODUCTION` | — | `false` | booleano mediante `filter_var` | futura sección de contenido; local siempre habilitado |
| `ASTRONOMY_API_BASE_URL` | `astronomy_api_base_url` | sin valor válido | URL HTTP/HTTPS | API server-side |
| `ASTRONOMY_REVERSE_GEOCODER_URL` | — | URL de Nominatim | URL HTTPS | geocodificación inversa server-side |
| `ASTRONOMY_REVERSE_GEOCODER_USER_AGENT` | — | identificación de Aquellas Lunas | texto | `User-Agent` para Nominatim |
| `ASTRONOMY_SHOW_TIMINGS` | `astronomy_show_timings` | `false` | booleano mediante `filter_var` | tiempos y requisito del panel swipe |
| `LOCAL_TIME_SIMULATION_ENABLED` | `local_time_simulation_enabled` | `false` | booleano mediante `filter_var` | control y sesión local de simulación temporal |
| `ALTITUDE_PROFILE_INTERVAL_MINUTES` | `altitude_profile_interval_minutes` | `15` | entero 5–60 | Sol y Luna, mismo intervalo |
| `SUPERMOON_MIN_APPARENT_SIZE_PERCENT` | `supermoon_min_apparent_size_percent` | `105` | número finito 90–120 | clasificación editorial de Luna llena |
| `MOONRISE_NOTICE_MAX_MINUTES` | `moonrise_notice_max_minutes` | `120` | entero 1–1440 | ventana de aviso de salida |
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

`MERCADO_PAGO_NOTIFICATION_URL` documenta y valida la URL registrada centralmente en **Tus integraciones → Webhooks**. No se incluye como `notification_url` al crear preferencias, para no reemplazar el canal firmado configurado en el panel.

`ASTRONOMY_SHOW_TIMINGS` usa la misma prioridad, aunque su cargador tolera cualquier valor que `FILTER_VALIDATE_BOOLEAN` interprete como falso. Es una bandera de diagnóstico y no identifica el entorno. `LOCAL_TIME_SIMULATION_ENABLED` sólo se evalúa cuando `APP_ENV=local`; aun configurada en `true`, no habilita el simulador en producción. No existe una opción para Analytics: el ID `G-GFZJ3D3MF3` está definido en `includes/analytics.php` y se carga también en local.

## Docker Compose y `.env`

`docker-compose.yml` publica las variables enumeradas en su bloque `environment`. La URL local de API queda fijada en `http://host.docker.internal:18000`; `ALTITUDE_PROFILE_INTERVAL_MINUTES` usa fallback Compose `15`. Las variables `ASTRONOMY_REVERSE_GEOCODER_*` figuran en `.env.example`, pero el Compose actual no las reenvía; PHP usa sus predeterminados mientras esa asignación no exista.

El `.env` local deseado contiene:

```dotenv
APP_ENV=local
LOCAL_TIME_SIMULATION_ENABLED=true
CONTENT_ENABLED_IN_PRODUCTION=false
ASTRONOMY_SHOW_TIMINGS=true
MOBILE_SWIPE_NAVIGATION_ENABLED=true
MOBILE_SWIPE_NAVIGATION_HINT_ENABLED=true
MOBILE_SWIPE_NAVIGATION_DEBUG_ENABLED=false
STORE_ORIGINALS_PATH=/srv/proyectos/astronomia/web/storage/tienda/originales
STORE_PREVIEWS_PATH=/srv/proyectos/astronomia/web/assets/images/tienda/previews
STORE_CATALOG_PATH=/srv/proyectos/astronomia/web/storage/tienda/catalogo
STORE_DB_HOST=167.250.5.41
STORE_DB_PORT=3306
STORE_DB_NAME=aquellaslunascom_tienda_dev
STORE_DB_USER=
STORE_DB_PASSWORD=
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

Usar `--build` sólo si cambian `Dockerfile`, `php.ini` o Apache.

## Configuración externa de producción

Ejemplo completo:

```php
<?php

return [
    'astronomy_api_base_url' => 'https://api.aquellaslunas.com.ar',
    'astronomy_show_timings' => false,
    'altitude_profile_interval_minutes' => 15,
    'supermoon_min_apparent_size_percent' => 105,
    'moonrise_notice_max_minutes' => 120,
    'mobile_swipe_navigation_enabled' => true,
    'mobile_swipe_navigation_hint_enabled' => true,
    'mobile_swipe_navigation_debug_enabled' => false,
    'store_originals_path' => '/home8/aquellaslunascom/fotos_tienda/originales',
    'store_previews_path' => '/home8/aquellaslunascom/public_html/astro/assets/images/tienda/previews',
    'store_catalog_path' => '/home8/aquellaslunascom/fotos_tienda/catalogo',
    'store_admin_user' => 'usuario-administrativo',
    'store_admin_password_hash' => 'HASH_GENERADO_FUERA_DEL_REPOSITORIO',
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

`loadStoreAdminConfig()` resuelve usuario y hash administrativo con la misma prioridad. No existe contraseña predeterminada ni tabla de usuarios. El hash se genera con `password_hash()` y se valida con `password_verify()`; el valor real y la contraseña no deben versionarse ni aparecer en mensajes.

En producción deben agregarse al arreglo externo las claves `store_db_host`, `store_db_port`, `store_db_name`, `store_db_user` y `store_db_password` con los valores reales administrados fuera del repositorio. Los valores no se reproducen en esta documentación.

`loadStoreInitialPrice()` resuelve `STORE_INITIAL_PRICE` o `store_initial_price`, exige un valor positivo compatible con `DECIMAL(10,2)` y lo normaliza a dos decimales. No tiene predeterminado: el precio es una decisión comercial explícita.

Los cuatro cargadores de previews resuelven por separado tamaño y calidad de tienda/contenido. Sus valores predeterminados son 800/72 y 400/72; las claves externas son las equivalentes `store_preview_tienda_*` y `store_preview_contenido_*`.

`loadMercadoPagoConfig()` exige modo, access token, public key y cuatro URLs. Sólo acepta `test` o `production`, y todas las URLs deben ser HTTPS sin credenciales embebidas. El secreto webhook puede estar vacío únicamente en modo `test`; en `production` es obligatorio. Token, public key y secreto webhook se devuelven exactamente como fueron configurados. El cargador no conecta, no crea preferencias y no publica ningún valor al navegador; cualquier falla produce el mismo error genérico. Sin secreto, el receptor sólo admite mocks en modo `test` originados desde loopback y rechaza cualquier notificación externa.

`loadStoreDownloadExpiryHours()` y `loadStoreDownloadMaxCount()` aplican entorno → configuración externa → 72/5 y validan los rangos de la tabla. Estos valores sólo crean permisos en `descargas`; todavía no existe un endpoint público para consumirlos.

Producción normalmente mantiene timings, diagnóstico visual del swipe y simulación temporal en falso. `APP_ENV` puede omitirse: ausencia, vacío y valores desconocidos se interpretan como producción. Timings muestra métricas pero no habilita por sí solo el reloj simulado. El panel y la pausa de navegación requieren además `mobile_swipe_navigation_debug_enabled => true`.

La detección está centralizada en `appEnvironment()`, `isLocalEnvironment()` e
`isProductionEnvironment()` dentro de `includes/api-config.php`.
`isContentEnabled()` habilita siempre el contenido futuro en local y, en producción,
únicamente con `CONTENT_ENABLED_IN_PRODUCTION=true`. Ninguna bandera correspondiente
a una funcionalidad particular debe utilizarse como indicador general del entorno.

## Reloj simulado

El reloj sólo se habilita cuando `APP_ENV=local` y además
`LOCAL_TIME_SIMULATION_ENABLED=true` —o su clave externa equivalente es verdadera—.
Aunque la bandera específica quede activada por error en producción, el simulador
permanece bloqueado. El encabezado guarda fecha y hora local en una sesión PHP; no es
necesario propagar parámetros. **Usar hora real** elimina la simulación. Con la
bandera apagada, `debug_now`, sesiones previas y formularios de simulación se ignoran.

Con simulación, consultas, fechas predeterminadas, selección editorial y marcadores usan el instante indicado. Los perfiles se solicitan una vez y el marcador no crea temporizador. Cachés, logs, sesiones y expiraciones de seguridad conservan el reloj real.
