# Índice maestro de documentación

Documento de entrada para contexto externo:

- [Contexto compacto de Aquellas Lunas](CONTEXTO_PROYECTO_AQUELLAS_LUNAS.md): mapa autocontenido para cargar como fuente en ChatGPT.

Documentos operativos vigentes:

- [Auditoría documental 2026-09](auditoria-documentacion-2026-09.md): inventario, inconsistencias, evidencia y límites de verificación.
- [Infraestructura y operación](infraestructura-y-operacion.md): producción/local, API histórica, Cloudflare, contenedores, configuración, scheduler, PWA y dependencias.
- [Funcionalidades y motor](funcionalidades-y-motor.md): páginas públicas, capacidades PHP, Explorador, Luna, SEO y Analytics.

- [Arquitectura](arquitectura.md): límites entre PHP/MySQL y API/PostgreSQL, componentes públicos y administración.
- [Configuración](configuracion.md): variables, configuración privada del hosting, `APP_ENV`, `STORE_DB` y `WEB_DB`.
- [Entorno local](entorno-local.md): Docker Compose y verificaciones reproducibles en la mini PC.
- [Despliegue](despliegue.md): publicación FTPS, exclusiones y comprobaciones que deben hacerse en el hosting.
- [Administración y Laboratorio](administracion-y-laboratorio.md): autenticación, módulos de `/admin/`, editor/importador y consultas del laboratorio.
- [Reglas editoriales](reglas-editoriales-astronomia.md): defaults, overrides, tipos de eventos y frontera de la lógica técnica.
- [Estado actual](estado-actual.md): capacidades verificadas y pendientes reales.
- [Pendientes auditados](pendientes-auditados.md): pendientes confirmados, deuda técnica y posibles mejoras explícitas.
- [Esquema de tienda](base_host.md): referencia SQL histórica/operativa de `STORE_DB`; no sustituye migraciones ni describe `WEB_DB` completo.

`contenido/*.php` y `docs/contenido-que-podes-hacer.php` son instantáneas históricas de la etapa previa a MySQL. No son documentación operativa, fuente pública ni archivos desplegados. El estado vigente del contenido está en Arquitectura y Administración y Laboratorio.

Documentación específica fuera de esta carpeta:

- [`../astronomy-engine/README.md`](../astronomy-engine/README.md): motor PHP portable.
- [`../astronomy-engine/ENTREGA.md`](../astronomy-engine/ENTREGA.md): inventario acumulativo y validación del motor.
- [`../explorador/README.md`](../explorador/README.md): Explorador público y contrato NDJSON.
- [`../content-data/README.md`](../content-data/README.md): fuentes editoriales JSON.
- [`../assets/images/moon-three/SOURCES.md`](../assets/images/moon-three/SOURCES.md): fuentes de texturas lunares.
