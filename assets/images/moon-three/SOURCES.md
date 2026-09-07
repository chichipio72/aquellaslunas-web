# Fuentes de los mapas astronómicos

## NASA Blue Marble 2048

- Archivo local: `nasa-blue-marble-2048.png`
- Resolución: 2048 × 1024 px
- Producto: Blue Marble, mosaico global de color verdadero construido con
  observaciones MODIS del satélite Terra.
- Fuente oficial: https://svs.gsfc.nasa.gov/2915/
- Recurso descargado: https://svs.gsfc.nasa.gov/vis/a000000/a002900/a002915/bluemarble-2048.png
- SHA-256: `ae6214b078ed0864c96f74bcb10ae3021f6eb116f8059797efe0fa9ea8b89d35`

## Normal map lunar global 8K

- Archivo local: `lola_64ppd_normal_8k.png`
- Resolución: 8192 × 4096 px, PNG de 8 bits.
- Fuente topográfica: DEM global LOLA `ldem_64_uint.tif`, 23040 × 11520,
  64 píxeles por grado y 16 bits; alturas en medios metros respecto de la esfera
  lunar de 1737,4 km.
- Fuente oficial: https://svs.gsfc.nasa.gov/4720/
- Recurso: https://svs.gsfc.nasa.gov/vis/a000000/a004700/a004720/ldem_64_uint.tif
- SHA-256 fuente: `0f40bce8b42864deddb6943a38474879e691d5b20647aa5e54c2612b23106499`
- SHA-256 normal map: `37f644fb3967c2f3ab27cc12f9b2c03a4dcfeb33a6d2ef0ab9e017aed9f0cca3`
- Derivación: `tools/generate-moon-normal-map-8k.sh`; reducción Lanczos directa
  del DEM de 64 ppd a 8K y gradientes Sobel con convención tangente OpenGL.
