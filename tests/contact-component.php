<?php

require_once __DIR__ . '/../includes/contact.php';

function contactAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

ob_start();
renderAstronomyContactBlock('about');
$about = ob_get_clean();
ob_start();
renderAstronomyContactBlock('features_page');
$features = ob_get_clean();

foreach ([$about, $features] as $html) {
    contactAssert(str_contains($html, 'Ayudame a mejorar Aquellas Lunas'), 'Falta el título.');
    contactAssert(str_contains($html, 'Aquellas Lunas está pensada para ser útil, clara y fácil de usar.'), 'Falta el contenido.');
    contactAssert(str_contains($html, '@aquellas_lunas'), 'Falta el usuario de Instagram.');
    contactAssert(str_contains($html, 'Escribir por Instagram'), 'Falta la acción.');
    contactAssert(str_contains($html, 'href="https://www.instagram.com/aquellas_lunas/"'), 'El destino no es el perfil esperado.');
    contactAssert(str_contains($html, 'target="_blank"'), 'El enlace no abre una pestaña nueva.');
    contactAssert(str_contains($html, 'rel="noopener noreferrer"'), 'Faltan protecciones del enlace externo.');
    contactAssert(substr_count($html, 'data-instagram-contact') === 1, 'La acción de Analytics falta o está duplicada.');
}
contactAssert(str_contains($about, 'data-contact-source="about"'), 'Origen incorrecto en Acerca.');
contactAssert(str_contains($features, 'data-contact-source="features_page"'), 'Origen incorrecto en funciones.');

$script = file_get_contents(__DIR__ . '/../assets/js/contact-analytics.js');
contactAssert(is_string($script) && str_contains($script, "'instagram_contact_click'"), 'Falta el evento de Analytics.');
contactAssert(str_contains($script, "destination: 'instagram_profile'"), 'Falta el destino de Analytics.');
contactAssert(!str_contains($script, 'preventDefault'), 'Analytics bloquea la navegación.');

echo "Contacto compartido: OK\n";
