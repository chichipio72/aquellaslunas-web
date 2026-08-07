# Resolución geográfica de zona horaria

La ubicación compartida se persiste como un bloque formado por latitud,
longitud, elevación y zona horaria IANA. La zona nunca se acepta desde el
navegador: `astronomySaveLocationRequest()` la calcula antes de escribir el
bloque completo de cookies. Si el cálculo falla, no se modifica ninguna cookie.

El resolvedor local vive en `includes/timezone-resolver.php`. Usa el índice y
los polígonos `timezones-1970` empaquetados por
[`mamluk/geo-tz`](https://1x.ax/mamluk/library/geo-tz), un port PHP MIT de
`node-geo-tz`. Se eligió el conjunto `1970` porque conserva identificadores
territoriales IANA como `Europe/Madrid` y
`America/Argentina/Buenos_Aires`; el conjunto `now` agrupa zonas con reglas
futuras equivalentes y no sirve para identidad geográfica.

Los límites provienen de
[`timezone-boundary-builder`](https://github.com/evansiroky/timezone-boundary-builder)
y OpenStreetMap. Los metadatos de origen, la licencia MIT del lector y la
licencia ODbL de los datos se incluyen en `includes/timezone-geo/`.

Las cookies antiguas se vuelven a resolver al construir el contexto. Cuando la
zona no coincide, falta elevación o falta la versión del bloque, se reescriben
todas las piezas juntas. El dataset debe actualizarse de forma controlada ante
una nueva publicación upstream; nunca se consulta una API durante una petición.

