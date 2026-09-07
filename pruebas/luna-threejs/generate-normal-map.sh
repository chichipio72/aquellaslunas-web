#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source_map="${1:-$script_dir/textures/ldem_3_8bit.jpg}"
target_map="${2:-$script_dir/textures/ldem_3_normal.png}"
work_dir="$(mktemp -d)"
trap 'rm -rf "$work_dir"' EXIT

# Three.js usa normales tangentes OpenGL: +X apunta hacia U creciente y +Y hacia
# V creciente. La imagen fuente tiene Y hacia abajo y TextureLoader la invierte al
# subirla a WebGL. Para n = normalize(-dh/du, -dh/dv, 1), esto exige -dH/dX en R
# y +dH/dY_imagen en G. Los signos no son una corrección estética: se deducen de
# esa transformación de coordenadas. ImageMagick rota 180° el kernel al ejecutar
# una convolución (no es una correlación), por eso los factores de escala de abajo
# tienen el signo opuesto al gradiente escrito. El sesgo de 50 % codifica cero.
magick "$source_map" -colorspace Gray -auto-level -depth 16 "$work_dir/height.png"
magick "$work_dir/height.png" -bias 50% -define convolve:scale='25%!' \
    -morphology Convolve '3x3: -1,0,1,-2,0,2,-1,0,1' "$work_dir/x.png"
magick "$work_dir/height.png" -bias 50% -define convolve:scale='-25%!' \
    -morphology Convolve '3x3: -1,-2,-1,0,0,0,1,2,1' "$work_dir/y.png"
dimensions="$(magick identify -format '%wx%h' "$work_dir/height.png")"
magick "$work_dir/x.png" "$work_dir/y.png" -size "$dimensions" xc:white \
    -channel RGB -combine -depth 8 "$target_map"

echo "Normal map generado: $target_map"
