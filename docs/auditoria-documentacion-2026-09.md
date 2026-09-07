# Auditoría de documentación — 2026-09-04

## Alcance y criterio

Esta auditoría comparó el árbol de trabajo actual de la web, el repositorio
hermano histórico `/srv/proyectos/astronomia/api`, configuración de ejemplo,
Docker/Compose, scripts, migraciones, includes, endpoints, assets relevantes y
tests. El código y la configuración versionable se tomaron como evidencia
principal. No se inspeccionaron valores de `.env`, credenciales ni el archivo
privado de producción. No hubo acceso al panel cPanel ni a la cuenta Cloudflare,
por lo que su estado externo queda expresamente sin verificar.

El árbol web y el de la API ya contenían muchos cambios sin commit al iniciar la
auditoría. Se auditaron como estado de desarrollo vigente y no se revirtieron.
No se desplegó ni se modificó funcionalidad.

## Inventario documental

| Documento | Tema | Evaluación al inicio | Acción/relación |
|---|---|---|---|
| `README.md` | panorama de la web y comportamiento funcional | amplio y mayormente actual; mezcla introducción con detalle | se conserva; este índice orienta hacia documentos especializados |
| `AGENTS.md` | reglas permanentes para agentes | vigente y muy detallado | fuente normativa; no sustituye documentación operativa |
| `docs/README.md` | índice | incompleto frente a los módulos nuevos | actualizado como índice maestro |
| `docs/arquitectura.md` | arquitectura y contratos web | amplio y vigente, con acumulación de detalle | referencia técnica principal |
| `docs/configuracion.md` | configuración web | vigente; contiene ejemplos con marcadores, no secretos reales | se conserva |
| `docs/entorno-local.md` | desarrollo, Docker y pruebas | vigente pero mezcla historia y operación | complementado por `infraestructura-y-operacion.md` |
| `docs/despliegue.md` | FTPS/cPanel y controles | vigente según el script; producción no comprobada | se conserva; no se ejecutó |
| `docs/administracion-y-laboratorio.md` | administración y laboratorio | útil para administración; el Explorador público ya tiene documento propio | se conserva |
| `docs/reglas-editoriales-astronomia.md` | frontera datos/presentación | vigente | se conserva |
| `docs/estado-actual.md` | snapshot de capacidades | obsoleto: sólo nueve vistas y faltaban módulos recientes | reemplazado por snapshot actual |
| `docs/base_host.md` | esquema de tienda | histórico/operativo, no esquema total de `WEB_DB` | rotulado por su alcance en el índice |
| `docs/notificaciones-astronomicas.md` | criterios editoriales Push | breve e incompleto para la arquitectura actual | se mantiene como complemento; operación resumida en infraestructura |
| `docs/resolucion-zona-horaria.md` | dataset y resolución IANA | vigente | referencia específica |
| `docs/geologia-lunar-fichas-piloto.md` | experimento de enriquecimiento | experimental, correctamente acotado | se conserva |
| `docs/contenido/*` y `docs/docs/*` | instantáneas editoriales antiguas | históricas, no fuente de runtime | se mantienen identificadas como históricas |
| `astronomy-engine/README.md` | motor portable | vigente salvo dos enlaces inexistentes y una descripción lunar antigua | corregido y complementado |
| `astronomy-engine/ENTREGA.md` | entrega/inventario inicial del motor | útil pero acumulativo; partes de imagen lunar quedaron históricas | marcado como documento de evolución, no snapshot único |
| `explorador/README.md` | Explorador público, API NDJSON y pruebas | vigente y específico | referencia canónica del módulo |
| `content-data/README.md` | JSON editorial estático | vigente para dos páginas concretas | referencia específica |
| `assets/images/moon-three/SOURCES.md` | procedencia de texturas | vigente | referencia de atribución técnica |
| `pruebas/luna-threejs/*.md` | prototipo y auditoría geométrica | experimental/histórico | no describe por sí solo la Luna pública |
| `/srv/proyectos/astronomia/api/README.md` | API FastAPI | documentación amplia del servicio activo local | histórica/optativa desde la perspectiva productiva de la web |
| `/srv/proyectos/astronomia/api/docs/*.md` | eventos, operación, perfiles, eclipses y satélites Python | documentación especializada, algunas piezas históricas | consultar sólo al operar o comparar la API Python |

## Evidencia revisada

- Web: `docker-compose.yml`, `Dockerfile`, `.env.example`, `.gitignore`,
  `.htaccess`, `apache/astro.conf`, `manifest.webmanifest`, `service-worker.js`,
  `robots.txt` y `sitemap.php`.
- Integración: `includes/api-client.php`, `astronomy-data.php`,
  `astronomy-events.php`, `location-context.php`, `timezone-resolver.php`,
  `seo.php`, `analytics.php`, `site-menu.php` y subsistema `web-push-*`.
- Motor: todas las clases bajo `astronomy-engine/src`, sus fachadas, eclipses,
  `TonightCalculator`, apariencia lunar y paquete satelital SGP4.
- Operación: `scripts/desplegar.sh`, `run-scheduled-tasks.php`,
  `update-satellite-tles.php` y registro completo de migraciones.
- Superficies: páginas PHP raíz, `explorador/`, `embeds/`, `admin/`, assets JS
  lunares/satelitales/fotográficos y sus tests relacionados.
- API: Compose, Dockerfile, requirements, routers, servicios, repositorios,
  generadores, scripts y documentación del repositorio hermano.

## Inconsistencias y correcciones principales

1. `docs/estado-actual.md` hablaba de nueve vistas. El catálogo actual incluye
   El cielo hoy, Fotografía, Luna favorita, Luna interactiva, ISS/Tiangong,
   Contenidos, Notificaciones, Fuentes y créditos y el Explorador, entre otras.
2. El motor enlazaba `docs/php-moon-image.md` y `docs/php-tonight.md`, archivos
   inexistentes. Las referencias se corrigieron hacia documentación real.
3. La imagen lunar pública ya no puede resumirse como “404 PNG + rotación CSS”:
   portada, El cielo hoy, fecha favorita y Luna interactiva usan Three.js en
   superficies expresamente delimitadas, con PNG como fallback; otras lunas
   conservan imágenes estáticas/`moon-image.php`.
4. La API Python no es el motor principal productivo. Los contratos generales
   tienen `php` como default en `astronomyDataSourceCatalog()` y fallback hacia
   API; los grupos de eventos conservan selección independiente cuyo default
   técnico sigue siendo `api` si no hay override persistido o de entorno.
5. PostgreSQL pertenece a la API histórica (persistencia/generación de eventos).
   La web productiva usa MySQL/MariaDB `WEB_DB`; no debe confundirse una base con
   la otra.
6. El dato histórico de timeout TLE de ~3 s no coincide con PHP actual: el
   descargador predetermina 8 s y limita la conexión a 4 s. El TTL de descarga
   es 6 h; 24/72 h son umbrales de advertencia por edad de época, no rechazo.
7. `update-satellite-tles.php` no está integrado en
   `run-scheduled-tasks.php`: es la excepción independiente prevista.
8. El service worker no implementa precache, caché ni offline: sólo `push` y
   `notificationclick`. La identidad PWA proviene del manifest y el registro.
9. El sitemap real es dinámico (`sitemap.php`, reescrito a `/astro/sitemap.xml`)
   y añade artículos visibles de MySQL; no existe una lista XML estática.
10. No hay configuración Cloudflare versionada en ninguno de los dos Compose.
    La existencia actual de reglas, WAF, Access, certificados o Tunnel no puede
    demostrarse desde estos repositorios.

## Límites de verificación

- Las rutas productivas `/home8/aquellaslunascom/public_html/astro` y
  `/home8/aquellaslunascom/config/astronomia.php`, PHP CLI Alt-PHP y el cron se
  encuentran documentados y referenciados por código, pero no existen en esta
  máquina y no se verificaron contra cPanel.
- La configuración Cloudflare y DNS es externa al repositorio.
- Los valores persistidos de `admin_configuracion_sitio`, visibilidad del menú y
  fuentes elegidas pueden diferir entre la base local y producción.
- “Activo en Docker local” no implica “requerido por producción”. La API y
  PostgreSQL estaban ejecutándose durante la auditoría, pero la mayoría de la web
  puede operar autónomamente con PHP + `WEB_DB`.

