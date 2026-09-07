#!/usr/bin/env bash
set -euo pipefail

if [ "$#" -ne 2 ]; then
    echo "Uso: $0 DEM_LOLA_64PPD.tif normal-map-8k.png" >&2
    exit 2
fi

source_map="$1"
target_map="$2"
work_dir="$(mktemp -d)"
trap 'rm -rf "$work_dir"' EXIT

# La fuente oficial es 23040x11520 y 16 bits. Se reduce directamente a 8K antes
# del Sobel: nunca se interpola uno de los mapas web existentes.
magick "$source_map" -filter Lanczos -resize '8192x4096!' -colorspace Gray \
    -auto-level -depth 16 "$work_dir/height.png"

# Convención tangente OpenGL usada por Three.js. TextureLoader invierte V al
# subir la imagen; por eso R=-dH/dX y G=+dH/dY en coordenadas de la imagen.
# HorizontalTile hace continua la derivada en la unión ±180°.
magick "$work_dir/height.png" -virtual-pixel HorizontalTile -bias 50% \
    -define convolve:scale='25%!' \
    -morphology Convolve '3x3: -1,0,1,-2,0,2,-1,0,1' "$work_dir/x.png"
magick "$work_dir/height.png" -virtual-pixel HorizontalTile -bias 50% \
    -define convolve:scale='-25%!' \
    -morphology Convolve '3x3: -1,-2,-1,0,0,0,1,2,1' "$work_dir/y.png"
magick "$work_dir/x.png" "$work_dir/y.png" -size 8192x4096 xc:white \
    -channel RGB -combine -depth 8 -define png:color-type=2 \
    -define png:compression-level=9 "$target_map"

magick identify -format 'Normal map generado: %f · %wx%h · %b\n' "$target_map"
