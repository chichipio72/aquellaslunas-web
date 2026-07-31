# Desarrollo local y verificación

## Entorno

La copia de trabajo está en `/srv/proyectos/astronomia/web`. Docker Compose ejecuta Apache con PHP 8.5:

- proyecto Compose: `web-astro`;
- servicio: `web`;
- contenedor: `web-astro`;
- imagen base: `php:8.5-apache`;
- volumen: `.` → `/var/www/html`;
- segundo montaje del mismo proyecto en `/srv/proyectos/astronomia/web`, para que las rutas absolutas de tienda coincidan entre host y contenedor;
- puerto: `18080:80`;
- reinicio: `unless-stopped`;
- URL: `http://localhost:18080/`.
- edición habitual desde VS Code mediante Remote SSH.

`Dockerfile` instala curl, mbstring, intl, mysqli y PDO MySQL, habilita `rewrite` y carga `php.ini` y `apache/astro.conf`. La zona horaria PHP es `America/Argentina/Buenos_Aires`; `display_errors` y `expose_php` están desactivados.

Docker declara `APP_ENV=local`. La detección general vive en
`includes/api-config.php`: sólo `local` habilita funciones de la mini PC; una
variable ausente, vacía o inválida se interpreta como `production`. Las banderas de
diagnóstico, navegación o cualquier otra función no deben usarse como detección del
entorno.

## API local

`docker-compose.yml` define:

```text
ASTRONOMY_API_BASE_URL=http://host.docker.internal:18000
```

`extra_hosts: host.docker.internal:host-gateway` permite alcanzar desde el contenedor la API ejecutada en el host. PHP, no JavaScript, consulta `/v1/astronomy/daily`, `/v1/moon/instant`, `/v1/astronomy/range` y `/v1/astronomy/events`. `moon-image.php` actúa como proxy server-side para `/v1/moon/image`. El navegador nunca llama directamente a la API.

La portada solicita sus perfiles del Sol y la Luna por separado a `altitude-profile.php`. Este proxy del mismo origen valida ubicación, fecha y zona horaria antes de consultar `/v1/astronomy/altitude-profile`; una falla de un perfil no impide cargar el otro. `ALTITUDE_PROFILE_INTERVAL_MINUTES` configura ambos perfiles, admite valores de 5 a 60 y usa 15 de forma predeterminada.

`SUPERMOON_MIN_APPARENT_SIZE_PERCENT` configura desde qué tamaño relativo una Luna llena se presenta como **Superluna**. Admite números entre 90 y 120, usa 105 de forma predeterminada y puede definirse en `.env` para Docker Compose.

`MOONRISE_NOTICE_MAX_MINUTES` configura con cuánta anticipación la portada empieza a anunciar la próxima salida lunar. Admite enteros de 1 a 1440 y usa 120 de forma predeterminada. También respeta `debug_now`.

`MOBILE_SWIPE_NAVIGATION_ENABLED` habilita el recorrido táctil móvil entre Inicio, Esta noche, Sol y Luna, Planificador y Eventos. `MOBILE_SWIPE_NAVIGATION_HINT_ENABLED` controla por separado el aviso inicial guardado en `localStorage`. Ambas usan `true` de forma predeterminada. `MOBILE_SWIPE_NAVIGATION_DEBUG_ENABLED` controla únicamente el panel visual y usa `false`: incluso en modo timings, el swipe navega automáticamente mientras esa opción siga apagada.

`LOCAL_TIME_SIMULATION_ENABLED=true` habilita el control «Modo de prueba» únicamente
si además `APP_ENV=local`. La fecha y hora se guardan en una sesión PHP y se
interpretan como hora local de la ubicación activa; al cambiar de zona horaria se
conserva la hora de pared elegida. Con la opción ausente o en `false`, el servidor no
inicia esta sesión, no renderiza el control e ignora `debug_now`.
`CONTENT_ENABLED_IN_PRODUCTION=false` queda preparado para la futura sección de
contenido; `isContentEnabled()` devuelve siempre `true` en local.

La sección **Contenidos** usa archivos PHP bajo `includes/contenido/`. En la cabecera
local aparece **Ver errores de contenido**: activa por sesión un diagnóstico que
mantiene visibles los problemas de archivos, metadatos, Markdown, referencias,
trivias, entradas “Sabías que…” e imágenes. El control no existe en producción y no
requiere variables adicionales.

Los recursos reutilizables de artículos, trivias y “Sabías que…” se colocan en
`assets/images/tienda/previews/contenido/`. Si una referencia editorial no coincide con un
archivo, localmente se usa otra imagen válida de esa carpeta; si no hay ninguna, el
contenido continúa sin imagen. El modo debug informa el reemplazo como advertencia,
no como error.

La referencia completa de prioridad, claves externas y validación está en [configuracion.md](configuracion.md). La arquitectura funcional está en [arquitectura.md](arquitectura.md).

## Analytics local

Inicio, Esta noche, Sol y Luna, Planificador, Eventos, Eclipses, Ubicación, Galería y Acerca invocan `renderAnalyticsTracking()` con el ID `G-GFZJ3D3MF3`. No hay una opción local para deshabilitarlo: localhost intenta descargar `gtag.js` cuando tiene red.

Si la variable no existe o está vacía, el cargador intenta `/home8/aquellaslunascom/config/astronomia.php`. Esta segunda ruta corresponde a producción.

## Diagnóstico de tiempos

Docker Compose carga automáticamente `.env`. El archivo local actual contiene:

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

No contiene secretos y no se despliega al hosting. Una variable ya exportada en la sesión de shell tiene prioridad sobre el valor de `.env`. Después de cambiarlo, recrear el servicio para aplicar el entorno:

```bash
cd /srv/proyectos/astronomia/web
docker compose up -d --force-recreate web
```

Con valor verdadero muestra al final de las vistas que consultan la API, incluida Esta noche, el tiempo interno informado por la API, cuando existe, el tiempo total observado por PHP/cURL, intentos, validación y código HTTP. También incorpora `Server-Timing` si está disponible. Para desactivarlo, establecer `ASTRONOMY_SHOW_TIMINGS=false` en `.env` y recrear.

El panel fijo de swipe sólo aparece si también se cambia `MOBILE_SWIPE_NAVIGATION_DEBUG_ENABLED=true` y se recrea el servicio. Registra fuente Touch/Pointer, movimientos, `touch-action`, coordenadas, exclusión, resultado y URL. Sólo con ambas opciones activas deja el destino pendiente para el botón. La configuración local normal conserva timings y reloj simulado, pero no muestra el panel y permite navegación automática.

### Rutas de la tienda

Copiar `.env.example` como punto de partida o agregar las tres variables `STORE_*` al `.env` existente. Antes de recrear el servicio, preparar los directorios:

```bash
mkdir -p storage/tienda/originales assets/images/tienda/previews storage/tienda/catalogo
docker compose up -d --force-recreate web
docker compose exec -T web chgrp www-data /srv/proyectos/astronomia/web/assets/images/tienda/previews /srv/proyectos/astronomia/web/storage/tienda/catalogo
docker compose exec -T web chmod 2775 /srv/proyectos/astronomia/web/assets/images/tienda/previews /srv/proyectos/astronomia/web/storage/tienda/catalogo
docker compose exec -T --user www-data web php -r 'require "/var/www/html/includes/api-config.php"; $config = loadStoreConfig(); echo count($config) === 3 ? "store config ok\n" : "store config error\n";'
```

El bit setgid conserva el grupo `www-data` en archivos futuros de previews y catálogo. La comprobación se ejecuta como el usuario de Apache para validar permisos efectivos y no imprime las rutas. Originales queda sólo legible para ese usuario. No hay galería ni generación de previews; el sincronizador CLI sólo lee imágenes y registra metadatos.

### Mercado Pago

Completar el access token y la public key únicamente en el `.env` ignorado. En modo `test`, `MERCADO_PAGO_WEBHOOK_SECRET` puede permanecer vacío y la creación de preferencias seguirá disponible. Mantener `MERCADO_PAGO_MODE=test` en desarrollo y recrear el servicio. La carga puede comprobarse sin imprimir tokens, claves, secretos ni URLs:

```bash
docker compose up -d --force-recreate web
docker compose exec -T web php -r 'require "/var/www/html/includes/api-config.php"; $config = loadMercadoPagoConfig(); echo count($config) === 8 ? "mercado pago config ok\n" : "mercado pago config error\n";'
docker compose exec -T web php tests/mercado-pago-config.php
```

Esta comprobación no abre conexiones ni llama a Mercado Pago.

El receptor permite secreto vacío únicamente para mocks en modo `test` originados desde loopback. Una notificación externa sin secreto se rechaza antes de consultar la API. En `production`, el cargador exige una clave no vacía.

Antes de usar credenciales reales, ejecutar el flujo completo con mocks:

```bash
docker compose exec -T web php tests/store-checkout.php
docker compose exec -T web bash tests/store-checkout-http.sh
docker compose exec -T web php tests/store-download-config.php
docker compose exec -T web php tests/store-payment-webhook.php
docker compose exec -T web bash tests/store-payment-webhook-http.sh
```

Las pruebas crean fotos, pedidos, ítems, pagos y permisos dentro de transacciones que revierten. Los transportes simulados validan Bearer, firma, idempotencia, estados, `external_reference`, monto, moneda, rollback y repetición sin tráfico externo. También comprueban que los retornos no aprueban pagos ni habilitan descargas públicas.

El simulador puede enviar un `data.id` ficticio. Si la firma es válida y Mercado Pago responde 404 al consultar ese pago, el receptor confirma la recepción con HTTP 200 y `{"status":"ignored"}`, sin tocar ninguna tabla. Sólo ese estado es terminal: 401/403, 429, 5xx, cURL o una respuesta 200 inválida conservan HTTP 503.

El diagnóstico interno sanitizado se comprueba sin tráfico ni pedidos adicionales con:

```bash
docker compose exec -T web php tests/mercado-pago-diagnostics.php
```

La prueba cubre cURL, error HTTP con causa y respuestas inválidas, y verifica que el access token y los campos no permitidos del cuerpo no lleguen al log.

Sólo después de estas pruebas, completar credenciales de prueba, mantener `MERCADO_PAGO_MODE=test`, recrear el servicio y seleccionar una foto desde `http://localhost:18080/galeria.php`. Las URLs de retorno son públicas y no deben reemplazarse por localhost. No configurar aún una URL productiva ni efectuar compras productivas.

Después de desplegar el receptor, comprobar primero que `GET https://aquellaslunas.com.ar/astro/webhooks/mercado-pago.php` responda 405 y `Allow: POST`. Luego, en **Tus integraciones → aplicación → Webhooks → Configurar notificaciones**, registrar esa URL en el entorno de prueba y seleccionar el evento **Pagos**. Las preferencias no incluyen `notification_url`, por lo que este registro central es el único canal. Al guardar, Mercado Pago genera la firma secreta: revelarla y copiarla sólo a `MERCADO_PAGO_WEBHOOK_SECRET` local o `mercado_pago_webhook_secret` en la configuración privada del hosting. Recrear el contenedor cuando corresponda y usar el simulador de Mercado Pago antes de una compra de prueba.

### MySQL de la tienda

Completar `STORE_DB_USER` y `STORE_DB_PASSWORD` únicamente en `.env`, que está ignorado por Git. Después de recrear el servicio, verificar la carga sin imprimir ningún valor:

```bash
docker compose up -d --force-recreate web
docker compose exec -T web php -r 'require "/var/www/html/includes/api-config.php"; $config = loadStoreDatabaseConfig(); echo count($config) === 5 ? "store database config ok\n" : "store database config error\n";'
```

El cargador valida presencia y puerto sin conectarse. Para comprobar la conexión PDO desde consola, el script interno ejecuta únicamente `SELECT 1 AS ok` y no imprime configuración:

```bash
docker compose exec -T web php scripts/check-store-database.php
```

Debe responder `store database connection: ok`. Apache deniega acceso HTTP a todo `scripts/` y esa carpeta también está excluida del despliegue FTPS; el verificador sólo funciona con PHP CLI.

### Sincronización de originales

Definir un precio positivo en `STORE_INITIAL_PRICE`, recrear el servicio y revisar primero sin insertar:

```bash
docker compose up -d --force-recreate web
docker compose exec -T web php scripts/sync-store-photos.php --dry-run
```

Si el resumen es correcto, ejecutar la sincronización real:

```bash
docker compose exec -T web php scripts/sync-store-photos.php
```

El resumen informa `encontrados`, `nuevos`, `existentes`, `omitidos` y `errores`. Sólo se insertan JPG/JPEG válidos que no tengan ya el mismo SHA-256. No se actualizan filas, no se desactivan ausentes, no se consulta ni modifica `pedido_fotos` y todavía no se crean previews.

La prueba automatizada usa una carpeta temporal, un duplicado por contenido, extensiones en mayúsculas, un JPEG inválido y elementos omitidos. Valida el `INSERT` dentro de una transacción que luego revierte, ejecuta también dry-run y confirma que los totales de `fotos` y `pedido_fotos` no cambien:

```bash
docker compose exec -T web php tests/store-photo-sync.php
```

### Generación de previews

La configuración inicial genera una preview comercial de 800 px/calidad 72 con marca de agua y otra editorial de 400 px/calidad 72 sin marca. Revisar primero las fotos pendientes sin crear carpetas, archivos ni actualizar la base:

```bash
docker compose exec -T web php scripts/generate-store-previews.php --dry-run
```

Generar los previews pendientes:

```bash
docker compose exec -T web php scripts/generate-store-previews.php
```

Para regenerar ambos derivados de todas las fotos, incluso los válidos ya asociados:

```bash
docker compose exec -T web php scripts/generate-store-previews.php --force
```

Los nombres son `tienda/<foto_id>.jpg` y `contenido/<foto_id>.jpg`. Se verifica el SHA-256 del original, se respeta orientación EXIF, no se amplían imágenes pequeñas y cada columna se actualiza sólo después de publicar su JPEG. `generados` y `existentes` cuentan derivados, mientras `pendientes` cuenta fotos procesadas.

La prueba crea originales y previews temporales, usa una fila transaccional con rollback y comprueba dry-run, proporción, límite, no ampliación, orientación, hash incorrecto, reutilización y `--force`:

```bash
docker compose exec -T web php tests/store-preview-generator.php
```

La galería pública se verifica con una prueba transaccional que cubre filtros, orden, conexión reutilizada, título opcional, precio y rechazo de rutas inseguras:

```bash
docker compose exec -T web php tests/store-gallery.php
```

Abrir `http://localhost:18080/galeria.php` y comprobar la cuadrícula en anchos de escritorio y móvil, imágenes sin deformación, precio/moneda, ausencia de nombre técnico cuando no hay título y apertura/cierre del modal con puntero, teclado y Escape. Galería está en el menú pero deliberadamente fuera del recorrido swipe. Si no hay filas disponibles con preview se muestra un estado vacío; un fallo SQL muestra sólo el mensaje público genérico.

### Administración privada

Generar el hash desde una consola segura. El comando lee la contraseña por entrada estándar y sólo imprime el hash:

```bash
docker compose run --rm -T web php -r '$password = rtrim(stream_get_contents(STDIN), "\r\n"); echo password_hash($password, PASSWORD_DEFAULT), PHP_EOL;'
```

Copiar únicamente el resultado a `STORE_ADMIN_PASSWORD_HASH` en `.env`, definir `STORE_ADMIN_USER` y recrear el servicio. El punto de entrada es `http://localhost:18080/admin/` y no se enlaza desde el menú público. Después del login se abre el panel general, con accesos a Galería y tienda y al Laboratorio Astronómico. Las tres pantallas comparten la navegación Inicio, Galería, Laboratorio y Cerrar sesión mediante `includes/store-admin-navigation.php`, reutilizando la sesión administrativa existente.

La galería administrativa permanece en `admin/fotos.php`; permite controlar publicación, administrar precios y editar título, descripción y palabras clave. Los campos editoriales pueden vaciarse para persistir `NULL`; se guardan como texto plano y no afectan metadatos técnicos.

```bash
docker compose up -d --force-recreate web
docker compose exec -T web php tests/store-admin.php
docker compose exec -T web php tests/astronomy-laboratory-extrema.php
docker compose exec -T web bash tests/store-admin-http.sh
```

La prueba PHP administrativa usa credenciales efímeras y una transacción revertida para login, sesión, CSRF, logout, disponibilidad, precios y metadatos editoriales completos, parciales, vacíos y excesivos. La prueba de extremos valida catálogo, rangos y consultas reales con `LAG()`/`LEAD()`. La prueba HTTP verifica redirecciones, panel, ambos modos del laboratorio, formularios administrativos, cabeceras privadas, logout sólo por POST y bloqueo de archivos internos.

Los nombres de portada son `moon instant`, `daily`, `home phases` y `home upcoming`. Como referencia no contractual, se observaron localmente unos 57 ms para `daily`, 90 ms para fases y 288 ms para próximos eventos. No dejar habilitado el diagnóstico en producción salvo durante una comprobación puntual.

### Reloj simulado

Con `APP_ENV=local` y `LOCAL_TIME_SIMULATION_ENABLED=true` se muestran en el
encabezado los campos de fecha y hora, **Aplicar** y, cuando corresponde,
**Usar hora real**. `get_current_datetime()` aplica el valor guardado en sesión a
consultas, fechas predeterminadas, descarte de eventos pasados y marcadores; cookies,
timeouts y mediciones siguen usando tiempo real. En los perfiles simulados no se crea
el temporizador por minuto.

## Presentación de eventos

La portada consulta “Lo próximo” por tramos no superpuestos: 7 días, los 7
siguientes y finalmente 16 días. Se detiene cuando, después de filtrar, deduplicar y
ordenar, reúne los seis eventos visibles. Conserva
`types=moon_phase,apsis,conjunction,earthshine,full_moon_observation` y
`max_difference_minutes=70`. `eventos.php` mantiene su consulta normal independiente.
`includes/event-presentation.php` comparte los títulos, resúmenes, reglas horarias,
explicaciones y datos técnicos.

La portada muestra una versión compacta. En `eventos.php`, el botón **Datos técnicos** abre un popover nativo accesible; separación, iluminación, altura, distancia y tamaño relativo no se muestran fuera de ese popover.

Una respuesta `moon_phase/full_moon` con `details.apparent_size_percent >= 105` se presenta como **Superluna** con la configuración predeterminada. Probar también valores inferiores y el límite exacto; el evento no debe duplicarse y el porcentaje real debe seguir en el popover.

## Comandos

Construir y levantar:

```bash
cd /srv/proyectos/astronomia/web
docker compose up -d --build
```

Estado y logs:

```bash
docker compose ps
docker compose logs --tail=100
```

Reiniciar o detener:

```bash
docker compose restart
docker compose down
```

Después de cambios en `Dockerfile`, `php.ini` o `apache/astro.conf`, reconstruir con `docker compose up -d --build`. Para cambios sólo de `.env` o variables de Compose usar `docker compose up -d --force-recreate web`; no hace falta reconstruir la imagen.

## Verificación manual

### Páginas

```bash
curl -fsS -o /dev/null -w '%{http_code}\n' http://localhost:18080/
curl -fsS -o /dev/null -w '%{http_code}\n' http://localhost:18080/cielo-de-esta-noche.php
curl -fsS -o /dev/null -w '%{http_code}\n' http://localhost:18080/sol-y-luna.php
curl -fsS -o /dev/null -w '%{http_code}\n' http://localhost:18080/eventos.php
curl -fsS -o /dev/null -w '%{http_code}\n' http://localhost:18080/eclipses.php
curl -fsS -o /dev/null -w '%{http_code}\n' http://localhost:18080/planificador.php
curl -fsS -o /dev/null -w '%{http_code}\n' http://localhost:18080/ubicacion.php
curl -fsS -o /dev/null -w '%{http_code}\n' http://localhost:18080/galeria.php
curl -fsS -o /dev/null -w '%{http_code}\n' http://localhost:18080/acerca-del-sitio.php
```

En navegador comprobar:

- Buenos Aires como ubicación inicial en todas las páginas;
- búsqueda, marcador movible y geolocalización en `ubicacion.php`;
- tarjeta “¿Qué planetas se ven esta noche?” enlazada completa y accesible desde teclado;
- página Esta noche con ventana local y secciones Planetas, Luna, Estrellas y Otros objetos;
- estados `visible_now`, `visible_later`, medianoche, cercanía lunar, dirección arriba y ayudas de observación;
- constelaciones de estrellas, planetas, Luna y cúmulos tomadas de la API, sin identificadores internos ni separadores vacíos;
- orden de estrellas idéntico al recibido y ocultación completa de categorías sin objetos observables;
- gráfico horario de nubosidad recortado a la ventana nocturna, incluyendo el cruce de medianoche;
- barras de 0 %, valores intermedios y 100 %, con popover por clic, toque o teclado y cierre por Escape/exterior;
- ocultación completa del gráfico ante fallo, timeout o serie inválida, y omisión aislada de capas ausentes;
- tarjeta persistente sin planetas y degradación aislada ante error del endpoint nocturno;
- pulsación prolongada mediante `TapHold` de Leaflet en el mapa de ubicación, con tolerancia de 10 px y cancelación nativa por movimiento, segundo dedo o fin del gesto;
- arrastre y zoom en los mapas de Ubicación y Planificador sin activar el swipe global, conservando el swipe fuera de ellos;
- guardado de nombre, coordenadas, zona, modo y persistencia tras navegar;
- “Usar Buenos Aires” seguido de “Guardar ubicación”;
- encabezado, panel, Escape, clic exterior y página activa;
- formulario de Sol y Luna limitado a fecha inicial y cantidad de días;
- tabla sin scroll horizontal;
- reorganización completa de filas en móvil;
- bandas, múltiples intervalos y miniaturas;
- popover mediante mouse, teclado, Escape y toque;
- popover **Datos técnicos** de las tarjetas de eventos mediante teclado y puntero;
- filtros y modal de `eclipses.php`, con separación entre datos locales y visibilidad mundial;
- ausencia del mapa mundial en el listado y cuando faltan metadatos o el estado no es disponible;
- imagen mundial responsive, diferida, atribuida y sin hotlink;
- cobertura nubosa sólo en eventos futuros de hasta siete días, con el texto exacto “Cobertura nubosa prevista: N %”;
- detalle accesible de nubes bajas, medias y altas cuando las tres capas estén disponibles, sin sumar porcentajes;
- apertura del detalle mediante clic, toque y teclado, y cierre mediante Escape o interacción fuera;
- cobertura nubosa actual en “La Luna ahora”, con el texto “Cobertura nubosa actual: N %”;
- ausencia silenciosa de nubosidad al bloquear Open-Meteo o superar cinco segundos;
- textos humanos iguales en la portada y `eventos.php`;
- `full_moon_observation` y el límite de 70 minutos en “Lo próximo”;
- perfiles de Sol y Luna cargados por separado, sus tres series, horizonte, relleno y marcador;
- aplicación inmediata y única de “Usar ahora” y los cuatro horarios rápidos del Planificador, con controles y resumen sincronizados;
- ausencia del acceso rápido correspondiente cuando el día no tiene una salida o puesta;
- error independiente de cada solicitud a `altitude-profile.php` y ausencia de exposición de la API interna;
- aviso lunar a 130, 90, 30, 10, 3 y 1 minutos de la próxima salida;
- estilos `soon`, `imminent` y `now`, incluido `prefers-reduced-motion`;
- reloj simulado en las páginas que cargan el helper y retorno a hora real;
- swipe sin circularidad entre Inicio, Esta noche, Sol y Luna, Planificador y Eventos;
- inicio del gesto sobre SVG, rechazo sobre controles y respeto por scroll horizontal real;
- scroll vertical, diagonales, zoom y ausencia de respuesta al mouse;
- aviso de descubrimiento una vez mediante `localStorage`;
- con timings y swipe debug activos, diagnóstico Touch y botón de destino; con sólo timings, panel ausente y navegación automática; con navegación deshabilitada, ausencia de script y contexto;
- Analytics visible en Network/HTML en las nueve vistas públicas, o bloqueo local intencional documentado;
- imagen aparente de portada para más de una ubicación.

### Sintaxis PHP

```bash
docker compose exec -T web php -l index.php
docker compose exec -T web php -l cielo-de-esta-noche.php
docker compose exec -T web php -l sol-y-luna.php
docker compose exec -T web php -l planificador.php
docker compose exec -T web php -l eventos.php
docker compose exec -T web php -l eclipses.php
docker compose exec -T web php -l ubicacion.php
docker compose exec -T web php -l moon-image.php
docker compose exec -T web php -l altitude-profile.php
docker compose exec -T web php -l galeria.php
docker compose exec -T web php tests/tonight-presentation.php
docker compose exec -T web php -l acerca.php
docker compose exec -T web php -l acerca-del-sitio.php
docker compose exec -T web php -l includes/api-config.php
docker compose exec -T web php -l includes/api-client.php
docker compose exec -T web php -l includes/store-gallery.php
docker compose exec -T web php -l includes/store-database.php
docker compose exec -T web php -l includes/current-datetime.php
docker compose exec -T web php -l includes/event-presentation.php
docker compose exec -T web php -l includes/presentation.php
docker compose exec -T web php -l includes/moon-images.php
docker compose exec -T web php -l includes/site-sections.php
docker compose exec -T web php -l scripts/check-store-database.php
```

### API y logs

```bash
curl -v https://api.aquellaslunas.com.ar/
docker compose logs --tail=100 web
```

Verificar el contrato y la ausencia de caché del proxy de perfiles:

```bash
curl -i 'http://localhost:18080/altitude-profile.php?target=sun&date=2026-07-21&latitude=-34.53&longitude=-58.48&timezone=America%2FArgentina%2FBuenos_Aires'
curl -i 'http://localhost:18080/altitude-profile.php?target=moon&date=2026-07-21&latitude=-34.53&longitude=-58.48&timezone=America%2FArgentina%2FBuenos_Aires'
```

Ambas respuestas correctas deben incluir tres series, el intervalo efectivo y `Cache-Control: no-store`. Probar por separado una indisponibilidad de Sol y otra de Luna desde DevTools o bloqueando cada solicitud para confirmar fallos aislados.

El proxy sólo admite `GET`. La prueba automatizada de métodos se ejecuta dentro del contenedor con:

```bash
docker compose exec -T web bash tests/altitude-profile-http-methods.sh
```

`POST`, `PUT` y `DELETE` deben responder 405, `Allow: GET`, JSON genérico y `Cache-Control: no-store`.

La prueba de regresión del swipe se abre sólo en local:

```text
http://localhost:18080/tests/mobile-swipe-navigation.test.html
```

Comprueba cancelación Pointer sin deltas falsos, continuidad con Touch Events, trayectoria vertical rechazada, `touch-action` y persistencia del destino al tocar el botón. El directorio `tests/` está excluido del FTPS.

Los logs indican origen de configuración (`environment` o `external_file`), estado HTTP, errores cURL y respuestas inválidas. No deben incluir credenciales, cookies ni cuerpos completos.

### Assets lunares

```bash
find assets/images/moon-phases -maxdepth 1 -name 'moon_*.png' -type f | wc -l
file assets/images/moon-phases/moon_067_waxing_south.png
```

El resultado esperado es 404 archivos y PNG RGBA de 48×48.

Actualizar la copia estática desde la salida pregenerada de la API:

```bash
cp -f /srv/proyectos/astronomia/api/generated/moon-table/*.png \
  /srv/proyectos/astronomia/web/assets/images/moon-phases/
```

### Mapas de eclipses

Los GIF descargados por la operación de la API viven en `assets/images/eclipses/`; la web nunca usa NASA como host de la imagen.

```bash
test -f assets/images/eclipses/solar-eclipse-2027-02-06.gif
chmod 0644 assets/images/eclipses/*.gif
curl -fsSI http://localhost:18080/assets/images/eclipses/solar-eclipse-2027-02-06.gif
```

La respuesta debe ser HTTP 200 con `Content-Type: image/gif`. El HTML usa una ruta relativa `assets/images/eclipses/...`: el navegador resuelve `/assets/...` en Docker y `/astro/assets/...` en producción.

## Compatibilidad PHP 8.5

El contenedor local incluye mbstring, pero el hosting no necesariamente. `includes/presentation.php` debe probar la disponibilidad conjunta de sus funciones `mb_*` y conservar el fallback UTF-8 para español. No instalar ni asumir extensiones para esta operación.

No agregar `curl_close()`: está obsoleta en PHP 8.5. `includes/api-client.php` centraliza cURL, tiempos, cabeceras, estado HTTP y errores para todas las páginas y para `moon-image.php`.

### Despliegue de prueba

```bash
export ASTRONOMY_FTP_PASSWORD='contraseña'
./scripts/desplegar.sh --dry-run
unset ASTRONOMY_FTP_PASSWORD
```

## Diferencias con producción

Producción usa cPanel y PHP 8.5 mediante Alt-PHP / CGI-FastCGI en `/home8/aquellaslunascom/public_html/astro`, publicado como `https://aquellaslunas.com.ar/astro/`. No usa Docker.

La configuración se lee desde `/home8/aquellaslunascom/config/astronomia.php`; no se usa `.htaccess SetEnv` como única fuente porque no propagó de forma confiable la variable a PHP bajo FastCGI.

El archivo debe permanecer fuera de `/home8/aquellaslunascom/public_html`:

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

CSS, JavaScript y miniaturas lunares locales usan `includes/asset-url.php` para agregar una versión basada en `filemtime()`. No se deshabilita la caché de estáticos: al modificar un archivo cambia su URL y el navegador solicita la versión nueva.

Producción no usa `.env` ni Docker. Puede omitir `APP_ENV`: el valor seguro
predeterminado es `production`. El simulador no inicia sesión, no renderiza controles
y no acepta `debug_now` salvo que coincidan `APP_ENV=local` y
`LOCAL_TIME_SIMULATION_ENABLED=true`.

Analytics no distingue entornos y también se carga en producción con el mismo ID. En el hosting, las páginas viven bajo `/astro/`; assets y endpoints del mismo origen deben resolver bajo ese prefijo.
