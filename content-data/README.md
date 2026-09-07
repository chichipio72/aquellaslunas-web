# Contenido editorial público en JSON

Los archivos que alimentan páginas públicas son `que-podes-hacer.json` y
`fuentes-y-creditos.json`. La carpeta
está bloqueada para solicitudes HTTP; PHP la lee directamente en el servidor.

`que-podes-hacer.json` usa `version: 1` y contiene:

- `page`: eyebrow, título e introducción;
- `main_label` y `feature_label`: etiquetas accesibles de ambas grillas;
- `main_cards`: tarjetas principales con `id`, `title`, `icon`, `blocks` y `link`;
- `feature_cards`: tarjetas especiales con `id`, estilo controlado, bloques y acción;
- `closing`: título y párrafos finales.

Cada elemento de `blocks` es uno de estos dos formatos:

```json
{ "type": "paragraph", "text": "Texto" }
{ "type": "list", "items": ["Primer ítem", "Segundo ítem"] }
```

Para cambiar el orden, mové el objeto completo dentro de su arreglo. Para agregar
una tarjeta, copiá una existente, asignale un `id` único y editá sus bloques. Los
enlaces usan `{ "text": "Texto visible", "url": "pagina.php" }`. Las acciones
especiales usan `kind: "link"`; la instalación conserva `kind: "install"`.

El archivo no admite HTML. Todos los textos y atributos se escapan al renderizar.

`fuentes-y-creditos.json` también usa `version: 1`. Contiene el encabezado en
`page` y una lista de `sections`; cada sección admite bloques de texto o lista y
fuentes con nombre, descripción, enlaces HTTPS oficiales y recursos acreditados.
La vista valida y escapa el documento completo: tampoco admite HTML editorial.
