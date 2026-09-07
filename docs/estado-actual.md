# Estado actual verificado

Fecha de revisión documental: 2026-09-04.

## Implementado en el árbol web

- catálogo público ampliado: Inicio, El cielo hoy/esta noche, calendario, eventos,
  eclipses, planificador, fotografía, Luna favorita/interactiva, ISS/Tiangong,
  Explorador, contenidos, notificaciones, ubicación y páginas institucionales;
- navegación centralizada en MySQL con catálogo de respaldo en `includes/site-menu.php`;
- ubicación global persistida en cookies y fallback Buenos Aires;
- motor PHP portable y API FastAPI seleccionables como fuente primaria de cálculos generales, con fallback simétrico;
- eventos configurables por grupo con `api`, `database`, `php`, `auto` y `compare`, y cobertura MariaDB 1900–2050;
- eclipses API/DB/PHP normalizados, con enriquecimiento local PHP para eventos globales de MariaDB;
- superficies Three.js delimitadas para portada, El cielo hoy, fecha favorita y
  Luna interactiva; PNG/API preservados como fallback y para otras lunas;
- diagnóstico astronómico común para API, PHP, MariaDB y colección estática, con fuente solicitada/usada, fallback, tiempos comparables y tiempo PHP total de página;
- selección interna de grupos lunares: cada consulta PHP calcula sólo fases, ápsides, nodos, libraciones o conjunciones solicitadas, conservando la caché exacta;
- administración de fuentes generales y de eventos, con acciones globales no persistentes hasta guardar;
- panel administrativo con nombres alineados al menú y favicon compartido con la web pública;
- tarjeta nocturna resumida y página detallada con planetas, Luna, estrellas y cúmulos;
- eventos generales y vista específica de eclipses;
- mapas mundiales locales desde `assets/images/eclipses/`, sin hotlink;
- galería, administración privada, sincronización y generación de previews;
- creación server-side de preferencias de Mercado Pago y procesamiento firmado del webhook;
- creación de permisos en `descargas` tras un pago aprobado.
- contenido público y editor administrativo respaldados por MySQL (`WEB_DB`);
- importación editorial JSON con validación previa y sin sobrescritura;
- administración de visibilidad de secciones, tipos de eventos y reglas/mensajes;
- defaults editoriales en catálogos PHP y overrides opcionales en MySQL;
- Laboratorio privado y Explorador público autocontenido con NDJSON progresivo;
- PWA y Web Push para salida lunar, eclipses, conjunciones, tránsitos satelitales y pruebas;
- motor satelital PHP con TLE, SGP4 e interfaz ISS/Tiangong.

## Alcance de la auditoría 2026-09

Se auditó código, configuración versionable, Compose, documentación, rutas,
migraciones, scheduler y tests. Las pruebas de esta revisión se informan en la
entrega y no se confunden con pruebas históricas. Véase
`auditoria-documentacion-2026-09.md`.

## Despliegue

El mecanismo FTPS está implementado y documentado. Incluye `assets/images/eclipses/` aunque sus GIF estén ignorados por Git y no usa `--delete`. Esta auditoría no ejecutó un despliegue real ni verificó por credenciales el contenido remoto; no debe inferirse que cada cambio local ya esté publicado.

El servidor local monta el repositorio completo y no representa la selección FTPS de producción. El script excluye `.env`, `.git`, documentación, pruebas, scripts, herramientas locales y archivos Docker; como no usa `--delete`, el hosting debe auditarse por separado para detectar copias antiguas o sensibles.

## Pendiente

- endpoint público de descarga y envío al comprador;
- retirar el diagnóstico temporal ampliado del webhook una vez confirmado el flujo productivo;
- automatización de navegador para responsive, modales, popovers, geolocalización y swipe;
- política explícita de caché larga para assets en el hosting;
- opción para desactivar Analytics en desarrollo, si se decide incorporarla;
- verificación productiva posterior a cada despliegue, incluidos mapas y permisos.
- verificación externa de Cloudflare, cron cPanel y fuentes productivas;
- documentación consolidada del esquema `WEB_DB`.
