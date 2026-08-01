<?php

putenv('APP_ENV=local');
putenv('CONTENT_ENABLED_IN_PRODUCTION=false');
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/local-tools/content-editor/index.php?action=edit&slug=eclipses-lunares';
$_GET = ['action' => 'edit', 'slug' => 'eclipses-lunares'];
$_POST = [
    'mode' => 'edit',
    'original_slug' => 'eclipses-lunares',
    'active_tab' => 'facts',
    'csrf_token' => 'token-invalido',
    'version' => '1',
    'visible' => 'on',
    'titulo' => 'Título escrito pero no guardado',
    'resumen' => 'Resumen conservado.',
    'articulo' => "# Título\n\nTexto conservado.",
];

ob_start();
require __DIR__ . '/../local-tools/content-editor/index.php';
$html = (string) ob_get_clean();

function contentEditorSaveFailureAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

contentEditorSaveFailureAssert(str_contains($html, 'No se guardaron los cambios.'), 'Falta el aviso explícito de guardado fallido.');
contentEditorSaveFailureAssert(str_contains($html, 'Se encontraron <strong>1 error</strong>'), 'Falta la cantidad de errores.');
contentEditorSaveFailureAssert(str_contains($html, 'Título escrito pero no guardado'), 'El formulario perdió los valores enviados.');
contentEditorSaveFailureAssert(str_contains($html, 'id="editor-tab-content" class="editor-tab has-errors"'), 'La solapa con el error no quedó marcada.');
contentEditorSaveFailureAssert(str_contains($html, 'name="active_tab" value="facts"'), 'El guardado fallido perdió la solapa activa.');
$tabCounts = editorTabErrorCounts([
    astronomyContentError('titulo', 'Error de contenido.'),
    astronomyContentError('sabias_que.0.titulo', 'Error de tarjeta.'),
    astronomyContentError('trivias.0.opciones', 'Error de trivia.'),
]);
contentEditorSaveFailureAssert($tabCounts === ['content' => 1, 'facts' => 1, 'trivias' => 1], 'Los errores no se asignaron a sus solapas.');

putenv('APP_ENV');
putenv('CONTENT_ENABLED_IN_PRODUCTION');
echo "content-editor-save-failure: ok\n";
