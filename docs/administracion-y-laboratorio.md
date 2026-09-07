# Administración y Laboratorio Astronómico

## Alcance

Este documento describe la arquitectura actual del área privada de Aquellas Lunas y
del Laboratorio Astronómico. El área administrativa no forma parte de la navegación
pública, no se incluye en el sitemap y comparte una única autenticación. El
laboratorio es una herramienta de consulta: lee la tabla MySQL
`datos_astronomicos`, pero no modifica ni precalcula sus datos.

## Área administrativa

### Presentación del sitio: tipos de eventos

`/admin/presentacion/` administra la primera capa de presentación editorial de los
eventos astronómicos. El catálogo se muestra por familias comprensibles (fases,
órbita, libraciones, conjunciones, eclipses y observaciones derivadas) y permite
editar el nombre amigable, la habilitación global, las superficies públicas en
las que aparece cada tipo y su relevancia para “El cielo esta noche”. Los
identificadores técnicos y el renderer no se
exponen como campos editables.

La configuración vive en MySQL en `admin_tipos_eventos` y
`admin_tipos_eventos_superficies`. La inicialización es idempotente: agrega el
catálogo conocido y sus superficies iniciales, pero nunca pisa cambios
editoriales ya guardados. Las superficies forman un catálogo cerrado: Lo
próximo, Próximas fases, El cielo hoy, El cielo esta noche, Eventos lunares y
Eclipses. La prioridad de las listas continúa siendo cronológica. La selección
resumida nocturna permite administrar cupos y orden de categorías sin exponer
prioridades numéricas ni reordenar efemérides.

La web aplica la habilitación global y por superficie mediante
`includes/event-type-configuration.php`. Si MySQL no está disponible, usa el
catálogo incorporado que reproduce el comportamiento anterior. Un tipo
desconocido no se publica y se registra en el log. La configuración sólo controla
visibilidad y presentación: la API y PostgreSQL siguen siendo la fuente técnica,
y ningún cálculo ni evento astronómico se duplica en MySQL.

La segunda área, `/admin/presentacion/reglas.php`, administra reglas y mensajes
por contexto. Umbral y texto aparecen juntos en cada bloque, con unidades y
límites definidos por el catálogo PHP. Los mensajes admiten únicamente los
placeholders indicados junto al campo y muestran una vista previa con datos
ficticios. Cada bloque puede restaurarse, con confirmación, eliminando sus
overrides para volver a los defaults ejecutables.

La precedencia se muestra en el orden real pero permanece fija: no se ofrece un
constructor AND/OR ni movimiento de condiciones porque los compositores de Luna,
noche y eclipses combinan estados técnicos conocidos. La configuración de
nubosidad llega al navegador como JSON validado y escapado; el resto se consume
por helpers PHP comunes.

Las familias de tipos y los bloques de reglas arrancan contraídos y admiten varias secciones abiertas simultáneamente. La grilla usa columnas automáticas según el ancho disponible. En Reglas y mensajes, el buscador localiza texto efectivo, defaults, etiquetas y placeholders sin requests; abre los bloques coincidentes, resalta tarjeta y campo, y restaura el estado anterior al limpiar.

### Estructura de `/admin`

| Ruta | Responsabilidad |
|---|---|
| `/admin/` y `/admin/index.php` | Panel general y punto de entrada autenticado. |
| `/admin/login.php` | Formulario y validación de las credenciales existentes. |
| `/admin/logout.php` | Cierre de sesión por `POST` con CSRF. |
| `/admin/fotos.php` | Administración de la galería y tienda. |
| `/admin/laboratorio-astronomico.php` | Interfaz del Laboratorio Astronómico. |
| `/admin/contenidos/` y `/admin/contenidos/index.php` | Editor administrativo de artículos, trivias y bloques “Sabías que...”. |
| `/admin/configuracion-sitio/` | Visibilidad de secciones públicas. |
| `/admin/presentacion/` | Nombres, habilitación y superficies de tipos de eventos. |
| `/admin/presentacion/reglas.php` | Parámetros, precedencias informadas y mensajes editoriales. |
| `/admin/api/datos-astronomicos.php` | Endpoint JSON privado del laboratorio. |

`admin/index.php` no redirige a una herramienta concreta: presenta las áreas disponibles. Una nueva herramienta debe agregarse al
panel y a la navegación común sólo si corresponde que sea accesible desde todo el
administrador.

Todas las páginas privadas envían cabeceras `no-store` y
`X-Robots-Tag: noindex, nofollow, noarchive`. Las páginas HTML agregan además la
directiva meta equivalente. No deben agregarse enlaces desde menús públicos,
listados públicos ni `sitemap.xml`.

### Autenticación y flujo de login

La implementación histórica se conserva en `includes/store-admin-auth.php`; sus
nombres `storeAdmin*` y `store_admin_*` no implican una autenticación exclusiva de
la tienda.

- Nombre de sesión PHP: `aquellas_lunas_admin`.
- Indicador autenticado: `$_SESSION['store_admin_authenticated']`, expuesto como
  `STORE_ADMIN_SESSION_KEY`.
- Credenciales: `STORE_ADMIN_USER` y `STORE_ADMIN_PASSWORD_HASH`, o sus claves
  equivalentes en la configuración externa de producción.
- Usuario: comparación constante mediante `hash_equals()`.
- Contraseña: `password_verify()` contra el hash configurado.
- Cookie: ruta `/`, `HttpOnly`, `SameSite=Lax`, `Secure` bajo HTTPS y duración de
  sesión.
- Login correcto: regenera el ID de sesión, establece el indicador autenticado y
  redirige con HTTP 303 a `admin/index.php`.
- Usuario autenticado que abre el login: vuelve al panel.
- Acceso HTML no autorizado: `requireStoreAdminAuthentication()` redirige a
  `login.php`.
- Acceso al endpoint sin sesión: responde JSON con HTTP 401; no redirige ni entrega
  HTML.
- Logout: sólo `POST`, valida CSRF, vacía y destruye sesión y cookie.

El sistema actual no conserva una URL de retorno arbitraria después del login. No
debe incorporarse otro usuario, otra cookie o un middleware paralelo para una nueva
herramienta.

### Navegación y componentes compartidos

`includes/store-admin-navigation.php` genera el encabezado común mediante
`renderStoreAdminNavigation($activeSection, $title)`. Los títulos de las tarjetas de Inicio y los encabezados de los módulos usan literalmente los nombres del menú, que incluyen Inicio, Contenidos, Visibilidad de secciones, Visibilidad de eventos, Fuentes astronómicas, Galería, Laboratorio y Notificaciones de prueba. El formulario seguro para cerrar sesión queda fuera del menú; aplica
`aria-current="page"` y la clase activa correspondiente.

La navegación resuelve enlaces válidos en todo `/admin` a partir de la ruta actual
del script, en lugar de depender de concatenaciones manuales de prefijos relativos.
La ruta canónica del editor es `/admin/contenidos/`.

El componente es adaptable a pantallas angostas y evita duplicar el HTML y el token
CSRF.

Las vistas administrativas cargan el mismo favicon SVG/ICO/PNG y Apple Touch
Icon que el sitio público mediante `includes/favicon-links.php`.

### Fuentes astronómicas

`/admin/fuentes-astronomicas/` persiste en `admin_configuracion_sitio` la fuente
primaria de cada cálculo general y la selección independiente de cada grupo de
eventos. La pantalla sólo ofrece valores soportados: API/PHP para los seis
cálculos portables, colección/API para `moon/image` y
API/database/PHP/auto/compare para los eventos configurables.

Los botones globales modifican únicamente los selectores en el navegador. No
escriben al pulsarlos: el formulario y su CSRF deben guardarse normalmente. Si
una funcionalidad no admite la fuente global solicitada —PHP en `moon/image`,
por ejemplo— conserva su selección y la interfaz lo informa.

Los componentes principales que debe reutilizar una herramienta administrativa son:

- `includes/store-admin-auth.php`: sesión, cabeceras, autenticación, CSRF y logout.
- `includes/store-admin-navigation.php`: encabezado y navegación.
- `includes/api-config.php`: lectura validada de `.env` y configuración externa.
- `includes/web-database.php`: conexión PDO a `WEB_DB`.
- `assets/css/styles.css`: sistema visual oscuro y controles administrativos.

Una página nueva debe iniciar la sesión, exigir autenticación antes de emitir
contenido, enviar las cabeceras privadas, usar la navegación común, escapar toda
salida HTML y emplear botones y controles estilizados. Un endpoint nuevo debe
responder errores JSON genéricos, desactivar `display_errors`, registrar únicamente
el tipo técnico necesario y no exponer rutas, DSN ni credenciales.

### Módulos administrativos actuales

- Fotos (`/admin/fotos.php`): gestión de disponibilidad, precio y metadatos editoriales
  de galería/tienda.
- Laboratorio astronómico (`/admin/laboratorio-astronomico.php`): análisis de series
  y extremos sobre `datos_astronomicos` en modo sólo consulta.
- Contenidos (`/admin/contenidos/`): edición estructurada de artículos editoriales en
  MySQL con validación y persistencia transaccional.
- Visibilidad de secciones (`/admin/configuracion-sitio/`): publicación de bloques y rutas públicas mediante claves cerradas.
- Visibilidad de eventos (`/admin/presentacion/`): nombres amigables, habilitación, superficies y relevancia nocturna.
- Reglas y mensajes (`/admin/presentacion/reglas.php`): umbrales y textos efectivos con defaults en código y overrides MySQL.

## Inventario administrativo completo (2026-09-07)

La tabla incluye pantallas enlazadas, rutas secundarias y endpoints internos. El
estado describe el árbol local; no confirma que esa versión esté desplegada.

| Área admin | Ruta | Función | Qué puede configurarse/modificarse | Persistencia | Estado |
|---|---|---|---|---|---|
| Acceso | `/admin/login.php` | autenticar al operador | sesión a partir de usuario/hash externos | sesión PHP | OPERATIVO |
| Salida | `/admin/logout.php` | cerrar sesión | destruye sesión/cookie; sólo POST+CSRF | sesión PHP | OPERATIVO |
| Panel | `/admin/` | índice y resumen operativo | no modifica; enlaza áreas y muestra avisos | sólo lectura | OPERATIVO |
| Menú y secciones | `/admin/configuracion-sitio/` | navegación y flags públicos | grupos, orden, visibilidad pública/admin, módulos de Inicio, trazabilidad y plantillas/aviso de eclipses | `site_menu_*`, `admin_configuracion_sitio` | OPERATIVO |
| Luna de portada | `/admin/luna-portada/` | apariencia Three.js | tres scopes independientes: Inicio, fecha favorita e interactiva | `admin_configuracion_sitio` | OPERATIVO |
| Widgets lunares | `/admin/widgets-lunares/` | generar/probar URL e iframe | opciones de nueve widgets, presets de escena lunar, previews y exportación WebM de libración | URL + `localStorage`; presets en `lunar_scene_presets` | HERRAMIENTA |
| API de presets lunares | `/admin/api/lunar-scene-presets.php` | gestionar presets del generador | listar, crear, actualizar, duplicar y eliminar presets validados | `lunar_scene_presets` | ENDPOINT INTERNO |
| Fotografía | `/admin/fotografia/` | catálogo y simulación | escenas, variantes, referencias fotográficas y calibración visual | `photography_*`, `admin_configuracion_sitio` | OPERATIVO |
| Visibilidad de eventos | `/admin/presentacion/` | catálogo editorial de eventos | nombre, activación global, superficies y relevancia nocturna | `admin_tipos_eventos*` | OPERATIVO |
| Reglas y mensajes | `/admin/presentacion/reglas.php` | umbrales/textos | parámetros numéricos y plantillas por contexto; restauración por bloque | `admin_parametros_editoriales`, `admin_textos_editoriales` | OPERATIVO |
| Fuentes astronómicas | `/admin/fuentes-astronomicas/` | selección de proveedores | fuente por contrato general y grupo de eventos | `admin_configuracion_sitio` | OPERATIVO |
| Trazabilidad | `/admin/trazabilidad-astronomica.php` | auditar consultas | filtros, detalle y limpieza por días de retención | `astronomy_request_log` | OPERATIVO |
| Galería | `/admin/fotos.php` | fotos/tienda | disponibilidad, precio individual/lote, título, descripción y palabras clave | `STORE_DB.fotos` | OPERATIVO |
| Contenidos | `/admin/contenidos/` | edición editorial | artículos, slugs, Markdown, imagen, visibilidad, relaciones, palabras, trivias y “Sabías que”; import/export de revisión/paquete | tablas `contenido_*` en `WEB_DB` | OPERATIVO |
| Upload local | `/admin/contenidos/upload-photos.php` | incorporar fotografías | hasta 20 originales, sincronización y previews | archivos privados/públicos + `STORE_DB` | SÓLO LOCAL |
| Laboratorio | `/admin/laboratorio-astronomico.php` | analizar series históricas | filtros/representación en sesión de navegador; no edita datos | sólo lectura `datos_astronomicos` | LABORATORIO |
| API laboratorio | `/admin/api/datos-astronomicos.php` | JSON para laboratorio | ninguna escritura | sólo lectura `WEB_DB` | ENDPOINT INTERNO |
| Suscripciones | `/admin/notificaciones-prueba.php` | alta/diagnóstico y Push manual | suscribir este navegador; enviar a uno/todos; buscar por soporte/nombre | `web_push_*` | OPERATIVO |
| Notificaciones | `/admin/notificaciones-astronomicas.php` | operación por dispositivo | identidad, ubicación, habilitación, preferencias, silencio; pruebas inmediatas/programadas/cancelación | `web_push_*` | OPERATIVO |
| Tipos Push | `/admin/tipos-notificaciones.php` | catálogo global | disponibilidad, admin-only, nombre, descripción, plantillas, URL, modo/anticipación/hora/desfase, DND y orden | `web_push_notification_types` | RUTA SECUNDARIA OPERATIVA |
| API de notificaciones | `/admin/api/notificaciones.php` | actualización/acciones asíncronas | prueba programada, cancelación y Push manual; lee scheduler/logs | `web_push_*`, estado scheduler | ENDPOINT INTERNO |
| API de suscripciones | `/admin/api/suscripciones.php` | salud/búsqueda asíncrona | no escribe; filtra y clasifica dispositivos | sólo lectura `web_push_*` | ENDPOINT INTERNO |

`editor-core.php`, los CSS/JS de Contenidos/Fotografía y
`partials/notificaciones-operativas.php` son componentes, no rutas autónomas. Las
rutas secundarias Tipos Push y Reglas se alcanzan desde su módulo padre aunque no
sean tarjetas del panel.

## Qué configura cada área y cuándo surte efecto

### Menú, secciones, Inicio y eclipses

`/admin/configuracion-sitio/` permite crear grupos, cambiar su nombre/orden y
eliminarlos sólo cuando están vacíos. Para todas las secciones salvo Inicio —que
permanece primera— asigna grupo, orden y visibilidad pública/administrativa. La
visibilidad de menú no es control de acceso: una URL puede seguir siendo accesible.
Persiste en `site_menu_groups` y `site_menu_sections`; efecto en la próxima carga.

En la misma pantalla se activan/desactivan Contenidos, trazabilidad y tarjetas de
Inicio: El cielo hoy, Esta noche, Próximas fases, Lo próximo, cálculo de tránsitos
satelitales, Explorá el cielo, Trivia, Sabías que e instalación. Guarda en
`admin_configuracion_sitio`; efecto en próxima carga. Los defaults están en
`includes/site-configuration.php` y se usan si DB no está disponible, pero esta
pantalla no ofrece restauración global de flags.

El bloque Eclipses controla aviso de próximos eclipses, anticipación entre 1 y 90
días y URLs plantilla de widgets solar/lunar reales. Conserva los parámetros
visuales de la plantilla y reemplaza fecha/coordenadas/elevación por observador.
Efecto en próxima carga; una URL inválida puede suprimir o degradar el widget. Las
plantillas se sembraron mediante migración, pero se editan en DB, sin redespliegue.

### Apariencia lunar y widgets

`/admin/luna-portada/` edita intensidad solar/ambiente, relieve, resolución DEM,
bump/normal scale, contraste, brillo, gamma, saturación, exposición y tamaño.
Fecha favorita agrega fondo/degradado y resplandor; Luna interactiva agrega normal
8K progresivo y atenuación de relieve cerca del terminador. Cada scope posee
validación cerrada y persistencia independiente bajo prefijos `home.moon_three.*`,
`favorite.moon_three.*` e `interactive.moon_three.*`. Efecto en próxima carga; no
reescribe texturas ni catálogos. No se observó botón de restauración en la UI.

`/admin/widgets-lunares/` genera variantes de Libración, escena lunar configurable,
Luna interactiva, fases Tierra–Luna, tamaños de las próximas doce lunas llenas,
eclipse lunar simple/real, solar real y solar desde el espacio.
Controla fecha/coordenadas de prueba, reproducción, velocidad, zoom/capas,
controles y parámetros visuales permitidos. La URL sigue siendo el estado
reproducible de cada embed y la última edición se recuerda en `localStorage`.
La excepción son los presets de escena lunar: nombre, descripción y configuración
URL validada se pueden crear, actualizar, duplicar o eliminar mediante el endpoint
administrativo autenticado con CSRF y persisten en `lunar_scene_presets`. Un preset
no publica un widget ni cambia la ubicación global; sólo repuebla el generador.

### Presentación y reglas editoriales

`/admin/presentacion/` inicializa un catálogo cerrado, permite nombre visible,
habilitación global, superficies (Lo próximo, Próximas fases, El cielo hoy, Esta
noche, Eventos y Eclipses) y relevancia/cupos nocturnos. Persiste en
`admin_tipos_eventos` y `admin_tipos_eventos_superficies`. Efecto en próxima carga;
no crea, borra ni recalcula efemérides. Catálogo/defaults PHP cubren fallas de DB.

`/admin/presentacion/reglas.php` agrupa parámetros y textos para fase/superluna,
salida lunar, nubosidad, partes del día, Esta noche, observación de Luna llena,
eclipses y resúmenes Sol/Luna. Valida rangos y relaciones entre umbrales; los
textos sólo aceptan placeholders declarados. Guardar un valor igual al default
elimina el override. “Restaurar bloque” borra todos sus overrides y recupera código
en próxima carga. No modifica cálculos astronómicos.

### Fuentes astronómicas

Los contratos generales `daily`, `range`, `directions`, `moon_instant`,
`altitude_profile` y `tonight` aceptan API/PHP; default PHP. `moon_image` acepta
estática/API; default estática. Grupos `moon_phase`, `lunar_apsis`, `lunar_orbit`,
`lunar_libration`, `lunar_conjunction` y `eclipse` aceptan API, database, PHP,
auto y compare; fallback sin override es la variable de entorno y después API.
Guardar escribe `astronomy.data_source.*` y `astronomy.event_source.*` en
`admin_configuracion_sitio`, con efecto en las siguientes solicitudes. Puede
introducir dependencia de FastAPI o mayor costo de comparación. No cambia URLs,
credenciales, cobertura DB ni algoritmos. Los botones globales no persisten hasta
guardar el formulario con CSRF.

### Fotografía

El modelo editorial modifica escenas y variantes: activación, título, descripción,
orden, imagen/nota editorial; variantes además focal, apertura, velocidad, ISO,
viabilidad/nota de celular. Persiste en `photography_scenes` y
`photography_scene_variants`; efecto en próxima carga. Importación JSON puede
fusionar o reemplazar todo; reemplazo exige confirmación y es el mayor riesgo del
módulo. Exportar no modifica datos.

La calibración del modo Simulado cambia colores de cielo/horizonte, umbrales
solar/lunar/atmosféricos, intensidad direccional, estrellas, halo, bruma/extinción
y perfiles lunares por altura/parte del día. Persiste con claves
`photography.simulated.*` en `admin_configuracion_sitio`; vista previa inmediata en
el iframe y efecto público guardado en próxima carga. La focal de preview no se
guarda. Defaults/rangos provienen del catálogo PHP; no se observó restauración
global en UI.

### Contenidos y fotos

Contenidos ofrece listado, creación y edición, importación completa sin
sobrescritura, exportación/importación limitada de revisión y selección de imagen.
El guardado transaccional modifica las seis tablas `contenido_*`; visibilidad
afecta próxima carga, búsqueda y sitemap dinámico. El slug publicado es una URL
estable: editarlo rompe enlaces porque no existe historial/redirección. Relaciones
son dirigidas; destinos inválidos/no visibles se filtran públicamente. Embeds sólo
mediante directiva/allowlist; HTML libre se rechaza. El upload de originales sólo
existe en `APP_ENV=local`, exige sesión/CSRF, valida cantidad/formato/destino sin
symlink, sincroniza `STORE_DB` y genera ambas previews.

Galería modifica en `STORE_DB.fotos` publicación, precio individual o por lote
con moneda común, y metadatos editoriales. Efecto en próxima carga de galería. No
hay pantalla administrativa para pedidos, pagos, descargas, reembolsos ni entrega;
Mercado Pago y almacenamiento se configuran fuera del admin.

### Web Push, dispositivos y scheduler

Suscripciones permite registrar el navegador del administrador, inspeccionar
endpoint/estado/fechas/user-agent de dispositivos, buscar por ID público de soporte
o nombre y enviar título/mensaje/URL inmediatamente a una suscripción o todas las
activas. Requiere VAPID, requisitos PHP y `vendor/autoload.php`; esas credenciales
y dependencias no son editables desde UI. Los envíos escriben log y pueden marcar
suscripciones expiradas según el resultado.

Notificaciones astronómicas edita por suscripción: nombre del dispositivo,
habilitación general, nombre/coordenadas/zona de ubicación, No molestar y horas,
y preferencias disponibles. Puede enviar una prueba manual inmediata o programar
una prueba con demora/respecto de silencio y cancelarla mientras esté pendiente.
La configuración tiene efecto inmediato en DB; avisos y pruebas programadas se
procesan en la próxima ejecución de `run-scheduled-tasks.php`.

Tipos Push edita `moonrise`, `eclipse`, `lunar_conjunction`,
`satellite_transit` y `test`: nombre/descripción, disponibilidad, admin-only,
plantillas y URL con placeholders controlados, modo antes del evento u hora fija,
anticipación 0–1440 min, hora, offset -366..366, política DND y orden. No permite
cambiar el código técnico. Desactivar globalmente no borra preferencias; efecto en
el próximo cálculo/scheduler. Una plantilla inválida se rechaza.

El panel lee última ejecución/éxito/error, lock omitido, última migración y
pendientes, próximos avisos, pruebas e historial. Los endpoints asíncronos refrescan
salud/logs y repiten acciones con sesión+CSRF. No hay botón para ejecutar cron,
aplicar manualmente migraciones, refrescar TLE o reiniciar servicios.

### Trazabilidad, laboratorio y herramientas técnicas

Trazabilidad filtra por fechas, operación, estado, request/session ID y fuente;
muestra tiempos, coordenadas, payload/resultado y error técnico. POST permite borrar
registros anteriores a una retención validada. La captura depende del flag
`astronomy.trace.enabled`; la limpieza es inmediata y destructiva para esos logs.

El Laboratorio y su endpoint son sólo consulta de `datos_astronomicos`. Los
controles cambian requests/gráficos en navegador, no configuración productiva. Las
herramientas públicas de debug (`debug_now`, timings, pruebas visuales) se autorizan
por `canUseSiteDebugTools()` o equivalentes: entorno local o sesión administrativa
según la función. No deben confundirse con settings persistidos.

## Qué configura realmente la administración

### `WEB_DB`

- menú/grupos y flags de sitio;
- fuentes generales y de eventos;
- tipos/superficies, parámetros y textos editoriales;
- apariencias Three.js y calibración fotográfica;
- presets administrativos de escena lunar;
- escenas/variantes fotográficas;
- artículos, relaciones, palabras, trivias y “Sabías que”;
- suscripciones, dispositivos, preferencias, tipos, pruebas y logs Push;
- trazabilidad, estado del scheduler/migraciones y datos del laboratorio.

### `STORE_DB`

La UI sólo edita `fotos`: disponibilidad, precio y metadatos. Pedidos, pagos y
descargas existen en el sistema de tienda, pero no tienen gestión administrativa
en estas pantallas.

### Archivos y estado local

Texturas/catálogos lunares, JSON de `content-data`, assets, código y defaults no se
editan desde admin. Widgets usa `localStorage`; upload de fotos local escribe
originales privados y previews mediante rutas configuradas. Cambiar esos archivos
requiere proceso offline o despliegue.

En particular, **Fuentes y créditos** se edita en
`content-data/fuentes-y-creditos.json`, no desde una pantalla administrativa. La
migración `sources-credits-menu` sólo incorpora su entrada al menú; admin puede
cambiar visibilidad/grupo/orden, no el contenido ni sus asociaciones. Los catálogos
de Luna interactiva y alunizajes tampoco son editables desde UI. ISS/Tiangong sólo
ofrece visibilidad de menú y el flag de cálculo en Inicio: TLE, TTL, caché,
actualización y parámetros SGP4 no tienen controles administrativos.

### Configuración privada y entorno

DB hosts/usuarios/contraseñas, credenciales admin, VAPID, Mercado Pago, rutas de
tienda y URL privada FastAPI viven en `.env` local o
`/home8/aquellaslunascom/config/astronomia.php`. Variables de entorno pueden fijar
fuentes de eventos y opciones operativas. No llegan como valores editables al admin.

### Cloudflare, cPanel y servicios

DNS, TLS, WAF, cache, Tunnel/Access, cron, versión/extensiones PHP, permisos,
Composer, FTPS y estado de FastAPI/PostgreSQL/CelesTrak quedan fuera del admin.
Requieren panel externo, configuración, CLI, reinicio o despliegue según el caso.
GA4, canonical, robots, sitemap, metadata social y reglas `noindex` tampoco tienen
editor administrativo: viven en código/datos y requieren cambio versionado y
despliegue.

## Seguridad administrativa

Las rutas privadas HTML inician `aquellas_lunas_admin`, exigen autenticación y
emiten no-cache/noindex; `login.php` es la excepción pública necesaria y un usuario
ya autenticado vuelve al panel. Usuario y hash provienen de configuración externa; se usa
`hash_equals`, `password_verify` y regeneración de ID. Cookie: sesión, ruta `/`,
HttpOnly, SameSite=Lax y Secure bajo HTTPS. Logout y escrituras validan CSRF. Los
endpoints devuelven 401 JSON sin sesión, validan método/IDs/listas blancas y no
renderizan excepciones sensibles; logs públicos se sanitizan.

No hay roles, auditoría de autor por cambio, segundo factor ni restricción general
por IP observados. La seguridad depende de una única credencial administrativa y
de proteger configuración/sesiones del hosting. `canUseSiteDebugTools()` no concede
acceso al admin: habilita herramientas técnicas locales o para una sesión ya válida.
El lector de cookie de debug consulta archivos de sesión del servidor; por ello
permisos correctos del directorio de sesiones son relevantes. Estas limitaciones se
registran como deuda/riesgo, no se corrigen en esta tarea.

## Editor de contenidos

### Fuente de datos y alcance

El editor administrativo de contenidos usa MySQL como única fuente y como destino
de escritura.

La lectura y escritura del módulo se realizan sobre tablas de contenido mediante
`getWebDatabaseConnection()`; no se escriben archivos PHP durante el guardado
administrativo.

### Flujo de guardado

El guardado se ejecuta en una única transacción que cubre:

- artículo base (`contenido_articulos`),
- palabras clave (`contenido_articulos_palabras_clave`),
- relaciones (`contenido_articulos_relaciones`),
- trivias (`contenido_trivias`),
- opciones de trivia (`contenido_trivia_opciones`),
- bloques “Sabías que...” (`contenido_sabias_que`).

Ante cualquier falla, la operación revierte completa (`ROLLBACK`) y no persiste un
estado parcial.

### Validaciones implementadas

El editor valida, antes de escribir:

- slug con formato válido y unicidad en base,
- versión numérica válida (>= 1),
- campos obligatorios (`titulo`, `resumen`, `articulo`),
- listas de `palabras_clave` y `relaciones`,
- `trivias` y `sabias_que` como colecciones listadas,
- códigos únicos dentro de cada colección,
- imágenes pertenecientes a la galería permitida,
- exactamente una opción correcta por trivia,
- coherencia entre referencias `[[trivia id="..."]]` en Markdown y trivias reales,
- codificación UTF-8 válida,
- consistencia de orden persistido para colecciones y opciones,
- advertencias de registros huérfanos en tablas relacionadas.

### Relaciones entre artículos, trivias y “Sabías que...”

Cada artículo (`contenido_articulos`) puede tener múltiples:

- trivias (`contenido_trivias`),
- bloques “Sabías que...” (`contenido_sabias_que`),
- palabras clave y relaciones internas por slug.

Cada trivia puede tener múltiples opciones (`contenido_trivia_opciones`) y una sola
opción correcta representada por `correcta = 1` con explicación asociada.

### Importación editorial JSON e importador histórico

La opción **Importar paquete JSON** de `/admin/contenidos/` es el flujo operativo para crear de una vez un artículo, relaciones, palabras clave, trivias, opciones y bloques “Sabías que...”. Valida esquema cerrado, tipos, claves desconocidas, slug, imágenes, referencias y UTF-8 antes de escribir. Primero permite validar y mostrar un resumen; importar exige CSRF, usa una transacción y nunca sobrescribe un slug existente.

La migración inicial desde archivos PHP quedó documentada en
`scripts/migrations/import-content-to-web-db.php`.

Ese script se conserva sólo como historial técnico de la carga inicial:

- valida candidatos desde una carpeta de entrada explícita en tiempo de ejecución,
- inserta en tablas MySQL de contenido,
- soporta `--dry-run` para simulación,
- no sobrescribe artículos existentes por slug.

### Política actual de sobrescritura

La política actual del importador es conservadora: no sobrescribe contenido ya
existente en MySQL y omite el slug duplicado. La actualización de contenido existente
se realiza desde el editor administrativo autenticado.

## Base de datos de contenidos

Las tablas editoriales activas son:

- `contenido_articulos`: tabla raíz por slug, versión, visibilidad, título, resumen,
  markdown e imagen principal.
- `contenido_articulos_palabras_clave`: lista ordenada de palabras clave por artículo.
- `contenido_articulos_relaciones`: lista ordenada de slugs relacionados por artículo.
- `contenido_trivias`: trivias asociadas a un artículo con orden, visibilidad,
  pregunta e imagen opcional.
- `contenido_trivia_opciones`: opciones de cada trivia con orden, bandera de correcta
  y explicación.
- `contenido_sabias_que`: bloques “Sabías que...” asociados a artículo con orden,
  visibilidad, frase y detalle.

Relaciones lógicas:

- `contenido_articulos` 1:N `contenido_trivias`.
- `contenido_trivias` 1:N `contenido_trivia_opciones`.
- `contenido_articulos` 1:N `contenido_sabias_que`.
- `contenido_articulos` 1:N `contenido_articulos_palabras_clave`.
- `contenido_articulos` 1:N `contenido_articulos_relaciones`.

## Herramientas de administrador en el sitio público

Una sesión administrativa válida autoriza en producción las herramientas técnicas ya integradas: simulación temporal, timings y diagnóstico de contenidos, según la bandera específica de cada función. Se comprueba la cookie admin sin mezclarla con `aquellas_lunas_local`; una sesión anónima no hereda el estado del administrador. En local, `APP_ENV=local` autoriza las herramientas y el simulador requiere además su interruptor técnico.

## Entorno local y sesiones

Durante la migración del editor se detectó un conflicto entre la sesión administrativa
(`aquellas_lunas_admin`) y la sesión usada por la simulación temporal local
(`aquellas_lunas_local`).

Causa:

- incluir componentes que cargan `includes/current-datetime.php` antes de iniciar la
  sesión administrativa podía activar la sesión local primero.

Efecto:

- la autenticación administrativa no encontraba `STORE_ADMIN_SESSION_KEY` en la
  sesión activa y trataba al usuario como no autenticado.

Solución aplicada:

- en módulos admin, ejecutar siempre primero
  `startStoreAdminSession()` y `requireStoreAdminAuthentication()`;
- cargar después componentes que puedan iniciar otras sesiones en entorno local.

Regla permanente:

"Ningún módulo administrativo debe incluir componentes que puedan iniciar otra sesión
antes de ejecutar `startStoreAdminSession()` y `requireStoreAdminAuthentication()`."

## Estado administrativo actual

La migración operativa está completa: contenido público y editor usan MySQL; visibilidad de secciones, tipos de eventos y reglas editoriales tienen interfaces autenticadas; la importación JSON está disponible. El script de importación desde PHP queda únicamente como historia de migración. Los pendientes reales generales se mantienen en [estado-actual.md](estado-actual.md).

## Laboratorio Astronómico

### Objetivo y arquitectura

El laboratorio permite explorar series históricas de la tabla
`datos_astronomicos` sin editarla. La página PHP entrega el formulario, el catálogo
de controles y la configuración inicial; JavaScript consulta bajo demanda el
endpoint privado y construye el gráfico.

```text
admin/laboratorio-astronomico.php
              |
              v
assets/js/astronomy-laboratory.js
              |
        GET con sesión
              v
admin/api/datos-astronomicos.php
              |
     includes/web-database.php
              |
      WEB_DB / datos_astronomicos
              |
          JSON validado
              v
       Apache ECharts 6.1
```

La conexión `WEB_DB` usa `WEB_DB_HOST`, `WEB_DB_PORT`, `WEB_DB_NAME`,
`WEB_DB_USER` y `WEB_DB_PASSWORD` —o las claves externas `web_db_*`— mediante PDO,
`utf8mb4`, excepciones, resultados asociativos y consultas preparadas. Esta ruta no
consume la API FastAPI de astronomía.

Responsabilidades:

- `includes/astronomy-laboratory.php`: catálogo central de campos, expresiones SQL,
  tipos, unidades, escalas, agrupación visual, fases permitidas y helpers derivados.
- `includes/astronomy-laboratory-extrema.php`: catálogo reducido y consulta del
  modo Extremos locales.
- `admin/api/datos-astronomicos.php`: autenticación, validación, composición de la
  consulta y contrato JSON.
- `admin/laboratorio-astronomico.php`: interfaz accesible, fechas, variables y
  configuración serializada para JavaScript.
- `assets/js/astronomy-laboratory.js`: estado del formulario, rangos rápidos,
  ECharts, ejes, leyendas, tooltips, zoom y mensajes.
- `assets/css/styles.css`: presentación de ancho amplio, controles, paneles y
  adaptación móvil.

No existe botón **Actualizar gráfico**. Los cambios que sólo afectan representación —Coincidencia/oposición, Refuerzo positivo/negativo y variables A/B— redibujan sin consultar el backend. Fechas, variables y modos que sí cambian la consulta disparan una única actualización automática con debounce y cancelación de la solicitud anterior. El estado solicitado de ambos análisis se conserva cuando temporalmente queda una sola variable; la banda reaparece al volver a dos o más, manteniendo A/B mientras sigan disponibles.

### Contrato del endpoint

El endpoint acepta únicamente `GET` autenticado.

Parámetros comunes:

- `modo`: `diario` por defecto o `extremos`.
- `fecha_desde` y `fecha_hasta`: fechas reales `AAAA-MM-DD`, con fecha desde no
  posterior a fecha hasta. La interfaz ofrece el intervalo disponible 1900–2100.

Serie diaria:

- `campos`: lista separada por comas, validada contra el catálogo central.
- `fases`: lista opcional separada por comas; sólo admite Luna nueva, Cuarto
  creciente, Luna llena y Cuarto menguante.

Extremos:

- `variable`: una entrada del catálogo específico de extremos.
- `tipo_extremo`: `maximo`, `minimo` o `ambos`.

No existe un máximo general de diez años. Los rangos rápidos retrospectivos restan
años a la fecha final; los futuros suman años a la fecha inicial. Ambos ajustan el
29 de febrero y respetan 1900–2100.

Los campos SQL nunca se toman directamente del request. El nombre solicitado debe
existir en la lista blanca y la consulta usa sólo la expresión controlada del
catálogo. Fechas, fases y umbrales se enlazan como parámetros preparados.

Los errores de validación usan HTTP 400; autenticación, 401; método, 405; fallo de
consulta o infraestructura, 503. La respuesta pública nunca incluye la excepción,
rutas internas, usuario, contraseña o DSN.

#### JSON de Serie diaria

```json
{
  "modo": "diario",
  "columns": ["fecha", "distancia_luna_km", "iluminacion_porc"],
  "field_types": {
    "fecha": "date",
    "distancia_luna_km": "number",
    "iluminacion_porc": "number"
  },
  "field_units": {
    "distancia_luna_km": " km",
    "iluminacion_porc": "%"
  },
  "field_scales": {
    "distancia_luna_km": "distance_km",
    "iluminacion_porc": "percentage"
  },
  "scale_groups": {},
  "available_years": {"min": 1900, "max": 2100},
  "rows": [
    {
      "fecha": "2026-01-01",
      "distancia_luna_km": 363124.0,
      "iluminacion_porc": 93.2
    }
  ]
}
```

`scale_groups` contiene la definición completa de cada eje. Si se solicitaron
marcadores de superluna o miniluna, la respuesta agrega `event_thresholds` y cada
fila marcada puede incluir `_event_marker_details`.

#### JSON de Extremos locales

```json
{
  "modo": "extremos",
  "variable": "distancia_luna_km",
  "variable_label": "Distancia de la Luna (km)",
  "field_type": "number",
  "scale_group": "distance_km",
  "unidad": "km",
  "desde": "2020-01-01",
  "hasta": "2025-12-31",
  "tipo_extremo": "ambos",
  "series_labels": {
    "maximos": "Apogeos",
    "minimos": "Perigeos"
  },
  "series": {
    "maximos": [{"fecha": "2020-01-02", "valor": 404579.0}],
    "minimos": [{"fecha": "2020-01-13", "valor": 365958.0}]
  },
  "conteos": {"maximos": 1, "minimos": 1},
  "rango": {},
  "advertencia": null
}
```

### Catálogo central de variables

Cada entrada de `astronomyLaboratoryFieldDefinitions()` usa:

| Clave | Función |
|---|---|
| `label` | Nombre completo en leyenda y tooltip. |
| `short_label` | Nombre compacto del selector. |
| `type` | `number`, `time_fraction`, `time_duration` o `event_marker`. |
| `scale_group` | Grupo de unidad/escala; `null` para marcadores. |
| `unit` | Sufijo visual opcional. |
| `body` | Columna visual `moon` o `sun`. |
| `group` | Sección del selector: horarios, diferencias, amplitudes, azimutes u otras. |
| `sql` | Columna o expresión SQL controlada. |
| `event` | Estrategia especial para un marcador, en lugar de `sql`. |

Variables físicas, leídas directamente de columnas:

- Horas: `hora_salida_luna`, `hora_puesta_luna`, `hora_salida_sol`,
  `hora_puesta_sol`.
- Diferencias: `diferencia_salida_luna_min`, `diferencia_puesta_luna_min`,
  `diferencia_salida_sol_min`, `diferencia_puesta_sol_min`.
- Azimutes: `azimut_salida_luna`, `azimut_puesta_luna`,
  `azimut_salida_sol`, `azimut_puesta_sol`.
- Otras: `distancia_luna_km`, `distancia_sol_km`, `iluminacion_porc`,
  `dia_ciclo_lunar`, `latitud_ecliptica_luna`, `fraccion_anio_tropico` y
  `angulo_nodo_sol`.

Variables derivadas durante la consulta:

- Amplitudes de salida y puesta lunares y solares: azimut menos 90° o 270°.
- `duracion_dia` y `duracion_noche`: diferencias modulares de los horarios
  solares, expresadas como fracción de día.
- `tiempo_luna_sobre_horizonte`: construye timestamps completos; para cada salida
  usa la primera puesta posterior del mismo día o del siguiente. Rechaza ciclos
  incompletos o mayores de 24 horas y conserva `NULL`.
- `superluna_llena` y `miniluna_llena`: marcadores calculados sobre las lunas
  llenas usando los percentiles 10 y 90 de las distancias de 1900–2100.

`hora_fase_lunar` no forma parte del catálogo seleccionable.

### Grupos de ejes, unidades y escalas

No se agrupan todos los números en un mismo eje. Cada serie consume el eje de su
`scale_group`; varias variables del mismo grupo comparten eje. Se permiten como
máximo cuatro grupos simultáneos, sin limitar la cantidad de series dentro de esos
grupos.

| Grupo | Variables típicas | Eje |
|---|---|---|
| `distance_km` | Distancias lunar y solar | Distancia (km), automática |
| `percentage` | Iluminación | Porcentaje (%), datos 0–100 |
| `time_fraction` | Horas de salida y puesta | Hora, 0–1, etiquetas `HH:MM` |
| `time_duration` | Duraciones y permanencia lunar | Duración, 0–1, etiquetas `HH:MM` |
| `angle_degrees` | Azimutes, amplitudes y ángulos | Ángulo (°), automática |
| `difference_minutes` | Diferencias diarias | Diferencia (min), automática |
| `cycle_days` | Día del ciclo lunar | Días, automática |
| `fraction` | Fracción del año trópico | Fracción, datos 0–1 |

Los ejes se crean sólo para los grupos seleccionados y alternan izquierda, derecha,
izquierda con offset y derecha con offset. `grid.left` y `grid.right` crecen según
la cantidad de ejes. Los marcadores de eventos no crean un eje propio cuando ya
existe una escala física.

Los valores numéricos de ejes y tooltips usan formato `es-AR`, separador de miles y
exactamente dos decimales. Las horas y duraciones se mantienen en `HH:MM`; el
redondeo a minutos normaliza el acarreo y evita valores como `18:60`. Los `NULL` se
presentan como “Sin dato” en contextos textuales.

La paleta diaria se asigna en orden:
`#8eb4ff`, `#f2d486`, `#89d6c6`, `#d9a5ff`, `#ff9f9f`, `#9ccf75` y
`#f7b267`. Extremos usa dorado `#f2d486` para máximos y azul `#8eb4ff` para
mínimos. La leyenda es interactiva y desplazable.

El gráfico permite zoom con rueda, desplazamiento, selección rectangular, control
inferior y restauración. No hay actualmente exportación a CSV, JSON, PNG o SVG; el
toolbox expone sólo zoom y restauración.

### Modo Serie diaria

Serie diaria admite selección múltiple de variables y filtro múltiple de fases.
Consulta una fila por fecha, ordenada ascendentemente. Los valores `TIME` de MySQL
se convierten en el endpoint a una fracción entre 0 y 1; los números se entregan
como `float`.

Las expresiones derivadas se calculan en la misma consulta para que el catálogo sea
la única definición funcional. No se duplican fórmulas en JavaScript. En particular,
la permanencia lunar no resta automáticamente salida y puesta de la misma fila:
empareja datetimes cronológicos y puede usar la puesta siguiente.

Un `NULL` permanece `null` en JSON, no se transforma en cero y ECharts no dibuja
ese punto. `connectNulls: false` corta la línea; no se calculan duraciones parciales.
Para series extensas se usa muestreo LTTB, se ocultan símbolos a partir de 100 filas
y se desactiva la animación desde 1500 filas. La resolución fuente sigue siendo
diaria.

### Modo Extremos locales

Este modo busca cambios de tendencia locales en una sola variable compatible. La
consulta usa CTE:

1. `calculada`: obtiene fecha y valor físico o derivado.
2. `base`: excluye `NULL`.
3. `serie`: obtiene fechas y valores anterior y siguiente mediante `LAG()` y
   `LEAD()`.
4. `clasificada`: etiqueta máximos y mínimos.

Un máximo cumple `valor > anterior` y `valor >= siguiente`. Un mínimo cumple
`valor < anterior` y `valor <= siguiente`. Además, ambos vecinos deben estar
exactamente a un día; así un hueco por `NULL` o una fecha ausente no convierte
puntos separados por varios días en vecinos.

Variables compatibles:

| Variable | Mínimo | Recomendado |
|---|---:|---:|
| Distancia lunar | 2 años | 5 años |
| Amplitud de salida lunar | 2 años | 5 años |
| Amplitud de puesta lunar | 2 años | 5 años |
| Tiempo lunar sobre el horizonte | 2 años | 5 años |
| Diferencia diaria de salida lunar | 2 años | 5 años |
| Duración del día | 5 años | 10 años |
| Amplitud de salida solar | 5 años | 10 años |
| Amplitud de puesta solar | 5 años | 10 años |

El endpoint puede devolver máximos, mínimos o ambas series. Distancia lunar usa los
nombres astronómicos Apogeos y Perigeos; las demás variables usan etiquetas
descriptivas. Cada serie tiene color, leyenda y conteo independientes.

Los extremos son extremos de la muestra diaria, no el instante físico interpolado
del fenómeno. No se suaviza la curva ni se infieren puntos entre fechas. Se calculan
bajo demanda porque el volumen 1900–2100 es manejable, las expresiones continúan
centralizadas y no se justifica todavía una tabla materializada que deba
sincronizarse con los datos fuente.

Como referencia local sobre MariaDB 10.11, la distancia lunar tardó alrededor de
68 ms para 5 años, 69 ms para 10, 85 ms para 20 y 168 ms para 1900–2100; una
derivada solar de 10 años rondó 72 ms. Son mediciones orientativas, no límites
contractuales.

## Extensibilidad

### Agregar una variable diaria física

1. Confirmar el nombre, tipo y semántica reales en MySQL.
2. Agregar una entrada a `astronomyLaboratoryFieldDefinitions()` con una columna
   SQL fija, tipo, unidad, escala, cuerpo y grupo.
3. Agregar un grupo de escala sólo si ninguna unidad existente es compatible.
4. Verificar selector, metadatos JSON, eje, tooltip, `NULL` y consulta HTTP.
5. Agregar pruebas PHP, JavaScript y HTTP.

No es necesario modificar manualmente la lista blanca: se deriva del catálogo.

### Agregar una variable derivada

1. Implementar la expresión controlada en `includes/astronomy-laboratory.php`;
   usar un helper cuando la fórmula sea compleja.
2. Conservar `NULL` si faltan entradas; no usar cero como reemplazo.
3. Definir límites físicos o temporales para descartar combinaciones inválidas.
4. Mantener alias de tabla compatibles con `d` y usar consultas acotadas e
   indexables si se requieren filas vecinas.
5. Completar los mismos metadatos y pruebas que una variable física.

La fórmula no debe recibirse desde el cliente ni reimplementarse en ECharts.

### Habilitar una serie de extremos

1. La variable debe existir y tener una expresión `sql` en el catálogo diario.
2. Agregarla a `astronomyLaboratoryExtremaVariables()` con `analysis_label`,
   `minimum_years`, `recommended_years` y, si corresponde, nombres específicos para
   máximos y mínimos.
3. Confirmar que la magnitud no sea circular y que la resolución diaria permita una
   interpretación útil.
4. Probar máximo, mínimo, ambos, rangos cortos, `NULL` y huecos de fechas.

### Agregar un grupo de ejes

1. Incorporarlo en `astronomyLaboratoryScaleGroups()` con etiqueta, límites,
   intervalo y formatter cuando correspondan.
2. Asignar el `scale_group` a las variables compatibles.
3. Actualizar el formatter JavaScript sólo si la unidad necesita una representación
   distinta de número con dos decimales o `HH:MM`.
4. Probar índices, offsets y márgenes con uno a cuatro grupos.

No debe aumentarse el límite de cuatro grupos sin revisar legibilidad y espacio
lateral.

### Agregar un modo de análisis

1. Definir un valor controlado nuevo para `modo` y un contrato JSON propio.
2. Crear un catálogo de variables permitido si no coincide con los actuales.
3. Separar cálculo/consulta en un include, manteniendo el endpoint como
   controlador.
4. Agregar controles dentro del formulario existente y una función de render
   específica en JavaScript.
5. Reutilizar autenticación, fechas, conexión, manejo de errores, metadatos y
   estilos.
6. Documentar resolución, supuestos, rendimiento y limitaciones antes de habilitarlo.

## Validación y pruebas

La cobertura relevante está en:

- `tests/astronomy-laboratory.php`: catálogo, escalas, horarios y expresiones
  derivadas.
- `tests/astronomy-laboratory-extrema.php`: catálogo, rangos, consultas y exclusión
  de valores inválidos.
- `tests/astronomy-laboratory.js`: formatters, fechas, ejes, series y tooltips.
- `tests/store-admin-http.sh`: autenticación, HTML, cabeceras y contrato HTTP/JSON.

Ejecución habitual:

```bash
docker compose exec -T web php tests/astronomy-laboratory.php
docker compose exec -T web php tests/astronomy-laboratory-extrema.php
docker compose exec -T web bash tests/store-admin-http.sh http://localhost
```

## Pendientes del laboratorio

- Evaluar una unión visual opcional de huecos pequeños por `NULL`, con un límite
  explícito de días y sin alterar los datos ni el cálculo de extremos.
- Definir reglas diferentes de conexión de segmentos para variables físicas,
  duraciones y marcadores.
- Comparar dos variables de extremos y resolver escalas, sincronización temporal y
  semántica de máximos contra mínimos.
- Considerar precálculo o materialización sólo si crecen el volumen, la concurrencia
  o el costo medido; definir entonces invalidación y trazabilidad.
- Diseñar exportación explícita de datos filtrados y, por separado, exportación de
  la imagen del gráfico.
- Incorporar diagnósticos opcionales de ciclos lunares emparejados sin exponer SQL
  ni convertirlos en una interfaz de edición.
- Evaluar una URL de retorno segura tras el login si el administrador incorpora más
  puntos de entrada profundos.
- Documentar objetivos de rendimiento y presupuesto de puntos si el uso deja de ser
  individual o la tabla supera el intervalo 1900–2100.
