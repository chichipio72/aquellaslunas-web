# Funcionalidades públicas y motor astronómico

## Páginas y estado

Los estados describen el árbol de desarrollo actual, no certifican producción.
La visibilidad final del menú se persiste en `WEB_DB`; el sitemap es evidencia de
indexabilidad, no de presencia en menú.

| Sección | Ruta | Función y origen | Estado |
|---|---|---|---|
| Inicio | `/astro/` | resumen diario, “Esta noche”, próximos eventos, Luna Three.js y contexto satelital; fachadas PHP/API y eventos | ESTABLE |
| El cielo hoy | `/astro/cielo-de-hoy.php` | Sol, Luna, luz y condiciones del día; motor/fachadas y Open-Meteo opcional | FUNCIONAL / EN EVOLUCIÓN |
| El cielo esta noche | `/astro/cielo-de-esta-noche.php` | ventanas observables, encuentros y escena angular compartida | FUNCIONAL / EN EVOLUCIÓN |
| Calendario solar y lunar | `/astro/sol-y-luna.php` | días, salidas, puestas, tránsitos, fase y visibilidad | ESTABLE |
| Eventos lunares | `/astro/eventos.php` | eventos por rango/tipo, detalle, clima opcional y calendario | ESTABLE |
| Eclipses | `/astro/eclipses.php` | eclipses globales/locales, mapas y widgets | FUNCIONAL / EN EVOLUCIÓN |
| Planificador | `/astro/planificador.php` | direcciones Sol/Luna sobre mapa para instante y lugar | ESTABLE |
| Fotografía | `/astro/fotografia.php` | simulación de encuadre, sensor, focal 1–3000 mm, orientación, horizonte, arrastre y roll ±45° | FUNCIONAL / EN EVOLUCIÓN |
| Luna de fecha favorita | `/astro/luna-fecha-favorita.php` | Luna Three.js y wallpaper compartible por fecha/hora/lugar | FUNCIONAL / EN EVOLUCIÓN |
| Luna interactiva | `/astro/luna-interactiva.php` | globo 3D, capas, búsqueda, fichas y alunizajes | FUNCIONAL / EN EVOLUCIÓN |
| ISS y Tiangong | `/astro/iss-y-tiangong.php` | visualización orbital 3D y estado temporal | FUNCIONAL / EN EVOLUCIÓN |
| Explorador astronómico | `/astro/explorador/` | series/rangos, filtros, extremos y respuesta NDJSON progresiva | FUNCIONAL / EN EVOLUCIÓN |
| Contenidos | `/astro/contenidos.php` | índice/buscador MySQL; búsqueda `noindex` | ESTABLE |
| Artículo | `/astro/contenido.php?slug=…` | Markdown seguro, relaciones, trivias y embeds controlados | ESTABLE |
| Notificaciones | `/astro/notificaciones.php` | suscripción Push y preferencias del dispositivo | FUNCIONAL / EN EVOLUCIÓN |
| Ubicación | `/astro/ubicacion.php` | geolocalización/manual, mapa y persistencia central | ESTABLE |
| Canciones a la Luna | `/astro/canciones-a-la-luna.php` | contenido temático | ESTABLE |
| Qué ofrece | `/astro/que-podes-hacer.php` | presentación editorial desde JSON validado | ESTABLE |
| Fuentes y créditos | `/astro/fuentes-y-creditos.php` | fuentes desde JSON validado | ESTABLE |
| Acerca del sitio | `/astro/acerca-del-sitio.php` | identidad y propósito | ESTABLE |
| Galería/tienda | `/astro/galeria.php` | catálogo/checkout; fuera de indexación | FUNCIONAL / EN EVOLUCIÓN |
| Pruebas visuales | `/astro/pruebas-visuales.php` | laboratorio protegido por sesión debug | INTERNO |
| Endpoints JSON/ICS | `altitude-profile.php`, `astronomy-directions.php`, `astronomy-featured-dates.php`, `calendar-event.php` | proxies/descargas del mismo origen | INTERNO |
| Infografía de evento | `/astro/infografia-evento.php` | composición compartible, `noindex` | FUNCIONAL / EN EVOLUCIÓN |
| Embeds | `/astro/embeds/*` y `/astro/explorador/embed.php` | widgets sin navegación, `noindex` | FUNCIONAL / EN EVOLUCIÓN |
| Administración | `/astro/admin/` | contenidos, configuración, fuentes, Push, Luna, fotografía y widgets | INTERNO |

`acerca.php` es compatibilidad histórica; las URLs técnicas y administrativas no
son superficies de navegación pública. El sitemap enumera sólo las páginas que
el proyecto pretende indexar y agrega artículos visibles desde MySQL.

## Motor PHP portable

El código vive en `astronomy-engine/src` bajo `AstronomyEngine\` y se carga con
el autoloader manual de `includes/api-client.php`. El núcleo no requiere Python,
HTTP, base de datos ni dependencias Composer. PHP declarado: `>=8.5`; GD sólo es
necesario para el renderer PNG específico, no para los cálculos.

Capacidades comprobadas por clases y fachadas:

- Sol: posición ecuatorial/topocéntrica, distancia, radio aparente, ecuación del
  tiempo, altitud/acimut, salida, puesta, tránsito, períodos de luz y
  crepúsculos, días/rangos, solsticios y perfiles.
- Luna: posición, distancia, diámetro aparente, iluminación, elongación, edad y
  fase, altitud/acimut, salida/puesta/tránsito, intervalos visibles, orientación,
  ángulo del limbo, libración y apariencia del disco.
- Tamaño lunar comparativo: diámetro angular y porcentaje respecto de la Luna a
  384.400 km, sin normalización contra el máximo de un período.
- Fases/órbita: cuatro fases principales, búsquedas temporales, perigeos,
  apogeos, nodos y extremos de libración.
- Eventos: conjunciones con planetas/estrellas/cúmulos, earthshine/Luna fina,
  oportunidades de Luna llena, agregación, normalización, filtros y rango.
- Eclipses: detección y clasificación global lunar/solar, contactos,
  magnitudes/geometría y circunstancias topocéntricas locales; geometría 3D de
  la sombra solar para el widget espacial.
- Observación: direcciones, perfiles de altura, contexto “Tonight”, catálogos
  planetarios/estelares y búsqueda predictiva.
- Satélites: parseo TLE, SGP4 near-earth WGS72, TEME, reducción WGS84, búsquedas
  angulares y tránsitos solares/lunares de ISS/Tiangong.

Las fachadas `DailyAstronomyFacade`, `AstronomyRangeFacade`,
`AstronomyDirectionsFacade`, `MoonInstantFacade`, `AltitudeProfileFacade` y
`AstronomyEventsFacade` componen contratos equivalentes a la API, pero no son
endpoints HTTP. `includes/astronomy-data.php` decide `php` o `api` y el proxy PHP
mantiene la frontera del mismo origen.

## Eventos y fuentes

`includes/astronomy-events.php` es la única entrada web. Fases, ápsides, nodos,
libración, conjunciones y eclipses eligen fuente por grupo con precedencia base
persistida → variable de entorno → `api`. `auto` usa MariaDB sólo donde cubre el
intervalo contractual 1900–2050 y PHP fuera de cobertura. `compare` diagnostica,
no corrige fuentes. Una respuesta API válida vacía no activa fallback; una falla
técnica sí. Earthshine y observación de Luna llena se resuelven por fachada PHP.

## Explorador

Es un subproyecto público autocontenido bajo `explorador/`: interfaz, includes,
catálogo, assets, endpoint y tests propios. Produce NDJSON progresivo con
metadatos, filas y progreso; admite cancelación del cliente, rangos largos,
variables agrupadas, fases principales, series diarias, extremos locales,
relaciones y URL compartible. La ubicación llega explícitamente en cada request.
Su estado y límites canónicos están en `explorador/README.md`; el laboratorio de
`admin/` es otra superficie y no debe confundirse con esta versión pública.

## Luna 3D e imágenes

Las superficies autorizadas usan Three.js local desde `assets/js/vendor/`,
textura/color y DEM/normal maps de `assets/images/moon-three/`. La geometría
servidor se deriva de `MoonDiskAppearanceCalculator`; libración, orientación y
dirección solar pertenecen al instante/observador. El normal 8K es progresivo:
Luna interactiva según umbral y sólo bajo acciones específicas en visor de
portada/exportación de fecha favorita.

`luna-interactiva.php` usa módulo propio para proyectar etiquetas 3D. Catálogo
curado: `assets/data/moon-features.json`; alunizajes exitosos:
`moon-landings.json`; búsqueda completa offline: `moon-gazetteer.json`.
Geología oficial, traducción y enriquecimientos permanecen separados. La URL
conserva tiempo, capas, selección, zoom y rotación; enlaces desde escenas deben
preservar el instante observacional.

La densidad predeterminada `detail=auto` progresa con el zoom y limita etiquetas
por prioridad, diámetro, superficie y colisiones; un accidente puede conservar su
marker interactivo aunque el nombre no quepa. `main` y `more` permanecen como modos
explícitos compatibles.

Los nombres y fuentes de texturas actuales están en
`assets/images/moon-three/SOURCES.md`; incluye derivados `lroc_color_2k.jpg`,
`ldem_3_8bit.jpg`, `ldem_4_height.png`, `ldem_8_height.png` y normals. Las demás
imágenes lunares siguen con colección estática/`moon-image.php`; no todo el sitio
usa Three.js.

## Portada y “Esta noche”

`includes/home-tonight-scene.php` comparte cálculo/escena entre Inicio, El cielo
hoy y Esta noche. `TonightCalculator` usa umbral de cercanía de 10°, prioriza
hasta dos planetas y sólo menciona la estrella más cercana si no hay planetas.
Las muestras futuras se comparan con el reloj vigente. La selección de eventos
próximos descarta vencidos y trata la madrugada hasta 06:00 local como parte de
“Esta noche”; eclipses pueden ocupar un destacado. Las recomendaciones de
cráteres cercanos al terminador se centralizan en
`includes/moon-crater-recommendations.php`.

Inicio muestra además los dos próximos hitos solares futuros —salida o puesta—
según el reloj real o simulado. Los nodos lunares ascendente y descendente forman
parte del catálogo público configurable y se presentan en Inicio, El cielo hoy y
Eventos; su migración inicial respeta cualquier configuración editorial ya tocada.

## Contenidos, SEO y Analytics

Artículos, relaciones dirigidas, trivias y “Sabías que” viven en `WEB_DB`; el
editor valida slugs y Markdown. HTML/iframe editorial no se permite. Los embeds
usan `[[embed url="…"]]` con allowlist y atributos impuestos por el renderer.
`que-podes-hacer.php` y `fuentes-y-creditos.php` son excepciones editoriales JSON
validadas desde `content-data/`.

`includes/seo.php` centraliza canonical, robots, Open Graph, Twitter y JSON-LD.
Artículos públicos incorporan `Article` + `BreadcrumbList` y breadcrumb visible.
`sitemap.php` genera `/astro/sitemap.xml`; `robots.txt` excluye rutas privadas y
técnicas. Galería y pruebas son `noindex,nofollow`; búsquedas internas son
`noindex,follow`; embeds y admin usan `noindex`.

`includes/analytics.php` carga GA4 con un ID público fijo y un wrapper tolerante
a fallos. Hay eventos PWA (`pwa_install_*`), trivias (`trivia_view`,
`trivia_answer`, `trivia_article_click`) y eventos de interacción definidos en
assets específicos. No se observó una plataforma local de consentimiento ni una
bandera que desactive GA4 en desarrollo. Cloudflare Analytics, si existe, es un
sistema externo distinto y no verificable en el repo.

## Ubicación y tiempo

`astronomyLocationContext()` es la fuente única: bloque atómico de latitud,
longitud, elevación y zona IANA en cookies versionadas. El servidor resuelve la
zona desde coordenadas con el dataset local `includes/timezone-geo`; nunca confía
en el timezone del navegador. La etiqueta puede enriquecerse con Nominatim.
Fallback: Buenos Aires. La simulación temporal sólo se habilita en entorno local
o sesión administrativa autorizada y alimenta `window.siteTimeContext`.
