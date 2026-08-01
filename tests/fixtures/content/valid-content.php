<?php

return [
    'version' => 1,
    'visible' => true,
    'titulo' => 'Contenido válido',
    'resumen' => 'Resumen válido para probar el cargador.',
    'palabras_clave' => ['Luna'],
    'relaciones' => ['luna'],
    'sabias_que' => [[
        'id' => 'sq-01',
        'visible' => true,
        'titulo' => '¿Sabías que esta entrada es válida?',
        'respuesta' => 'Sí, porque cumple el contrato completo.',
        'imagen' => null,
        'referencia' => ['articulo' => 'articulo-heredado-inexistente', 'ancla' => 'ancla-inexistente'],
    ]],
    'trivias' => [[
        'id' => 'tr-01',
        'visible' => true,
        'pregunta' => '¿Cuál es la opción correcta?',
        'imagen' => null,
        'opciones' => [
            ['texto' => 'Incorrecta'],
            ['texto' => 'Correcta', 'explicacion' => 'Es la respuesta válida.'],
        ],
        'referencia' => ['articulo' => 'articulo-heredado-inexistente', 'ancla' => 'ancla-inexistente'],
    ]],
    'articulo' => <<<'MD'
# Contenido válido

## Sección {#seccion}

Texto con **énfasis**.

[[trivia id="tr-01"]]
MD,
];
