# Estado actual verificado

Fecha de revisión documental: 2026-08-01.

## Implementado en el árbol web

- nueve vistas públicas: Inicio, Esta noche, Sol y Luna, Planificador, Eventos, Eclipses, Ubicación, Galería y Acerca;
- navegación centralizada; Galería está temporalmente fuera del menú y Eclipses fuera del swipe;
- ubicación global persistida en cookies y fallback Buenos Aires;
- consumo server-side de FastAPI, proxies de imagen lunar, perfiles y direcciones;
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
