# Material histórico de contenidos

Los archivos PHP de esta carpeta son fuentes históricas de la migración inicial. No son la fuente pública vigente, no los lee el editor y no se despliegan: `scripts/desplegar.sh` excluye todo `docs/`.

El contenido operativo vive en MySQL (`WEB_DB`), se administra desde `/admin/contenidos/` y se publica mediante `includes/content-database.php` e `includes/content-system.php`. Para cargas nuevas se usa la importación editorial JSON del administrador. El script `scripts/migrations/import-content-to-web-db.php` se conserva únicamente para reproducir o auditar la migración histórica.
