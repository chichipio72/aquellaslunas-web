# Estado actual verificado

Fecha de revisión documental: 2026-08-03.

## Implementado en el árbol web

- nueve vistas públicas: Inicio, Esta noche, Sol y Luna, Planificador, Eventos, Eclipses, Ubicación, Galería y Acerca;
- navegación centralizada; Galería está temporalmente fuera del menú y Eclipses fuera del swipe;
- ubicación global persistida en cookies y fallback Buenos Aires;
- motor PHP portable y API FastAPI seleccionables como fuente primaria de cálculos generales, con fallback simétrico;
- eventos configurables por grupo con `api`, `database`, `php`, `auto` y `compare`, y cobertura MariaDB 1900–2050;
- eclipses API/DB/PHP normalizados, con enriquecimiento local PHP para eventos globales de MariaDB;
- `moon/image` seleccionable entre 404 PNG precalculados y API, con PNG pequeño final, orientación apparent por CSS y sin GD en runtime;
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
- Laboratorio con actualización automática, análisis recordados y endpoint privado.

## Probado localmente durante esta auditoría

- sintaxis de todos los PHP del árbol;
- presentación de ubicación, eclipses, libraciones y situación lunar;
- administración, galería, checkout, configuración de descargas, sincronización y previews;
- webhook y validación de configuración/diagnóstico de Mercado Pago;
- `solar-eclipse-2027-02-06.gif` servido como `image/gif` después de normalizar permisos;
- ausencia de errores de whitespace con `git diff --check` y sintaxis de `scripts/desplegar.sh`.

Las pruebas HTTP dependen de que Docker y la API local estén activos. Los comandos reproducibles están en `entorno-local.md`.

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
