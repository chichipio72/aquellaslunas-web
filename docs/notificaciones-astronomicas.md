# Notificaciones astronómicas

El cron único `scripts/run-scheduled-tasks.php` procesa pruebas programadas y
cuatro tipos públicos desde el catálogo `web_push_notification_types`:

- `moonrise`: 15 minutos antes, con No molestar `omit`;
- `eclipse`: 08:00 local del día anterior, con `postpone`;
- `lunar_conjunction`: 08:00 local del mismo día, con `postpone`;
- `satellite_transit`: 60 minutos antes, con `omit`.

`includes/web-push-astronomy-providers.php` adapta cada fuente al contrato
común `notification_type`, `event_key`, `event_time_utc` y datos de plantilla.
El procesador común resuelve programación, silencio, deduplicación, render,
envío e historial.

## Relevancia

Un eclipse se notifica sólo cuando `AstronomyEventsFacade` devuelve
`details.local.visible === true`. La clave usa grupo, clasificación local o
global estable y máximo global canónico.

Una conjunción debe tener `object_kind=planet` y separación mínima menor o
igual al parámetro `maximum_separation_deg` (3° inicialmente). Su instante
canónico es el mínimo entregado por la fachada.

Un evento satelital debe pertenecer a ISS o Tiangong, apuntar al Sol o la Luna
y estar clasificado `transit` o `very_close`. `close`, `near_pass` y `none` no
se notifican. El TLE sigue entrando exclusivamente por
`CachedCelesTrakTleProvider`.

## Silencio y cadencia

`postpone` mueve la entrega al final del silencio sólo si la demora no supera
12 horas y todavía es anterior al evento. Eclipse se evalúa cada 15 minutos;
conjunciones y satélites cada 5; moonrise cada minuto. El lookback de eclipse
es 16 minutos y el de los demás tipos 6. Los resultados se reutilizan dentro
del ciclo por tipo, coordenadas, zona horaria y parámetros.

La URL satelital inicial es la portada (`./index.php`), porque allí ya se
presentan los eventos y todavía no existe una página pública específica.

