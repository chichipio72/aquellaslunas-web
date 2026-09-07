# Fichas piloto de geología lunar

## Alcance

La prueba vive en `assets/data/moon-geology-experiment.json` y no está conectada a
la Luna interactiva. Se construyó antes de definir el esquema definitivo y conserva
campos nulos cuando no se encontró respaldo suficiente.

## Resultado observado

Los campos sólidos en casi todos los accidentes nombrados fueron identidad IAU,
tipo, coordenadas, dimensión nominal, procedencia del nombre y una interpretación
general del proceso. En los alunizajes la identidad procede de NASA/USGS, no del
Gazetteer.

No apareció una ficha geológica universal. Surgieron cuatro perfiles:

- cráteres: impacto, clase morfológica, edad o sistema, rayos, terrazas, pico,
  fundido y métricas piso-borde;
- mares y cuencas: evento de cuenca, múltiples unidades de relleno, anillos,
  estratigrafía regional y edades que no deben condensarse en una cifra;
- montes, valles y rimas: geometría lineal o poligonal, relieve local, relación
  tectónica/volcánica y métricas variables a lo largo del accidente;
- sitios de misión: múltiples unidades locales, estaciones, muestras, métodos de
  datación y hallazgos, sin diámetro propio.

## Campos difíciles

- La unidad del mapa USGS 1:5M no se asignó todavía. Hace falta una unión espacial
  validada en coordenadas lunares; el punto central además puede ser engañoso para
  objetos extensos.
- Elevación, profundidad y altura no están listas en el Gazetteer. Conviene
  derivarlas localmente desde LOLA/SLDEM, guardando raster, resolución, datum,
  máscara y método.
- Las edades absolutas son escasas y a menudo indirectas. Copernicus se vincula
  con muestras Apollo 12 y Tycho con Apollo 17; ambas asociaciones deben seguir
  mostrándose como interpretación, no como muestreo directo del cráter.
- El diámetro IAU es una dimensión nominal. No siempre coincide con longitud,
  ancho, anillo de cuenca o superficie geológica.

## Fuentes que resultaron útiles

El Gazetteer fue sólido para identidad, pero no para geología. El mapa unificado
USGS es la base correcta para contexto y estratigrafía regional. NASA PDS/LOLA es
la fuente apropiada para cálculos topográficos reproducibles. Las publicaciones
LRO/NASA aportaron rasgos y edades de cráteres; las bases LPI fueron especialmente
útiles para cráteres y rimas. Los mapas y documentos de misión fueron
imprescindibles para Apollo 11 y 17.

## Relaciones entre fuentes

La asociación por ID IAU es directa para accidentes nombrados. Los sitios Apollo
necesitan IDs locales. La cuenca Orientale reveló una ambigüedad importante: no es
la misma entidad que el nombre oficial Mare Orientale. Las uniones con catálogos
de cráteres deberán comprobar nombre, coordenadas y diámetro, y las uniones con
mapas geológicos deben conservar que son relaciones espaciales, no identidades.

## Cálculo local y curación

Conviene calcular localmente elevación, desnivel, profundidad, altura de borde,
pendiente y perfiles desde productos versionados. Deben curarse manualmente el
proceso de formación, la interpretación de edades, la selección de unidades para
objetos extensos, las relaciones entre cuencas y mares y los resúmenes editoriales.

## Esquema que emerge de las fichas

El resultado sugiere un sobre común pequeño —ID local, identidad externa,
geometría, fuentes y limitaciones— y bloques opcionales por dominio:
`chronology`, `geologic_context`, `topography`, `morphology`, `samples` y
`mission`. Cada afirmación cuantitativa necesita `evidence`, `method`, `sources`,
alcance espacial y, cuando corresponda, incertidumbre.

No conviene una tabla ancha con una columna por posible atributo. Una estructura
normalizada de afirmaciones con procedencia, acompañada por extensiones tipadas,
representa mejor los datos reales. Antes de cerrar ese modelo falta ejecutar la
unión GIS del mapa USGS y una prueba reproducible de métricas LOLA sobre al menos
un cráter, un monte y una rima.
