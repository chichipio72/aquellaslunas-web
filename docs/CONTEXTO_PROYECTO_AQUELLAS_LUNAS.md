# Aquellas Lunas — Contexto del proyecto

## 1. Qué es Aquellas Lunas

Sitio web en español para observar y comprender el cielo desde la ubicación del
visitante. Combina efemérides solares/lunares, eventos, eclipses, satélites,
visualizaciones 3D, planificación fotográfica, contenidos, PWA y notificaciones.

## 2. Estado actual

La aplicación principal es PHP 8.5 sin framework sobre Apache. Incluye un motor
astronómico PHP portable que resuelve contratos generales y eventos principales
sin Python, red ni DB. MySQL/MariaDB (`WEB_DB`) guarda contenidos, configuración,
menú, Web Push y eventos persistidos. La API FastAPI de la mini PC sigue activa
como fuente opcional, comparación y fallback, no como motor principal.

Los cálculos generales predeterminan a PHP. Los grupos de eventos conservan
selección separada y, sin override persistido o de entorno, predeterminan a API;
por eso la dependencia productiva real debe comprobarse en configuración.

## Principios del producto

- Aquellas Lunas nació como una cuenta de fotografías de la Luna y conserva esa
  identidad lunar y visual aunque hoy abarque más fenómenos astronómicos.
- Su pregunta central es qué está pasando, qué puede observarse y cuándo desde
  una ubicación y un momento concretos; no busca ser una enciclopedia general.
- Prioriza utilidad observacional, rigor científico y explicación accesible para
  personas no técnicas. El detalle avanzado es una segunda capa opcional.
- El idioma editorial es español argentino: voseo, horarios y fechas locales y
  redacción natural antes que nomenclatura interna.
- Fotografía lunar e imágenes son parte del producto. Las configuraciones
  fotográficas publicadas son referencias, no recetas universales.
- La UI pública no debe revelar selección PHP/API, infraestructura ni diagnósticos;
  comparación y trazabilidad pertenecen a administración/laboratorio.
- La meteorología es orientativa y degradable: ayuda a decidir, no garantiza la
  observación ni bloquea la astronomía.
- En superficies solares se conserva la advertencia de no observar directamente
  ni con instrumentos sin filtro solar certificado.

## Reglas funcionales transversales

### Tiempo observacional

“Esta noche” es una ventana astronómica que atraviesa medianoche. La madrugada
inmediata continúa la noche anterior; los helpers de etiquetas usan 06:00 local
como corte observacional. Se comparan instantes completos —fecha, hora, offset y
zona—, no sólo fechas civiles. Objetos, encuentros, tránsitos y eventos cuyo
instante o ventana útil terminó se descartan; lo visible ahora conserva sólo su
tramo futuro.

Inicio diferencia “Esta noche”, “Mañana” y días futuros. “Lo próximo” mezcla
familias válidas cronológicamente y omite vencidos; “Próximas fases” conserva su
bloque aunque las fases también puedan aparecer en la secuencia general. Un
eclipse lunar nocturno puede reemplazar la escena habitual por un destacado, y
los avisos de eclipses respetan el mismo corte de madrugada.

### Persistencia del instante observacional

Cuando una escena representa otro momento, la navegación relacionada conserva
ese instante. La escena Luna–astros usa su `datetime` futuro; Fotografía transporta
evento y hora de observación; la Luna ampliada de Inicio recibe fecha y hora. Las
recomendaciones de cráteres enlazan por ID estable y conservan el instante cuando
corresponde. Luna favorita, Luna interactiva, Fotografía, Explorador y embeds
mantienen contexto compartible en URL. No deben abrir silenciosamente “ahora” si
el origen representa otra hora.

### Ubicación y zona horaria

La ubicación activa es un bloque atómico: nombre, latitud, longitud, elevación,
zona IANA, modo y confirmación. Puede obtenerse por geolocalización del navegador
o selección manual; se valida y persiste en cookies versionadas. Si se deniega o
falla, permanece la última ubicación válida o el default Buenos Aires. Nominatim
puede enriquecer el nombre, pero las coordenadas gobiernan los cálculos.

La zona IANA se resuelve en servidor desde coordenadas mediante el dataset local;
el timezone del navegador no es autoridad. Las páginas normales reutilizan
cookies; herramientas compartibles sólo adoptan coordenadas/fecha/hora de URL si
forman un contexto completo y válido.

`America/Argentina/Buenos_Aires` es default observacional, timezone de Docker y
contexto documentado del cron. No reemplaza la zona real de otro observador:
efemérides, etiquetas civiles y noches usan la IANA de la ubicación activa.

### Navegación temporal

Cada página valida fechas según su contrato. Fecha favorita, Luna interactiva,
Fotografía, Explorador y widgets reflejan el contexto relevante en query string;
los formularios preservan parámetros de evento sólo mientras coincidan con fecha
y hora mostradas. `debug_now` es exclusivo de local o sesión autorizada y puede
propagarse por navegación diagnóstica, pero no es estado público permanente.

## 3. Arquitectura

```text
Usuario → Cloudflare → hosting cPanel/Apache → PHP
                                          ├─ motor PHP
                                          ├─ WEB_DB / STORE_DB
                                          ├─ assets y datasets locales
                                          └─ servicios externos puntuales
```

El navegador no accede a servicios privados. Históricamente:

```text
PHP hosting → Cloudflare Tunnel → FastAPI mini PC → Skyfield/DE421/PostgreSQL
```

El Tunnel no está versionado. Servicio local activo no equivale a dependencia
productiva.

## Evolución de la arquitectura astronómica

1. La arquitectura inicial concentró cálculos en una mini PC con FastAPI,
   Skyfield/DE421 y eventos en PostgreSQL.
2. El PHP del hosting consultaba esa API server-side mediante Cloudflare Tunnel.
3. Se creó `astronomy-engine/`, portable y autocontenido en PHP.
4. Se migraron progresivamente contratos generales, eventos, eclipses,
   observación nocturna y el bloque TLE/SGP4.
5. La arquitectura objetivo usa PHP como motor principal y conserva Python como
   fuente configurada, fallback, comparación o legado.

Que FastAPI y PostgreSQL estén activos localmente no demuestra dependencia de
producción. Tampoco deben retirarse hasta comprobar fuentes productivas y tipos
sin reemplazo.

## Modelo de fuentes astronómicas

Una “fuente” es la implementación elegida para obtener un contrato, no un dato
editorial visible. `includes/astronomy-data.php` permite PHP/API para `daily`,
`range`, `directions`, `moon/instant`, `altitude-profile` y `tonight`; PHP es el
default y una falla técnica intenta la alternativa. `moon/image` elige colección
estática —default— o API.

Los eventos entran por `includes/astronomy-events.php`. Fases, ápsides, nodos,
libraciones, conjunciones y eclipses admiten `api`, `database`, `php`, `auto` y
`compare`. Precedencia: override en `admin_configuracion_sitio` → variable de
entorno específica → `api`. `auto` usa MariaDB donde cubre completamente
1900–2050 y PHP fuera; `compare` diagnostica sin corregir. Una respuesta API
válida vacía no activa fallback; una falla técnica sí usa la cadena disponible.

`/admin/fuentes-astronomicas/` cambia overrides persistidos para las siguientes
solicitudes, también en producción si comparte esa `WEB_DB`. Los botones globales
sólo preparan selectores hasta guardar. Variables, URL y credenciales de FastAPI
no se gestionan ahí. La selección efectiva en producción no está comprobada.

## 4. Infraestructura

- Producción documentada: `https://aquellaslunas.com.ar/astro/`, cPanel, Apache,
  Alt-PHP 8.5/CGI-FastCGI, sin Docker.
- Ruta documentada: `/home8/aquellaslunascom/public_html/astro`.
- Mini PC: repos `web` y `api` bajo `/srv/proyectos/astronomia/`; Docker Compose.
- Web: MySQL/MariaDB externas `WEB_DB` y `STORE_DB`.
- API histórica: PostgreSQL 17; tabla principal `astronomical_events`.
- Scheduler PHP común y actualización TLE independiente.
- Cloudflare: proxy/HTTPS documentados; cuenta externa no auditada.

## 5. Estructura del proyecto

```text
/srv/proyectos/astronomia/
├─ web/
│  ├─ admin/                    administración privada
│  ├─ astronomy-engine/src/     motor PHP (Facade/ y Satellite/)
│  ├─ assets/                   CSS, JS, imágenes y catálogos
│  ├─ content-data/             JSON editorial validado
│  ├─ docs/                     documentación técnica
│  ├─ embeds/                   widgets no indexables
│  ├─ explorador/               subproyecto público autocontenido
│  ├─ includes/                 integración y dominio
│  ├─ scripts/migrations/       operación y esquema
│  └─ tests/                    regresión PHP/JS/HTTP
└─ api/                         FastAPI histórica/optativa
   ├─ app/api, services, generators
   ├─ docs/
   └─ tests/
```

## Despliegue y operación

El despliegue productivo es explícito y manual. El script real es
`/srv/proyectos/astronomia/web/scripts/desplegar.sh`. Su propósito es publicar
desde el árbol local `/srv/proyectos/astronomia/web/` hacia la raíz remota de la
cuenta FTPS habitual, que corresponde físicamente a
`/home8/aquellaslunascom/public_html/astro` y públicamente a
`https://aquellaslunas.com.ar/astro/`. Producción no se actualiza al editar el
repositorio local: primero se desarrolla y prueba en Docker/local, después se
revisa el dry-run, se ejecuta manualmente la transferencia y finalmente se
comprueba la URL pública. Nunca debe asumirse que un cambio local ya está
desplegado.

El script usa `lftp` contra `set.servidoraweb.net:21`, con FTP sobre TLS
explícito: obliga TLS, protege el canal de datos y verifica certificado y
hostname. La contraseña principal sólo entra mediante la variable de entorno
`ASTRONOMY_FTP_PASSWORD`, se entrega a `lftp` como `LFTP_PASSWORD` y se elimina
del entorno al salir. El host, puerto, usuario de publicación, raíz local
esperada y destino son constantes del script, no parámetros de línea de
comandos.

Opciones soportadas, combinables y sin argumentos de valor:

| Opción/variable | Default | Comportamiento |
|---|---|---|
| sin opciones | despliegue real, `vendor/` excluido | conecta y publica los cambios elegibles |
| `--list-local` | desactivado | lista candidatos usando las mismas exclusiones; no requiere credenciales, no conecta ni transfiere |
| `--dry-run` | desactivado | conecta y compara con el destino mediante `lftp mirror --dry-run`, pero no transfiere; también informa archivos explícitos y `robots.txt` que publicaría |
| `--include-vendor` | desactivado | elimina únicamente la exclusión de `vendor/`; aplica a listado, dry-run o despliegue real |
| `ASTRONOMY_FTP_PASSWORD` | sin valor | obligatoria para dry-run remoto y despliegue real |
| `ASTRONOMY_ROOT_FTP_USER` + `ASTRONOMY_ROOT_FTP_PASSWORD` | sin valor | pareja opcional para publicar el mismo `robots.txt` en la raíz del dominio; si falta cualquiera, el despliegue de `/astro/` continúa con aviso |

Cualquier opción desconocida muestra el uso y termina con código 2. Antes de
conectar valida que la raíz resuelta sea exactamente
`/srv/proyectos/astronomia/web`, que existan `index.php` y `sol-y-luna.php`, que
`lftp` esté instalado y que exista la contraseña principal. Usa Bash con
`set -Eeuo pipefail` y configura `lftp` con salida ante error.

La transferencia principal ejecuta `mirror --reverse --verbose`. Los archivos
nuevos y los que `lftp` detecta distintos se cargan; los idénticos se omiten.
El script no usa `--delete`: un archivo eliminado localmente, recién excluido o
ausente del mirror permanece en el hosting hasta que se retire deliberadamente
por otra vía. Esto evita borrados automáticos, pero obliga a auditar residuos y
copias antiguas en producción.

El mirror normal excluye:

- Git/GitHub, configuración de editores e IDE y entornos `.venv`;
- `docs/`, `apache/`, `scripts/`, `tests/`, `local-tools/` y `storage/`;
- resultados de benchmark del Explorador, Dockerfiles, Compose, archivos ignore,
  README, `php.ini`, `pytest.ini` y `requirements.txt`;
- `.env` y variantes, CSV, ZIP, Python/bytecode, logs, PID, temporales, cachés,
  coverage, respaldos de editor y metadatos del sistema operativo;
- los PNG raíz históricos `cuarto.png`, `llena.png` y `luna-nueva.png`;
- `robots.txt`, porque se trata separadamente para la raíz del dominio;
- `vendor/`, salvo que se use `--include-vendor`.

Las exclusiones no afectan `astronomy-engine/`, páginas PHP, `includes/`, assets,
embeds ni catálogos públicos. Por eso también se transfieren archivos locales
nuevos que todavía no estén en Git si no coinciden con una exclusión. Assets
especiales presentes localmente —por ejemplo mapas de eclipses ignorados por
Git y previews públicas— entran en el mirror; originales y catálogo privado bajo
`storage/` no entran.

Aunque `scripts/` se excluye globalmente, después del mirror el script abre una
segunda sesión y carga mediante `put` una allowlist explícita: el orquestador
`scripts/run-scheduled-tasks.php`, el actualizador TLE,
`cleanup-astronomy-request-log.php`, `scripts/migrations/registry.php` y las
migraciones PHP/SQL registradas para Web Push, trazabilidad astronómica, menú,
plantillas de eclipses, fotografía, ISS/Tiangong y Fuentes/créditos. En dry-run
no ejecuta esos `put`: sólo enumera cada archivo que publicaría. Agregar un script
al repositorio no basta para desplegarlo; debe incorporarse expresamente a esa
allowlist y al bloque de transferencia.

Las migraciones nuevas de presets de escena lunar y publicación de nodos ya están
en esa allowlist local. Esa corrección no confirma el hosting: hasta desplegar y
verificar los archivos explícitos, el scheduler productivo puede seguir fallando
si ya recibió un `registry.php` que los referencia.

`robots.txt` está excluido del mirror `/astro/`. Cuando se proporcionan las dos
variables de la cuenta raíz, se publica mediante otra conexión en
`/robots.txt`; sin ellas queda intacto. `vendor/` sólo se incluye deliberadamente,
normalmente después de generar localmente las dependencias de Web Push. Cachés y
temporales nunca forman parte del mirror. El archivo privado productivo
`/home8/aquellaslunascom/config/astronomia.php`, los `.env`, credenciales,
originales fotográficos y configuración Cloudflare están fuera del mirror y no
deben incorporarse a él.

Procedimiento habitual: validar funcionalidad y tests localmente; ejecutar
`./scripts/desplegar.sh --list-local` si se necesita auditar candidatos; exportar
las credenciales sólo en la consola operativa; ejecutar
`./scripts/desplegar.sh --dry-run` y revisar el plan; ejecutar manualmente el mismo
script sin `--dry-run`; limpiar las variables; comprobar por HTTPS páginas,
assets, headers, sitemap/robots, rutas privadas y logs. `--include-vendor` se usa
sólo cuando corresponda actualizar dependencias Composer. El detalle de comandos,
permisos y checklist post-publicación permanece en `docs/despliegue.md`.

## Convenciones de trabajo

- Desarrollo y pruebas ocurren primero en la mini PC/Docker. Producción sólo
  cambia mediante el despliegue manual explícito del operador; ninguna tarea de
  desarrollo debe desplegar por sí misma.
- Ante diferencias local/producción, comprobar primero qué versión se publicó.
  Un archivo modificado localmente o incluso listo para desplegar no prueba que
  el hosting haya cambiado.
- Secretos y configuración privada quedan fuera de Git y, en producción, fuera
  del document root. No se diagnostica imprimiendo credenciales.
- Se avanza en bloques funcionales razonables y se reserva la validación minuciosa
  para errores, inconsistencias, seguridad, persistencia y cambios sensibles.
- El motor PHP es la base madura para iterar en cambios no críticos, manteniendo
  tests proporcionales y compatibilidad con el hosting sin extensiones opcionales.
- El Explorador público permanece encapsulado bajo `explorador/`, con endpoints,
  includes, assets, documentación y tests propios; no se fusiona con el laboratorio
  administrativo.

## 6. Motor astronómico

`astronomy-engine/src` implementa:

- Sol: posición, coordenadas, altitud/acimut, distancia, tamaño, ecuación del
  tiempo, salida/puesta/tránsito, crepúsculos, períodos y perfiles.
- Luna: posición, distancia, tamaño, iluminación, elongación, edad/fase,
  altitud/acimut, salida/puesta/tránsito, orientación, limbo y libración.
- Tamaño lunar: diámetro angular y escala porcentual respecto de la distancia
  media de 384.400 km, reutilizados por la comparación de lunas llenas.
- Fases/órbita: cuatro fases, perigeos, apogeos, nodos y extremos de libración.
- Eventos: conjunciones, earthshine/Luna fina, observación de Luna llena, rangos,
  filtros, deduplicación y normalización.
- Eclipses lunares/solares: clase global, contactos, magnitudes, geometría y
  circunstancias topocéntricas locales.
- Contratos daily/range/directions/moon-instant/altitude-profile/tonight,
  solsticios y búsqueda predictiva.
- Satélites: TLE, SGP4 WGS72, TEME, observador WGS84 y tránsitos Sol/Luna.

Se carga con el autoloader manual de `includes/api-client.php`; el núcleo no
depende de Composer. Detalle: `astronomy-engine/README.md` y
`docs/funcionalidades-y-motor.md`.

## 7. API Python histórica y mini PC

Se creó para servir FastAPI + Skyfield + DE421, orientación lunar, imágenes,
visibilidad nocturna y eventos generados/persistidos en PostgreSQL. Expone health,
daily, range, directions, altitude-profile, tonight, events, moon/instant,
moon/image y un endpoint satelital experimental. PHP reemplazó los contratos
generales y familias principales. PostgreSQL no es necesario para cálculos PHP.
Referencia local externa: `/srv/proyectos/astronomia/api/README.md`.

## 8. Servicios externos

| Servicio | Uso | Criticidad/fallback |
|---|---|---|
| FastAPI/mini PC | fuente/fallback astronómico | depende de fuentes elegidas |
| `WEB_DB` | contenido, config, Push y eventos | crítica para esas áreas |
| `STORE_DB` | tienda/pedidos/pagos | crítica sólo para tienda |
| CelesTrak | TLE ISS/Tiangong | caché y último TLE válido |
| Nominatim/OSM | mapa/nombre de ubicación | degradable |
| dataset timezone local | zona IANA por coordenadas | crítico, sin red |
| Open-Meteo | nubosidad | opcional; caché 30 min |
| Mercado Pago | checkout/webhook | crítico sólo para compra |
| Google Analytics 4 | medición | no crítico |

## 9. Secciones del sitio

| Sección | Ruta | Función | Estado | Situación / próximo paso |
|---|---|---|---|---|
| Inicio | `/astro/` | resumen/próximos fenómenos | ESTABLE | incorpora los dos próximos hitos solares futuros; módulos se habilitan desde admin |
| El cielo hoy | `/astro/cielo-de-hoy.php` | resumen diario | FUNCIONAL / EN EVOLUCIÓN | núcleo terminado; comparte escenas y perfiles |
| Esta noche | `/astro/cielo-de-esta-noche.php` | observación nocturna | FUNCIONAL / EN EVOLUCIÓN | filtra pasado y conserva noche observacional |
| Calendario | `/astro/sol-y-luna.php` | días solares/lunares | ESTABLE | sin deuda funcional confirmada |
| Eventos | `/astro/eventos.php` | agenda lunar | ESTABLE | incluye nodos ascendente/descendente y Luna fina/luz cenicienta; fuente productiva pendiente de confirmar |
| Eclipses | `/astro/eclipses.php` | global/local/widgets | FUNCIONAL / EN EVOLUCIÓN | plantillas configurables; validar despliegue productivo |
| Planificador | `/astro/planificador.php` | direcciones en mapa | ESTABLE | depende de servicios cartográficos degradables |
| Fotografía | `/astro/fotografia.php` | encuadre lunar | FUNCIONAL / EN EVOLUCIÓN | catálogo/calibración editables; entrega comercial aparte pendiente |
| Luna favorita | `/astro/luna-fecha-favorita.php` | Luna/wallpaper por fecha | FUNCIONAL / EN EVOLUCIÓN | configuración visual independiente |
| Luna interactiva | `/astro/luna-interactiva.php` | globo lunar 3D | FUNCIONAL / EN EVOLUCIÓN | etiquetas automáticas progresivas; catálogos se regeneran offline |
| ISS y Tiangong | `/astro/iss-y-tiangong.php` | estaciones orbitales 3D | FUNCIONAL / EN EVOLUCIÓN | depende de caché/TLE y tarea externa |
| Explorador | `/astro/explorador/` | series y análisis | FUNCIONAL / EN EVOLUCIÓN | subproyecto encapsulado; pendientes en su README |
| Contenidos | `/astro/contenidos.php` | artículos/búsqueda | ESTABLE | publicación inmediata desde editor |
| Notificaciones | `/astro/notificaciones.php` | Web Push | FUNCIONAL / EN EVOLUCIÓN | depende de VAPID, `vendor/` y scheduler productivos |
| Ubicación | `/astro/ubicacion.php` | contexto geográfico | ESTABLE | fallback y timezone local implementados |
| Galería | `/astro/galeria.php` | tienda, noindex | FUNCIONAL / EN EVOLUCIÓN | falta entrega pública de compras |
| Pruebas visuales | `/astro/pruebas-visuales.php` | laboratorio protegido | INTERNO | sólo debug autorizado |
| Administración | `/astro/admin/` | gestión privada | INTERNO | inventario y límites documentados debajo |

Hay además Canciones, Qué ofrece, Fuentes/créditos, Acerca, infografías,
endpoints técnicos y embeds. Inventario: `docs/funcionalidades-y-motor.md`.

## Administración

`/astro/admin/` es el área privada para edición editorial, presentación,
configuración persistida, operación Push, trazabilidad y herramientas técnicas.
Usa una sesión PHP común con usuario/hash de configuración externa, cookie
HttpOnly/SameSite, regeneración al iniciar sesión, CSRF en escrituras y cabeceras
`no-store`/`noindex`. No se observó control por roles, IP o múltiples usuarios.

| Área | Qué controla | Persistencia | Efecto |
|---|---|---|---|
| Menú y secciones | grupos, orden, visibilidad pública/admin, tarjetas de Inicio y avisos/widgets de eclipse | `WEB_DB` | próxima carga pública |
| Presentación | nombres, tipos/superficies de eventos, reglas, umbrales y textos | `WEB_DB` con defaults PHP | próxima carga; restauración elimina overrides |
| Fuentes astronómicas | PHP/API/estática por contrato y API/DB/PHP/auto/compare por grupo | `WEB_DB` | siguientes cálculos; puede cambiar dependencia de mini PC |
| Contenidos | artículos, Markdown, visibilidad, imágenes, relaciones, trivias y “Sabías que” | `WEB_DB`; fotos en `STORE_DB`/archivos | próxima carga/indexación |
| Luna de portada | apariencia separada de Inicio, fecha favorita y Luna interactiva | `WEB_DB` | próxima carga; assets no se editan |
| Fotografía | escenas/variantes y calibración visual simulada | tablas editoriales + `admin_configuracion_sitio` | próxima carga |
| Widgets lunares | genera URL/iframe y previews; administra presets de escena lunar | URL/`localStorage`; presets en `WEB_DB`; plantillas de eclipse público en Menú | sin cambio público hasta usar una URL o plantilla; presets disponibles al recargar admin |
| Galería | disponibilidad, precio y metadatos de fotos | `STORE_DB` | próxima carga de galería; no administra pedidos/entrega |
| Suscripciones/Push | altas del dispositivo actual, envíos manuales, diagnóstico y búsqueda | `WEB_DB`; VAPID externa | alta/envío inmediato según acción |
| Notificaciones astronómicas | dispositivo, ubicación, preferencias, silencio, tipos, plantillas y pruebas programadas | tablas `web_push_*` | config inmediata; avisos/pruebas en próximo scheduler |
| Trazabilidad | consulta/filtros/detalle y limpieza por retención | `astronomy_request_log` | inmediato; captura depende del flag general |
| Laboratorio | consulta series y extremos históricos | sólo lectura de `datos_astronomicos` | no cambia producción |
| Estado operativo | scheduler, migraciones pendientes, logs y salud Push | lectura de tablas operativas | informativo; no ejecuta cron |

No se administran desde esta UI: credenciales/URL privada de FastAPI, VAPID
privada, conexiones DB, Mercado Pago, FTPS, Cloudflare/cPanel, cron, PHP/extensiones,
TLE/TTL o assets/catálogos lunares. Eso requiere configuración externa, scripts,
servicios externos o despliegue. “Laboratorio” consulta datos; “Trazabilidad”
observa operaciones; ninguno es un editor productivo. Inventario de cada ruta,
acción, validación, efecto y almacenamiento:
`docs/administracion-y-laboratorio.md`.

## 10. Luna interactiva

Three.js local renderiza textura, relieve, libración, iluminación/terminador y
orientación calculada por PHP. Proyecta etiquetas 3D y ofrece capas, búsqueda
Gazetteer, fichas, aterrizajes exitosos y URL compartible. Catálogos:
`assets/data/`; fuentes de texturas: `assets/images/moon-three/SOURCES.md`. El
normal 8K se carga progresivamente en superficies autorizadas. `detail=auto` es
el modo predeterminado: aumenta etiquetas y markers por bandas de zoom, prioridad,
tamaño, área disponible y colisiones; `main` y `more` siguen siendo compatibles.

El generador administrativo ofrece nueve widgets. Los recientes son una escena
lunar observacional configurable —fecha/hora y apariencia en URL, ubicación activa—
y la comparación de las próximas doce lunas llenas por tamaño angular real. Sólo
la escena admite presets server-side; guardarlos facilita reutilizar parámetros,
pero no publica el widget ni altera la configuración pública del sitio.

## 11. Explorador Astronómico

Subproyecto en `explorador/` con endpoint, includes, assets y tests propios.
Entrega NDJSON progresivo, avance/cancelación, rangos largos, variables, fases,
extremos, relaciones y URL compartible. No es el laboratorio administrativo.
Detalle: `explorador/README.md`.

## 12. ISS / Tiangong

PHP propaga TLE de CelesTrak con SGP4 near-earth WGS72 y TEME→WGS84. Caché JSON
ignorada, TTL 6 h; advierte a >24 h y baja confianza a >72 h. Ante descarga
fallida conserva el último TLE válido. `scripts/update-satellite-tles.php` fuerza
actualización y se programa aparte.

## 13. PWA y notificaciones

Manifest con scope/start `/astro/`. El service worker sólo gestiona Push/clics:
sin offline/precache. Web Push usa VAPID y `minishlink/web-push` server-side;
datos/preferencias viven en `WEB_DB`. Tipos comprobados: salida lunar, eclipse,
conjunción lunar, tránsito satelital y prueba administrativa.

## 14. Contenidos

Artículos MySQL con Markdown seguro, relaciones dirigidas, trivias, “Sabías que”,
imagen y embeds controlados. Sin HTML/iframe editorial libre. Los slugs publicados
son estables. Dos páginas editoriales usan JSON validado en `content-data/`.

## 15. SEO

`includes/seo.php` centraliza title, description, canonical, robots, Open Graph,
Twitter y JSON-LD. Artículos usan `Article` + `BreadcrumbList`. `sitemap.php`
genera `/astro/sitemap.xml` y añade artículos visibles. Admin, embeds, pruebas y
endpoints técnicos no se indexan; búsquedas usan `noindex,follow`.

## 16. Analytics

GA4 se carga desde `includes/analytics.php` con ID público fijo. Hay eventos PWA,
trivias e interacciones específicas. No se observó consentimiento ni bandera de
desactivación local. Cloudflare Analytics sería independiente y externo.

## 17. Cloudflare

Se documentan proxy/HTTPS del sitio y Tunnel histórico a FastAPI. No hay archivos
versionados que permitan confirmar DNS, certificados, WAF, reglas, caché,
Analytics, Tunnel o Access actuales. Consultar la cuenta externa.

## 18. Configuración y credenciales

| Archivo | Entorno | Uso |
|---|---|---|
| `web/.env` | local, ignorado | web/API/DB/Push/tienda/pagos |
| `web/.env.example` | plantilla | nombres de variables |
| `api/.env` | mini PC, ignorado | PostgreSQL/API |
| `/home8/aquellaslunascom/config/astronomia.php` | producción, externo | configuración privada |
| configuración Cloudflare | externa | DNS/proxy/Tunnel/Access |
| credenciales FTPS | operación local | despliegue por script |

Nunca copiar valores sensibles a documentación, Git, navegador o logs.

## 19. Contenedores

| Contenedor/servicio | Función | Desarrollo | Producción | Estado |
|---|---|---|---|---|
| `web-astro` / `web` | Apache + PHP | sí | no | principal local |
| `api-astro` / `api-demo` | FastAPI/Skyfield | opcional | si se elige API | legado operativo |
| `postgres` | eventos de API | para API | no para motor PHP | legado operativo |

## 20. Tareas programadas

`run-scheduled-tasks.php` toma lock, aplica migraciones automáticas registradas,
bloquea ante manuales críticas, procesa pruebas/notificaciones, persiste estado y
emite resumen UTC. Sin reintento interno. TLE se actualiza aparte. Confirmar
comandos/frecuencias reales en cPanel.

Entre las migraciones automáticas actuales están la publicación conservadora de
nodos lunares en Inicio/El cielo hoy/Eventos y la tabla de presets de escena lunar.
La primera sólo modifica registros que todavía conservan exactamente el estado
legado desactivado, para no sobrescribir decisiones editoriales existentes.

## 21. Documentación detallada

| Tema | Archivo |
|---|---|
| Índice maestro | `docs/README.md` |
| Auditoría/contradicciones | `docs/auditoria-documentacion-2026-09.md` |
| Arquitectura web | `docs/arquitectura.md` |
| Infraestructura/Cloudflare/scheduler | `docs/infraestructura-y-operacion.md` |
| Páginas y motor | `docs/funcionalidades-y-motor.md` |
| Configuración | `docs/configuracion.md` |
| Desarrollo/pruebas | `docs/entorno-local.md` |
| Despliegue | `docs/despliegue.md` |
| Administración/laboratorio | `docs/administracion-y-laboratorio.md` |
| Explorador público | `explorador/README.md` |
| Motor portable | `astronomy-engine/README.md` |
| Notificaciones | `docs/notificaciones-astronomicas.md` |
| Zona horaria | `docs/resolucion-zona-horaria.md` |
| Pendientes | `docs/pendientes-auditados.md` |

## 22. Pendientes

Confirmados: entrega pública de compras; confirmar cron/TLE, fuentes productivas,
rutas cPanel y Cloudflare; esquema consolidado `WEB_DB`; controles de exposición
post-despliegue. Deuda: documentación extensa/duplicada y defaults de fuentes
distintos. Detalle: `docs/pendientes-auditados.md`.

## 23. Componentes legacy

- FastAPI, PostgreSQL y posible Tunnel: activos localmente, no principales cuando
  PHP/DB están seleccionados.
- `acerca.php`: compatibilidad histórica por confirmar.
- `docs/contenido/*`, `docs/docs/*`, `pruebas/`: snapshots/prototipos.
- PNG/API siguen para fallbacks/superficies no migradas; Three.js no los reemplazó
  globalmente.

## 24. Decisiones importantes de arquitectura

- Cálculo principal ejecutable localmente en PHP; minimizar dependencia mini PC.
- Navegador → PHP; servicios privados y secretos sólo server-side.
- Configuración sensible fuera de `public_html` y Git.
- Ubicación atómica; servidor resuelve zona IANA desde coordenadas.
- Eventos únicamente por `includes/astronomy-events.php`.
- TLE con validación/caché/fallback y actualización separada.
- Service worker sólo Push/click, sin caché offline incidental.
- Admin, pruebas, embeds y endpoints técnicos fuera de indexación.
- JSON editorial validado; Markdown sin HTML arbitrario.
- `vendor/` sólo se despliega explícitamente cuando Web Push lo requiere.
