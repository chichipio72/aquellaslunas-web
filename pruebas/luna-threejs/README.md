# Prueba técnica: Luna 3D con relieve aparente

Prototipo aislado con Three.js. El modo predeterminado consulta `astronomy.php`,
que reutiliza el motor PHP portable para mostrar la Luna del instante vigente en
Buenos Aires. La prueba sigue aislada de la home y conserva un modo manual.

## Abrir localmente

Con el contenedor `web-astro` activo, visitar:

    http://localhost:18080/pruebas/luna-threejs/

No abrir `index.html` como `file://`: los módulos JavaScript y las texturas necesitan
un servidor HTTP.

## Modo astronómico

**Astronómico hoy / Buenos Aires** usa las coordenadas predeterminadas del proyecto
(`-34.53`, `-58.48`, elevación `0 m`) y `America/Argentina/Buenos_Aires`.
`get_current_datetime()` respeta primero la simulación temporal local existente y,
si no está activa, usa el reloj actual.

El endpoint combina:

- `MeeusLunarCalculator`: fase, fracción iluminada, posición y distancia;
- `MoonBrightLimbCalculator`: dirección topocéntrica del limbo brillante en la
  pantalla local (arriba = cenit, derecha = azimut creciente);
- `LunarLibrationCalculator`: libración óptica/física y ángulo de posición del
  polo lunar según Meeus, capítulo 53;
- `MoonDiskAppearanceCalculator`: paralaje del observador, libración topocéntrica
  y orientación final del norte lunar respecto del cenit.

Three.js orienta la esfera desde el punto subobservador y transforma el vector
selenográfico del punto subsolar con la misma rotación; la fase y el terminador
emergen de esos dos vectores. La textura lunar se centra con
`rotation.y = -90°`; después se aplican `-longitud_subobservador` alrededor de Y,
`+latitud_subobservador` alrededor de X y `-ángulo_norte_lunar` alrededor de Z.
La auditoría de signos, ejes y comparación con JPL Horizons está documentada en
[`GEOMETRY-AUDIT.md`](./GEOMETRY-AUDIT.md).

El modo **Manual** conserva todos los controles anteriores y restablece libración
y orientación del disco a cero. Los presets manuales cambian automáticamente a
ese modo.

## Recursos incluidos

- `vendor/three.module.min.js` y `vendor/three.core.min.js`: Three.js r185,
  descargado del paquete npm oficial a través de jsDelivr. Licencia MIT.
- `textures/lroc_color_2k.jpg`: mapa de color 2025 de LROC, 2048 × 1024.
- `textures/ldem_3_8bit.jpg`: mapa de elevación LOLA, 1024 × 512.
- `textures/ldem_3_normal.png`: normal map OpenGL de 1024 × 512 para el DEM JPEG.
- `textures/ldem_4_height.png` y `ldem_4_normal.png`: altura y normales de
  1440 × 720, derivados del TIFF LOLA de 4 píxeles por grado y 16 bits.
- `textures/ldem_8_height.png` y `ldem_8_normal.png`: altura y normales de
  2880 × 1440, reducidos desde el TIFF LOLA de 5760 × 2880 y 16 bits.

Las dos imágenes provienen del [CGI Moon Kit de NASA Scientific Visualization
Studio](https://svs.gsfc.nasa.gov/4720/). Crédito: NASA's Scientific Visualization
Studio; visualización de Ernie Wright (USRA), datos LRO/LROC/LOLA.

El DEM se usa como `bumpMap`: modifica las normales durante la iluminación para
producir relieve aparente, pero no deforma la silueta geométrica.

## Normal map derivado

`generate-normal-map.sh` genera el normal map con ImageMagick y filtros Sobel. El
mapa de salida indicado desde un DEM de entrada:

    bash pruebas/luna-threejs/generate-normal-map.sh entrada.tif salida.png

El canal rojo codifica `-dH/dX` y el verde `+dH/dY` de la imagen. Esos signos
producen `(-dH/du, -dH/dv, 1)` después del `flipY` que TextureLoader aplica al
subir la imagen a WebGL. Así, una depresión del DEM sigue siendo una depresión y
no se invierte para corregirla a ojo. El resultado es un mapa tangente RGB de 8
bits para comparación visual, no un producto topográfico científico.

NASA no publica un DEM exactamente 2K en este kit: ofrece 1024 × 512 en JPEG,
1440 × 720 en TIFF de 16 bits y luego 5760 × 2880. La interfaz permite elegir el
primer nivel, el de 1440 o una reducción 2:1 del producto 5760. Esta última sí
conserva detalle adicional real: no es una ampliación del mapa pequeño. Los mapas
altos se cargan sólo al seleccionarlos.

La esfera se rota −90° para colocar la longitud lunar 0° frente a la cámara. El
signo contrario que usaba la primera prueba mostraba la cara lejana, motivo
principal por el que los mares clásicos casi no se reconocían.

## Rangos de los controles

- ángulo horizontal: −180° a 180°;
- altura solar: −60° a 60°;
- luz principal: 0 a 8;
- luz ambiente: 0 a 1;
- `bumpScale`: 0 a 0,2;
- `normalScale X/Y`: −4 a 4;
- contraste: 0,5 a 2;
- brillo: 0,5 a 1,5;
- gamma: 0,5 a 2;
- saturación: 0 a 2;
- exposición: 0,5 a 2.

## Comparación y valores de referencia

El preset **Terminador / cráteres** usa 78° de ángulo horizontal, 5° de altura,
luz principal 3,2, relleno 0,005 y `normalScale` 1. Es el punto de partida
recomendado para el normal map. Un rango de 0,8 a 1,4 conserva un aspecto
razonable; valores mucho mayores son útiles sólo como diagnóstico.

Para el bump map conviene comenzar entre 0,025 y 0,05. Su máximo de interfaz
permite detectar orientación y respuesta, pero no pretende ser físicamente real.

El nivel medio suma 1.158.008 bytes entre altura y normales. El nivel alto suma
3.590.934 bytes y en la validación headless local cargó ambos mapas en 84 ms. Son
tiempos orientativos de localhost, no una estimación de descarga por Internet.
Ambos modos realizan una lectura adicional de textura por fragmento y no mostraron
una diferencia de fluidez perceptible en esta escena de una sola esfera.

Los ajustes de contraste, brillo, gamma y saturación son uniformes del material:
no duplican ni alteran el albedo NASA. El preset **Luna más realista** selecciona
el DEM alto, normal map, contraste 1,25, brillo 0,9, gamma 0,9, saturación 0,65 y
exposición 1,05.
