# Infraestructura y operación

## Arquitectura actual

```text
Usuario → Cloudflare → Apache/cPanel → PHP 8.5
                                     ├─ motor portable PHP
                                     ├─ MySQL/MariaDB WEB_DB
                                     ├─ archivos/JSON/assets locales
                                     └─ servicios externos puntuales
                                        (API Python sólo si se selecciona o como fallback)
```

El navegador consume páginas y proxies del mismo origen. No conoce la URL de la
API FastAPI ni credenciales. Producción no usa Docker. El motor PHP es la fuente
predeterminada de `daily`, `range`, `directions`, `moon/instant`,
`altitude-profile` y `tonight`; la fuente alternativa es fallback simétrico.
Los eventos tienen selección separada por grupo (`api`, `database`, `php`,
`auto`, `compare`) y por ello una configuración productiva en `api` sí conserva
dependencia de la mini PC.

## Arquitectura histórica de la API

La API en `/srv/proyectos/astronomia/api` se construyó para centralizar efemérides
con FastAPI, Skyfield, DE421, kernels de orientación lunar y generadores de
eventos. Calcula datos diarios y por rango, direcciones, perfiles, Luna
instantánea e imagen, visibilidad nocturna, eventos, eclipses y un endpoint
experimental de tránsitos lunares satelitales. PostgreSQL 17 almacena
`astronomical_events`, ejecuciones de generadores y mapas/metadatos; no almacena
la configuración editorial de la web.

Endpoints observados: `/`, `/ping`, `/v1/health`, `/v1/moon/instant`,
`/v1/moon/image`, `/v1/astronomy/daily`, `/directions`, `/altitude-profile`,
`/range`, `/events`, `/tonight`, `/satellite-lunar-transits` y endpoints locales
derivados declarados en el mismo router. Su README es la fuente contractual.

Históricamente el hosting llegaba server-side a esta API mediante un hostname
publicado por Cloudflare Tunnel. Ni el tunnel ni `cloudflared` están en Compose;
dominio, credencial y políticas son configuración externa. El navegador nunca
debía acceder directamente. Hoy la API sigue activa en la mini PC para
comparación, fuentes configurables y fallback, pero no es necesaria para los
contratos generales cuando PHP funciona y está seleccionado.

## Contenedores observados

| Contenedor/servicio | Imagen/base | Puerto/red/volumen | Desarrollo | Producción | Estado |
|---|---|---|---|---|---|
| `web-astro` / `web` | build `php:8.5-apache` | `18080:80`; bind del repo a `/var/www/html` y su ruta absoluta | sí | no | activo, principal local |
| `api-astro` / `api-demo` | build `python:3.14-slim` | `18000:8000`; redes `api_default` y externa `servicios_backend` | opcional para fallback/comparación | sólo si una fuente usa API | activo, legado operativo |
| `postgres` | `postgres:17` | `5432`; red externa `servicios_backend` (también compartida con otro proyecto) | API/eventos | no para el motor PHP; sí para API | activo, Compose fuera del repo API |

El Compose web no define DB. `WEB_DB` y `STORE_DB` son conexiones externas
MySQL/MariaDB. No se atribuyen a Aquellas Lunas los demás contenedores activos de
la máquina.

## Producción y despliegue

- URL y ruta documentadas: `https://aquellaslunas.com.ar/astro/` y
  `/home8/aquellaslunascom/public_html/astro`.
- Servidor documentado: cPanel/Apache, Alt-PHP 8.5 mediante CGI/FastCGI.
- Configuración privada esperada:
  `/home8/aquellaslunascom/config/astronomia.php`, fuera de `public_html`.
- Publicación: `scripts/desplegar.sh`, FTPS explícito, sin `--delete` y con dry
  run. `vendor/` sólo se incluye con `--include-vendor` para Web Push.
- No se verificaron hosting ni rutas externas durante esta auditoría.

## Configuración y credenciales (sólo ubicaciones)

| Archivo/ubicación | Entorno | Uso | Repositorio/ignore |
|---|---|---|---|
| `web/.env` | local | entorno, API, DB, Push, tienda, Mercado Pago y opciones | fuera de Git por `.gitignore` |
| `web/.env.example` | plantilla | nombres y ejemplos no sensibles | versionado |
| `api/.env` | mini PC | PostgreSQL y opciones API | fuera de Git por el `.gitignore` de API |
| `/home8/aquellaslunascom/config/astronomia.php` | producción | API, DB, VAPID, administración, tienda y pagos | externo al repo y document root |
| credenciales FTPS consumidas por `scripts/desplegar.sh` | operación local | publicación cPanel | consultar el script/documentación; no registrar valores |
| configuración Cloudflare/Tunnel/Access | cuenta Cloudflare | DNS, proxy y posible acceso a API | externa; no hay archivo local verificable |
| `astronomy-engine/cache/satellite/tle-cache.json` | runtime | TLE públicos, no credenciales | ignorado por Git |

El ID público GA4 vive en `includes/analytics.php`; no es una credencial. No se
deben copiar valores de los archivos privados a documentación, HTML o logs.

## Cloudflare

El sitio público está documentado detrás del proxy Cloudflare y usa HTTPS. La
API fue publicada históricamente mediante Tunnel para llamadas PHP server-side.
No existen en los repositorios archivos que demuestren el estado actual de DNS,
modo SSL, certificados de origen, Page/Cache Rules, redirects, WAF, Analytics,
Access o Zero Trust. Deben auditarse en el panel externo. Compose no contiene
`cloudflared`; la API local sólo publica el puerto 18000 del host.

En consecuencia, sólo son afirmaciones verificables localmente: la aplicación
emite headers propios de no-cache donde corresponde, usa URLs HTTPS canónicas,
y su documentación presupone Cloudflare delante del origen. No se atribuyen a
Cloudflare reglas o seguridad no observables.

## Scheduler y migraciones

El cron debe invocar por CLI `scripts/run-scheduled-tasks.php`. El comando exacto
documentado para cPanel usa el PHP 8.5 de CloudLinux y timezone
`America/Argentina/Buenos_Aires`, pero debe confirmarse en producción. El script:

1. toma un lock no bloqueante en el directorio temporal;
2. crea tablas de control si faltan;
3. valida y ejecuta en orden migraciones `automatic` de
   `scripts/migrations/registry.php`;
4. bloquea los jobs si hay una migración manual marcada como bloqueante;
5. ejecuta un ciclo común de pruebas programadas y notificaciones astronómicas;
6. persiste inicio/fin/éxito/error sanitizado y escribe resumen UTC a stdout.

No hay reintentos internos: la siguiente ejecución del cron vuelve a evaluar.
El registro actual incluye migraciones Push, trazabilidad, menú, plantillas de
widgets, fotografía y entradas nuevas de menú.

`scripts/update-satellite-tles.php` es una tarea CLI independiente y fuerza una
descarga de ISS y Tiangong. El proveedor normal usa caché JSON con lock y TTL de
6 h; valida formato, checksum/catálogo mediante `TleParser` antes de reemplazar.
Si una resolución normal no puede actualizar, conserva el último TLE válido sin
límite duro, pero marca advertencia sobre la época a >24 h y baja confianza a
>72 h. El descargador PHP usa 8 s por defecto y conexión de hasta 4 s.

## PWA y Web Push

`manifest.webmanifest` fija identidad, scope y start URL `/astro/` e iconos
locales. `service-worker.js` atiende exclusivamente `push` y
`notificationclick`; no hay precache, caché offline ni actualización de assets.
`assets/js/install-prompt.js` adapta instalación normal, iOS y navegadores
embebidos, y emite eventos GA4.

Las suscripciones anónimas, configuración por dispositivo, preferencias,
catálogo de tipos, pruebas, historial y estado técnico viven en `WEB_DB` mediante
migraciones `web-push-*`. VAPID pública llega al navegador; la privada sólo al
servidor. Tipos comprobados: `moonrise`, `eclipse`, `lunar_conjunction`,
`satellite_transit` (ISS/Tiangong según evento) y `test` administrativo. Comparten
programación, No molestar, deduplicación y envío. `minishlink/web-push` llega por
Composer; `vendor/` no se versiona ni se despliega salvo opción explícita.

## Servicios externos

| Servicio | Uso | Criticidad/fallback |
|---|---|---|
| API FastAPI de mini PC | fuente/fallback astronómico configurable | prescindible para contratos migrados si PHP está seleccionado; requerida para tipos sin fallback o configuración `api` |
| MySQL/MariaDB `WEB_DB` | contenido, menú/config, Push y eventos persistidos | crítico para esas áreas; cálculos puros PHP no dependen de DB |
| MySQL/MariaDB `STORE_DB` | catálogo, pedidos y pagos de tienda | crítico sólo para tienda/galería comercial |
| CelesTrak | TLE ISS/Tiangong | caché local y último TLE válido como fallback |
| OpenStreetMap/Nominatim | mapa/geocodificación | ubicación por coordenadas sigue siendo posible; fallos se degradan |
| dataset local timezone-boundary-builder | zona IANA desde coordenadas | local, sin red; parte crítica del contexto de ubicación |
| Open-Meteo | nubosidad | mejora no crítica; se omite ante fallo y usa caché de sesión 30 min |
| Google Analytics 4 | métricas | no crítico; ID público fijo, sin consentimiento implementado en código observado |
| Mercado Pago | checkout/webhook de tienda | crítico sólo para compra; integración server-side |
| CDNs/Three.js | visualizaciones 3D | revisar assets vendor locales; cada superficie define fallback visual |

