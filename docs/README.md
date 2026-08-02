# Índice de documentación

Documentos operativos vigentes:

- [Arquitectura](arquitectura.md): límites entre PHP/MySQL y API/PostgreSQL, componentes públicos y administración.
- [Configuración](configuracion.md): variables, configuración privada del hosting, `APP_ENV`, `STORE_DB` y `WEB_DB`.
- [Entorno local](entorno-local.md): Docker Compose y verificaciones reproducibles en la mini PC.
- [Despliegue](despliegue.md): publicación FTPS, exclusiones y comprobaciones que deben hacerse en el hosting.
- [Administración y Laboratorio](administracion-y-laboratorio.md): autenticación, módulos de `/admin/`, editor/importador y consultas del laboratorio.
- [Reglas editoriales](reglas-editoriales-astronomia.md): defaults, overrides, tipos de eventos y frontera de la lógica técnica.
- [Estado actual](estado-actual.md): capacidades verificadas y pendientes reales.
- [Esquema de tienda](base_host.md): referencia SQL histórica/operativa de `STORE_DB`; no sustituye migraciones ni describe `WEB_DB` completo.

`contenido/*.php` y `docs/contenido-que-podes-hacer.php` son instantáneas históricas de la etapa previa a MySQL. No son documentación operativa, fuente pública ni archivos desplegados. El estado vigente del contenido está en Arquitectura y Administración y Laboratorio.
