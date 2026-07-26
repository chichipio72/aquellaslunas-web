# Despliegue por FTPS explícito

## Dependencias públicas de ubicación

`ubicacion.php` carga Leaflet 1.9.4 desde `unpkg.com` con SRI, mosaicos desde
`tile.openstreetmap.org` y búsqueda/geocodificación desde
`nominatim.openstreetmap.org`. No requiere claves. CSP, proxy y firewall deben permitir
esos dominios. Sin JavaScript, PHP mantiene la última ubicación válida o Buenos Aires.
`planificador.php` reutiliza esas dependencias. La API desplegada debe incluir
`GET /v1/astronomy/directions` y `GET /v1/astronomy/events`; una versión anterior
produce un error público 503 sin filtrar la URL interna. El proxy
`astronomy-featured-dates.php` usa caché privada de una hora.

## Destino

- Hosting: cPanel.
- PHP: 8.5 mediante Alt-PHP / CGI-FastCGI.
- Ruta física: `/home8/aquellaslunascom/public_html/astro`.
- URL: `https://aquellaslunas.com.ar/astro/`.
- Servidor FTPS: `set.servidoraweb.net`.
- Puerto: `21`.
- Usuario: `andres@aquellaslunas.com.ar`.

La cuenta FTP entra directamente en la carpeta remota efectiva de publicación, que corresponde a la carpeta pública `astro`. El script no ejecuta `cd public_html/astro` porque ese directorio ya es la raíz visible de la cuenta. Producción no usa Docker.

## Configuración externa previa

Crear fuera del directorio público:

```text
/home8/aquellaslunascom/config/astronomia.php
```

Contenido, sin incluir secretos en el repositorio:

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

Esta es la fuente de producción cuando `ASTRONOMY_API_BASE_URL` no está disponible. `.htaccess SetEnv` no se considera una solución única fiable bajo Alt-PHP/FastCGI.

El archivo debe permanecer fuera de `public_html`, no forma parte del repositorio y nunca se transfiere mediante el script FTPS.

El mismo arreglo privado debe incluir `store_db_host`, `store_db_port`, `store_db_name`, `store_db_user` y `store_db_password`. Sus valores reales no se documentan ni se incorporan al repositorio. `loadStoreDatabaseConfig()` exige las cinco claves y un puerto entre 1 y 65535. La conexión PDO reutilizable no revela credenciales al fallar; registra únicamente tipo y código técnico sanitizados.

La sincronización requiere además `store_initial_price`, un decimal positivo compatible con `DECIMAL(10,2)`. El script se ejecuta primero con `--dry-run` y luego, tras revisar el resumen, sin esa opción. `scripts/` no se publica por FTPS: la sincronización en hosting debe ejecutarse desde una copia interna autorizada o mediante una tarea CLI fuera del document root, nunca como endpoint web.

La generación usa parámetros separados: tienda 800/72 con marca de agua y contenido 400/72 sin marca. Producción necesita GD con JPEG y, para orientación, EXIF. Los derivados viven bajo `tienda/` y `contenido/`, están excluidos del FTPS y deben generarse directamente en el entorno correspondiente mediante el proceso CLI interno.

El usuario administrativo y el hash real se agregan únicamente al archivo externo privado mediante `store_admin_user` y `store_admin_password_hash`. Generar el hash con `password_hash()` en una consola segura; no almacenar ni documentar la contraseña en texto plano. Verificar que `/astro/admin/fotos.php` redirija al login sin sesión y que `/astro/scripts/` continúe respondiendo 403.

Mercado Pago se configura en el mismo archivo privado con claves `mercado_pago_*`; agregar además `store_download_expiry_hours` y `store_download_max_count`. El secreto webhook puede omitirse sólo para mocks locales en `test` y es obligatorio en `production`. Publicar `webhooks/mercado-pago.php` y los includes asociados, pero mantener las compras productivas deshabilitadas hasta completar pruebas con credenciales de test. El access token y el secreto nunca deben incorporarse a HTML, JavaScript, logs o respuestas públicas. Los retornos siguen siendo informativos y no habilitan descargas.

Tras publicar, verificar por HTTPS que un GET al webhook responda 405 con `Allow: POST`, sin detalles internos. Recién entonces registrar `https://aquellaslunas.com.ar/astro/webhooks/mercado-pago.php` como URL de prueba en **Tus integraciones → Webhooks**, activar el evento Pagos, guardar, revelar la firma generada y almacenarla como `mercado_pago_webhook_secret` en `/home8/aquellaslunascom/config/astronomia.php`. Las preferencias no envían `notification_url`: el único canal es el configurado en el panel, con validación obligatoria de `x-signature`. Usar primero el simulador; no registrar todavía la URL productiva ni cambiar `mercado_pago_mode` a `production`.

Una simulación firmada con un ID ficticio debe devolver HTTP 200 y `{"status":"ignored"}` cuando la API confirme que el pago no existe mediante 404. Un 503 sigue indicando conectividad, credenciales, límite, error del proveedor o respuesta inválida y debe investigarse en los logs sanitizados.

Antes de habilitar cualquier función futura de tienda, crear las tres carpetas declaradas. Originales y catálogo quedan fuera de `public_html`; sólo previews pertenece al árbol público. El proceso PHP debe poder leer las tres y escribir en previews y catálogo. `loadStoreConfig()` rechaza el conjunto completo si falta una carpeta o un permiso, sin exponer rutas en el error público.

Asignar en cPanel el propietario/grupo correspondiente al proceso PHP y permisos mínimos equivalentes: lectura en originales, y lectura/escritura en previews y catálogo. No usar permisos públicos `777`. Verificar mediante PHP bajo el mismo usuario que atiende la web, sin imprimir las rutas en una respuesta HTTP.

`astronomy_show_timings` debe permanecer en `false` normalmente. Al cambiarlo explícitamente a `true`, las páginas HTML agregan al final un bloque discreto con estado HTTP, tiempo interno de la API y tiempo total observado por la web. La misma condición habilita `debug_now` y su barra de simulación; con el valor normal de producción en `false`, ese parámetro se ignora. La imagen lunar se mide en su petición independiente y deja el resultado en `error_log`.

En las secciones con swipe, el panel táctil requiere además `mobile_swipe_navigation_debug_enabled => true`. Sólo entonces evita la navegación automática hasta pulsar **Ir al destino detectado**. Timings puede permanecer activo sin ese panel; la opción de diagnóstico táctil no debe permanecer activa para visitantes.

Las claves numéricas se validan así: intervalo 5–60 minutos, Superluna 90–120% y aviso de salida 1–1440 minutos. Las opciones móviles aceptan `true/false`, `1/0`, `yes/no` y `on/off`. La variable de entorno correspondiente tiene prioridad sobre este archivo; la tabla completa está en [configuracion.md](configuracion.md).

Analytics no usa este archivo: `includes/analytics.php` carga siempre `G-GFZJ3D3MF3` en las nueve vistas públicas. No hay una bandera separada por entorno.

El `.env` local es exclusivo de Docker Compose, está excluido por el script y no reemplaza esta configuración externa de producción.

## Script

El script está en `/srv/proyectos/astronomia/web/scripts/desplegar.sh` y usa `lftp`. Valida:

- ejecución desde `/srv/proyectos/astronomia/web`;
- presencia de `lftp`;
- presencia de `ASTRONOMY_FTP_PASSWORD`;
- existencia de `index.php` y `sol-y-luna.php`.

La contraseña se entrega a `lftp` mediante `LFTP_PASSWORD` y `open --env-password`; no está guardada ni se pasa en la línea de comandos.

La conexión usa FTP en puerto 21 con:

- TLS explícito obligatorio;
- autenticación TLS;
- canal de datos protegido;
- validación del certificado;
- validación del hostname.

La sincronización usa `mirror --reverse --verbose`. Actualiza archivos modificados y no incluye `--delete`, por lo que nunca elimina archivos remotos.

Los mapas mundiales de eclipses bajo `assets/images/eclipses/` son assets generados o descargados, no archivos fuente versionados. `.gitignore` conserva fuera de Git los GIF de esa carpeta, pero el script de despliegue no la excluye: `lftp` los transfiere junto con el resto de `assets/images/`. La ausencia de `--delete` también impide que un despliegue borre mapas ya existentes en el hosting.

Los GIF deben ser legibles por Apache. En local se normalizan a `0644`; con `0600` la ruta correcta responde HTTP 403:

```bash
find assets/images/eclipses -maxdepth 1 -type f -name '*.gif' -exec chmod 0644 {} +
```

El proyecto publica `sitemap.xml` dentro de `astro`, pero excluye `robots.txt`. La línea propuesta para el robots de la raíz del dominio se conserva sólo como referencia en `docs/robots-raiz-propuesto.txt` y debe integrarse manualmente fuera de este despliegue.

## Dry run

```bash
cd /srv/proyectos/astronomia/web
export ASTRONOMY_FTP_PASSWORD='contraseña'
./scripts/desplegar.sh --dry-run
unset ASTRONOMY_FTP_PASSWORD
```

El modo prueba se conecta y compara los árboles, pero no transfiere archivos.

## Despliegue real

```bash
cd /srv/proyectos/astronomia/web
export ASTRONOMY_FTP_PASSWORD='contraseña'
./scripts/desplegar.sh
unset ASTRONOMY_FTP_PASSWORD
```

## Exclusiones reales

No se transfieren:

- pruebas locales bajo `tests/`;
- `.git/`, `.github/`, `.vscode/` y `.idea/`;
- `docs/`, `apache/` y `scripts/`;
- `Dockerfile` y variantes;
- `docker-compose.yml`, `.dockerignore` y `.gitignore`;
- `.env` y `.env.*`;
- originales y catálogo bajo `storage/`, y previews locales bajo `assets/images/tienda/previews/`;
- `README.md`, `php.ini` y `pytest.ini`;
- ZIP;
- logs, PID, temporales y respaldos de editor;
- `.cache/`, `cache/`, `__pycache__/`, `coverage/` y bytecode;
- metadatos de macOS y Windows.

Sí se transfieren:

- `index.php`, `cielo-de-esta-noche.php`, `sol-y-luna.php`, `eventos.php`, `planificador.php`, `galeria.php`, `acerca.php`, `acerca-del-sitio.php`, `moon-image.php`, `altitude-profile.php`, `astronomy-directions.php`, `astronomy-featured-dates.php` y `sitemap.xml`;
- `includes/`;
- `assets/css/` y `assets/js/`;
- las 404 imágenes de `assets/images/moon-phases/`;
- los mapas disponibles de `assets/images/eclipses/`, aunque estén ignorados por Git;
- `.htaccess`, que bloquea por HTTP `includes/`, `scripts/` y las pruebas PHP/shell sin impedir el HTML de prueba táctil local.

Después del despliegue, verificar cada mapa usando exactamente el nombre local informado por la API (sin rutas):

```bash
test -f "assets/images/eclipses/<local_filename>"
curl -fsSI "https://aquellaslunas.com.ar/astro/assets/images/eclipses/<local_filename>"
```

El primer comando confirma el archivo en el árbol que se desplegó y el segundo debe devolver una respuesta HTTP satisfactoria desde `/astro/assets/images/eclipses/`. No usar `source_url` para esta comprobación ni como URL pública de la imagen.

El archivo real `/home8/aquellaslunascom/config/astronomia.php` no forma parte del repositorio ni del despliegue.

## Verificación posterior

Comprobar:

```text
https://aquellaslunas.com.ar/astro/
https://aquellaslunas.com.ar/astro/sol-y-luna.php
https://aquellaslunas.com.ar/astro/cielo-de-esta-noche.php
https://aquellaslunas.com.ar/astro/eventos.php
https://aquellaslunas.com.ar/astro/planificador.php
https://aquellaslunas.com.ar/astro/eclipses.php
https://aquellaslunas.com.ar/astro/ubicacion.php
https://aquellaslunas.com.ar/astro/galeria.php
https://aquellaslunas.com.ar/astro/acerca-del-sitio.php
```

Revisar:

- HTTP 200 y datos reales;
- directivas `no-store/no-cache` en las vistas dinámicas;
- carga de CSS y JavaScript bajo `/astro/assets/`;
- favicon SVG/ICO/PNG y Apple Touch Icon con query `v=<filemtime>`;
- etiqueta de Analytics `G-GFZJ3D3MF3` en las nueve vistas públicas;
- imagen lunar aparente mediante `moon-image.php`;
- perfiles solar y lunar mediante dos solicitudes independientes a `/astro/altitude-profile.php`, con tres series, `Cache-Control: no-store` y sin URL interna de API en el HTML/JSON;
- miniaturas estáticas;
- tabla sin scroll horizontal;
- popover de Cielo;
- textos humanos coherentes entre portada y Eventos;
- botón y popover accesible **Datos técnicos** en las tarjetas de Eventos;
- eventos `full_moon_observation` en “Lo próximo” cuando correspondan, usando la ventana máxima de 70 minutos;
- presentación **Superluna** únicamente para `moon_phase/full_moon` que alcance el umbral, sin evento duplicado y con porcentaje real en datos técnicos;
- próxima salida lunar con estilos diferenciados y pulso eliminado bajo `prefers-reduced-motion`;
- encabezado con “Una Luna diferente cada noche”, menú activo y Acerca del sitio accesible;
- swipe móvil Inicio ⇄ Sol y Luna ⇄ Planificador ⇄ Eventos, sin circularidad;
- modal de Eclipses con “Desde tu ubicación” separado de “Visibilidad mundial”;
- mapa local servido desde `/astro/assets/images/eclipses/<local_filename>`, nunca desde `source_url`;
- conservación literal de `debug_now` al navegar por menú, enlaces y botón de diagnóstico;
- configuración móvil deshabilitada: sin `mobile-swipe-navigation.js` ni atributos de destinos;
- configuración de tienda válida, comprobada desde PHP sin imprimir las rutas; originales legible y previews/catálogo legibles y escribibles;
- Galería con sólo fotos disponibles y preview, orden descendente, títulos opcionales, precio/moneda y modal responsive;
- con timings deshabilitados, abrir `index.php?debug_now=2026-07-29T18:15:00-03:00` y comprobar que no aparece la barra de simulación ni cambia la hora mostrada;
- `error_log` de cPanel sin errores de configuración, cURL, TLS o JSON.

Para comprobar directamente el proxy, usar una fecha y ubicación válidas:

```bash
curl -i 'https://aquellaslunas.com.ar/astro/altitude-profile.php?target=sun&date=2026-07-21&latitude=-34.53&longitude=-58.48&timezone=America%2FArgentina%2FBuenos_Aires'
```

Debe responder JSON, tres roles solares, el intervalo efectivo y `Cache-Control: no-store`; no debe aparecer la URL base de la API. Repetir con `target=moon`.

El hosting no requiere reconstrucción de contenedor: producción no usa Docker. Antes de transferir, validar localmente y ejecutar `./scripts/desplegar.sh --dry-run`; luego ejecutar el mismo script sin `--dry-run`. El mirror actualiza archivos modificados sin borrar archivos remotos y excluye `.env`, documentación y artefactos locales.

Para probar la recuperación sin borrar datos del navegador, interrumpir temporalmente la conectividad entre la web y la API, abrir una página y comprobar el aviso con **Reintentar**. Después de restaurar la API, el botón debe recuperar los datos. En móvil, dejar la pestaña fallida oculta al menos cinco segundos y volver a ella: debe hacer un único intento controlado. Confirmar además que CSS, JavaScript, iconos y miniaturas estáticas no reciben las directivas `no-store` de las páginas HTML.

El hosting usa PHP 8.5 sin garantía de mbstring. La versión desplegada debe incluir `includes/presentation.php` con su fallback UTF-8 e `includes/api-client.php` sin `curl_close()`.

El intervalo de los perfiles de altura se configura con `altitude_profile_interval_minutes` en el arreglo externo de producción. Es opcional, vale 15 de forma predeterminada y admite enteros entre 5 y 60.

El umbral de superluna se configura con `supermoon_min_apparent_size_percent` en el mismo arreglo. Es opcional, vale 105 de forma predeterminada y admite números entre 90 y 120. La variable de entorno `SUPERMOON_MIN_APPARENT_SIZE_PERCENT`, cuando existe, tiene prioridad.

La anticipación del aviso de salida lunar se configura con `moonrise_notice_max_minutes`. Es opcional, vale 120 minutos de forma predeterminada y admite enteros entre 1 y 1440. La variable `MOONRISE_NOTICE_MAX_MINUTES`, cuando existe, tiene prioridad.

La navegación táctil, su aviso inicial y el panel se configuran por separado mediante `mobile_swipe_navigation_enabled`, `mobile_swipe_navigation_hint_enabled` y `mobile_swipe_navigation_debug_enabled`. Sus valores predeterminados son `true`, `true` y `false`; las variables de entorno equivalentes tienen prioridad. Con la navegación deshabilitada no se publica contexto ni se carga el script. El panel requiere además timings activos.

CSS, JavaScript y miniaturas lunares locales se publican con URLs versionadas por `includes/asset-url.php` a partir de `filemtime()`. El despliegue debe incluir ese helper; no hace falta desactivar la caché de estáticos ni purgarla en cada publicación, porque la URL cambia junto con el archivo.

Los favicon usan el mismo helper. Las páginas dinámicas principales mantienen `no-store`; `altitude-profile.php` también lo usa en éxito y error. Analytics se sirve desde Google y no hereda estas cabeceras.
