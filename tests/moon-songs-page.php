<?php

declare(strict_types=1);

function moonSongsAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SCRIPT_NAME'] = '/canciones-a-la-luna.php';
ob_start();
require __DIR__ . '/../canciones-a-la-luna.php';
$html = (string) ob_get_clean();

$notifications = strpos($html, '>Configurar notificaciones</a>');
$songs = strpos($html, '>Canciones a la Luna</a>');
$capabilities = strpos($html, '>Qué ofrece Aquellas Lunas</a>');
moonSongsAssert($notifications !== false && $songs !== false && $capabilities !== false
    && $notifications < $songs && $songs < $capabilities, 'La entrada no quedó en la posición solicitada.');
moonSongsAssert(str_contains($html, 'aria-current="page">Canciones a la Luna</a>'), 'El menú no marca la página activa.');
moonSongsAssert(str_contains($html, 'open.spotify.com/embed/playlist/2RgMG2nxWdfT51Gz1bZvKe'), 'Falta el reproductor de Spotify.');
moonSongsAssert(str_contains($html, 'loading="lazy"') && str_contains($html, 'title="Playlist Canciones a la Luna en Spotify"'), 'El iframe no conserva accesibilidad o carga diferida.');
moonSongsAssert(str_contains($html, 'height="704"'), 'El reproductor no conserva la altura ampliada.');
moonSongsAssert(str_contains($html, 'https://aquellaslunas.com.ar/astro/canciones-a-la-luna.php'), 'Falta la canonical pública.');
moonSongsAssert(!str_contains($html, 'data-mobile-swipe-navigation="enabled"'), 'La página complementaria entró en el recorrido swipe.');

$sitemap = file_get_contents(__DIR__ . '/../sitemap.php');
moonSongsAssert(is_string($sitemap) && str_contains($sitemap, "'/canciones-a-la-luna.php'"), 'La página no está incorporada al sitemap.');

echo "OK canciones a la Luna\n";
