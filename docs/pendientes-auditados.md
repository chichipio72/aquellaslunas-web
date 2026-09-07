# Pendientes auditados

Este listado incluye sólo deuda observada en código/documentación o límites que
requieren verificación externa. “Posible mejora” no significa compromiso.

## Pendientes confirmados

### Funcionales

- Completar el endpoint público de descarga y el envío al comprador de la tienda;
  el checkout y permiso existen, pero la entrega continúa registrada como
  pendiente en README/documentación vigente.
- Completar las decisiones pendientes expresas del Explorador documentadas en
  `explorador/README.md`, sin confundirlas con el laboratorio administrativo.

### Técnicos

- Mantener la validación comparativa del motor PHP y sus fixtures cuando se
  amplíen fechas, eventos o geometrías.
- Verificar que el cache TLE productivo sea escribible y que la tarea independiente
  de actualización esté realmente programada; no aparece en el scheduler común.
- La API y su repositorio tienen cambios locales sin commit: su estado operativo
  debe estabilizarse antes de considerarlo una referencia histórica congelada.

### Documentación

- Incorporar una exportación o captura aprobada de la configuración Cloudflare
  sin secretos: DNS, SSL, WAF, reglas, caché, Tunnel y Access.
- Confirmar desde cPanel las rutas Alt-PHP, comando/frecuencia de cron y ubicación
  real del archivo privado; hoy sólo hay evidencia en documentación/código.
- Documentar el esquema consolidado de `WEB_DB`. Actualmente se reconstruye desde
  migraciones e includes y `base_host.md` sólo cubre tienda.

### Infraestructura y operación

- Publicar y verificar en producción los archivos PHP/SQL de
  `20260906_lunar_scene_presets` y `20260906_publish_lunar_nodes`. La allowlist
  local ya está corregida, pero el estado del scheduler compartido indica que el
  hosting todavía no puede calcular la firma de la primera migración.
- Auditar el origen productivo después de cada despliegue porque FTPS no usa
  `--delete`; comprobar copias antiguas y rutas sensibles.
- Confirmar fuentes astronómicas persistidas en producción. El default general
  es PHP, pero eventos sin override caen a API y podrían conservar dependencia de
  la mini PC/Tunnel.
- Confirmar frecuencia real del cron principal y del actualizador TLE separado.

### Seguridad

- Verificar externamente que `.env`, `.git`, `docs`, `tests`, `scripts`, Docker,
  backups y logs respondan 403/404 en producción.
- Retirar el diagnóstico temporal ampliado del webhook una vez confirmado el
  flujo productivo, pendiente ya registrado en `docs/estado-actual.md` anterior.
- Revisar en la cuenta Cloudflare SSL/TLS, WAF, reglas y Access; no hay evidencia
  local suficiente para certificarlos.
- Evaluar endurecimiento administrativo proporcional al riesgo: actualmente hay
  una única credencial/sesión sin roles, segundo factor ni restricción general por
  IP, y no se registra autor por cada cambio. No constituye por sí solo una falla
  comprobada, pero concentra el acceso operativo.
- Revisar permisos del almacenamiento de sesiones PHP: la autorización de algunas
  herramientas técnicas reconoce la sesión administrativa persistida desde el
  servidor y depende de que esos archivos no sean accesibles por terceros.

### SEO y Analytics

- Verificar sitemap, robots, canonical y datos estructurados sobre la URL pública
  luego del próximo despliegue; esta auditoría fue local.
- Decidir explícitamente si GA4 requiere consentimiento/gestión de privacidad y
  si debe desactivarse en local. Hoy carga desde el include compartido.

### Limpieza legacy

- Evaluar —sin borrar durante esta auditoría— si la API FastAPI, PostgreSQL y el
  Tunnel pueden retirarse después de configurar todos los grupos en PHP/DB y
  demostrar que ningún tipo sin fallback los necesita.
- Conservar como históricos, o mover fuera del corpus operativo, los snapshots
  `docs/contenido/*`, `docs/docs/*` y prototipos de `pruebas/`.
- `acerca.php` parece una ruta de compatibilidad; confirmar tráfico/enlaces antes
  de retirarla.

## Deuda técnica

- README, `docs/arquitectura.md` y `AGENTS.md` acumulan mucho detalle repetido; el
  índice nuevo reduce el costo de búsqueda, pero una futura consolidación debe
  preservar contratos permanentes.
- La selección de fuentes tiene defaults distintos: cálculos generales a PHP y
  eventos a API cuando no hay configuración. Es válido pero fácil de interpretar
  mal; debe vigilarse en operación.
- No existe un check automático único de enlaces Markdown, rutas del índice y
  ausencia de secretos; la validación de esta auditoría se ejecutó con búsquedas
  específicas.

## Posibles mejoras registradas

- Automatización de navegador para responsive, modales, geolocalización, swipe y
  visualizaciones 3D.
- Política explícita de caché larga para assets en hosting.
- Incorporación futura de EOP/DUT1 real para satélites, sólo con fixtures y
  validación explícita; hoy DUT1 es configurable y cero por defecto.
