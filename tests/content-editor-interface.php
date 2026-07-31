<?php

putenv('APP_ENV=local');
putenv('CONTENT_ENABLED_IN_PRODUCTION=false');
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/local-tools/content-editor/index.php?action=edit&slug=eclipses-lunares&tab=facts';
$_GET = ['action' => 'edit', 'slug' => 'eclipses-lunares', 'tab' => 'facts'];

ob_start();
require __DIR__ . '/../local-tools/content-editor/index.php';
$html = (string) ob_get_clean();

function contentEditorInterfaceAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

contentEditorInterfaceAssert(substr_count($html, ' role="tab"') === 3, 'El editor no renderizó exactamente tres solapas.');
contentEditorInterfaceAssert(substr_count($html, ' role="tabpanel"') === 3, 'Las solapas no tienen sus tres paneles ARIA.');
contentEditorInterfaceAssert(
    str_contains($html, 'name="active_tab" value="facts" data-active-tab')
        && str_contains($html, 'id="editor-tab-facts" class="editor-tab is-active"')
        && str_contains($html, 'id="editor-panel-facts" class="editor-tab-panel" role="tabpanel" aria-labelledby="editor-tab-facts">'),
    'El editor no restauró la solapa “Sabías que” solicitada.'
);
contentEditorInterfaceAssert(str_contains($html, 'data-collection="fact"'), 'Falta la colección maestro-detalle de “Sabías que…”.');
contentEditorInterfaceAssert(str_contains($html, 'data-collection="trivia"'), 'Falta la colección maestro-detalle de trivias.');
contentEditorInterfaceAssert(str_contains($html, 'data-item-select="1"'), 'La lista compacta no contiene todos los elementos.');
contentEditorInterfaceAssert(str_contains($html, 'data-item-editor="1" hidden'), 'Los editores no seleccionados quedaron desplegados.');
contentEditorInterfaceAssert(str_contains($html, 'Todavía no hay trivias.'), 'Falta el estado vacío de trivias.');
contentEditorInterfaceAssert(str_contains($html, 'data-confirm-dialog'), 'La eliminación no usa confirmación integrada.');
contentEditorInterfaceAssert(str_contains($html, 'data-image-choose'), 'El selector visual no está disponible dentro de los paneles.');
contentEditorInterfaceAssert(str_contains($html, 'data-focal-surface'), 'Falta el selector visual del punto focal.');
contentEditorInterfaceAssert(str_contains($html, 'name="imagen_posicion_x"'), 'Falta la persistencia X del punto focal.');
contentEditorInterfaceAssert(str_contains($html, 'name="imagen_posicion_y"'), 'Falta la persistencia Y del punto focal.');
contentEditorInterfaceAssert(
    str_contains($html, 'aria-label="Vista previa de cabecera 16:9 sin recorte significativo"')
        && str_contains($html, 'data-focal-controls hidden')
        && str_contains($html, 'data-featured-ratio-warning hidden'),
    'La preview de la imagen principal 16:9 real no desactivó el ajuste de recorte.'
);
contentEditorInterfaceAssert(str_contains($html, 'editor-image-option__format'), 'La galería no distingue el formato de las imágenes.');
contentEditorInterfaceAssert(
    str_contains($html, 'data-image-block-builder')
        && str_contains($html, 'data-image-block-position')
        && str_contains($html, 'data-insert-image-block'),
    'El editor no ofrece el asistente para insertar un bloque con imagen.'
);

$javascript = file_get_contents(__DIR__ . '/../local-tools/content-editor/editor.js');
contentEditorInterfaceAssert(str_contains($javascript, "event.key === 'ArrowRight'"), 'Falta navegación por flechas.');
contentEditorInterfaceAssert(str_contains($javascript, "'beforeunload'"), 'Falta advertencia por cambios sin guardar.');
contentEditorInterfaceAssert(str_contains($javascript, "tab.id.replace('editor-tab-', '')"), 'El cambio de solapa no actualiza el valor persistido.');
contentEditorInterfaceAssert(
    str_contains($javascript, "'pageshow'") && str_contains($javascript, 'event.persisted'),
    'El editor no recarga una edición obsoleta restaurada desde el historial.'
);
contentEditorInterfaceAssert(!str_contains($javascript, 'window.confirm'), 'La eliminación conserva un diálogo nativo sin integrar.');
contentEditorInterfaceAssert(str_contains($javascript, '[[bloque-imagen src="${image}"'), 'El editor no construye la sintaxis del bloque automáticamente.');

$editorStyles = file_get_contents(__DIR__ . '/../local-tools/content-editor/editor.css');
contentEditorInterfaceAssert(
    str_contains($editorStyles, '.editor-focal__surface img { width: auto; max-width: 100%; height: auto; max-height: 100%;')
        && str_contains($editorStyles, '.editor-image-option img { width: auto; max-width: 100%; height: auto;'),
    'Las vistas previas del editor todavía fuerzan la ampliación de imágenes.'
);

$protectedPhotosJavascript = file_get_contents(__DIR__ . '/../assets/js/protected-photos.js');
contentEditorInterfaceAssert(
    str_contains($protectedPhotosJavascript, "'contextmenu'")
        && str_contains($protectedPhotosJavascript, "'dragstart'")
        && str_contains($protectedPhotosJavascript, "closest('.js-protected-photo')"),
    'La protección cliente no quedó limitada a las fotografías de contenido.'
);

putenv('APP_ENV');
putenv('CONTENT_ENABLED_IN_PRODUCTION');
echo "content-editor-interface: ok\n";
