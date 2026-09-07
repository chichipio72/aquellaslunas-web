# Auditoría de geometría lunar

## Convenciones

- Coordenadas selenográficas IAU para la Luna: latitud planetocéntrica y
  longitud positiva hacia el este, normalizada a `[-180°, +180°]`.
- Vector lunar fijo usado por el renderer: `x = este` sobre el ecuador,
  `y = norte`, `z = lon 0° / lat 0°`.
- La textura NASA SVS está centrada en longitud 0°, con norte arriba y
  longitud este creciendo hacia la derecha del raster.
- Los ángulos de posición astronómicos de JPL/Meeus crecen en sentido
  antihorario desde el norte celeste. Los ángulos de pantalla de la prueba
  crecen en sentido horario desde el cenit hacia azimut creciente. Por eso la
  conversión correcta es `ángulo_pantalla = norte_celeste_pantalla - PA`.

La libración topocéntrica en longitud/latitud es, para una Luna esférica, la
longitud/latitud del punto subobservador. La orientación del polo determina la
rotación de ese sistema selenográfico sobre la pantalla. El punto subsolar es
la dirección Luna→Sol expresada en el mismo sistema fijo lunar. El terminador
es el gran círculo de puntos cuya normal tiene producto escalar cero con esa
dirección.

## Fuente independiente

Referencia: NASA/JPL Horizons, objetivo `301` (Luna), centro topocéntrico
`coord@399`, coordenadas geodéticas `-58.48, -34.53, 0 km`, cantidades de tabla
de observador 10, 14, 15, 16 y 17. Consulta realizada el 17 de agosto de 2026.
Horizons usa longitud positiva hacia el este para la Luna.

| UTC | Caso | Parámetro | Motor | Horizons | Diferencia |
|---|---|---:|---:|---:|---:|
| 2026-08-17 11:30 | creciente | subobs lon / lat | +6.4272 / +4.8306 | +6.4177 / +4.8041 | +0.0095 / +0.0265° |
| | | subsolar lon / lat | +125.9871 / +0.1195 | +125.9721 / +0.1356 | +0.0150 / -0.0161° |
| | | iluminación | 25.4298% | 25.4341% | -0.0043 pp |
| | | polo / subsolar PA | 20.8428 / 293.7137 | 20.8368 / 293.7100 | +0.0060 / +0.0037° |
| 2026-08-20 11:30 | cuarto creciente | subobs lon / lat | +3.6218 / +6.0789 | +3.6244 / +6.0538 | -0.0026 / +0.0251° |
| | | subsolar lon / lat | +89.3170 / +0.0740 | +89.3043 / +0.0758 | +0.0128 / -0.0018° |
| | | iluminación | 53.7388% | 53.7524% | -0.0136 pp |
| | | polo / subsolar PA | 12.0859 / 281.7030 | 12.0755 / 281.6900 | +0.0104 / +0.0130° |
| 2026-08-24 11:30 | gibosa creciente | subobs lon / lat | -1.7074 / +3.7186 | -1.7120 / +3.7001 | +0.0046 / +0.0185° |
| | | subsolar lon / lat | +40.5069 / +0.0037 | +40.4973 / -0.0113 | +0.0096 / +0.0150° |
| | | iluminación | 86.9541% | 86.9569% | -0.0028 pp |
| | | polo / subsolar PA | -6.9994 / 258.9168 | -7.0035 / 258.9100 | +0.0041 / +0.0068° |
| 2026-09-05 11:30 | cuarto menguante | subobs lon / lat | -0.6959 / -6.8188 | -0.6949 / -6.8410 | -0.0011 / +0.0222° |
| | | subsolar lon / lat | -105.7709 / -0.3867 | -105.7854 / -0.3768 | +0.0144 / -0.0099° |
| | | iluminación | 37.1282% | 37.1149% | +0.0133 pp |
| | | polo / subsolar PA | -1.6251 / 90.6038 | -1.6158 / 90.6100 | -0.0093 / -0.0062° |

Las diferencias son compatibles con el modelo Meeus truncado del motor frente a
la efeméride y el modelo de rotación de alta precisión de Horizons. Horizons
incluye además tiempo-luz aparente; el motor portable no lo modela por completo.

## Transformación Three.js

1. La rotación base de la esfera (`rotation.y = -90°`) lleva el centro del mapa
   NASA (`lon=0°, lat=0°`) al eje `+Z`, hacia la cámara.
2. La esfera se orienta con `Ry(-lon_subobservador)` y
   `Rx(+lat_subobservador)`.
3. El disco completo se gira con `Rz(-norte_lunar_pantalla)`.
4. El vector fijo subsolar `(x,y,z)` recibe exactamente esas mismas tres
   rotaciones y se usa como posición de la luz direccional.
5. La fase resultante se verifica con `(1 + dot(subobservador, subsolar)) / 2`.

No se usa el porcentaje iluminado para construir el terminador: ahora es una
salida de comprobación de la geometría de los dos puntos.

## Caso fotográfico: Carapachay

Fixture local: 2026-08-16 19:01 (`2026-08-16T22:01Z`), latitud
`-34.532989356165835`, longitud `-58.537805002828605`.

| Parámetro | Motor | Horizons | Diferencia |
|---|---:|---:|---:|
| Iluminación | 19.83049% | 19.83387% | -0.00338 pp |
| Subobservador lon / lat | +5.57423 / +4.89356° | +5.56365 / +4.86626° | +0.01058 / +0.02730° |
| Subsolar lon / lat | +132.86009 / +0.12903° | +132.84476 / +0.14736° | +0.01533 / -0.01832° |
| Polo norte PA | 21.71166° | 21.70650° | +0.00516° |
| Subsolar PA | 295.58877° | 295.59000° | -0.00123° |
| Altura / azimut | 43.73631 / 291.49839° | 43.73722 / 291.50152° | -0.00092 / -0.00313° |

Referencias de superficie (centros IAU/USGS, longitud este positiva):

| Accidente | Lon / lat | Distancia firmada al terminador | Incidencia solar |
|---|---:|---:|---:|
| Taruntius | +46.54 / +5.50° | +3.6753° | 86.3247° |
| Mare Fecunditatis | +53.67 / -7.83° | +10.6900° | 79.3100° |
| Langrenus | +61.04 / -8.86° | +17.9346° | 72.0654° |

Los tres centros están geométricamente iluminados. Taruntius es la referencia
más sensible: está sólo 3.68° dentro del lado iluminado. El borde visible real
de un cráter no tiene por qué coincidir con el terminador de la esfera: altura
del borde, profundidad, pendiente, seeing, enfoque y procesado fotográfico
pueden desplazar el primer píxel visible sin alterar la geometría global.
