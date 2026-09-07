<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/photography-editorial-json.php';

function photographyJsonAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

photographyJsonAssert(in_array('sqlite', PDO::getAvailableDrivers(), true), 'La prueba aislada requiere PDO SQLite.');
$connection = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$connection->exec('CREATE TABLE photography_scenes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    scene_key TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    description TEXT NOT NULL,
    sort_order INTEGER NOT NULL,
    active INTEGER NOT NULL,
    example_image_path TEXT NULL,
    editorial_notes TEXT NULL
)');
$connection->exec('CREATE TABLE photography_scene_variants (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    scene_id INTEGER NOT NULL,
    variant_key TEXT NOT NULL,
    title TEXT NOT NULL,
    description TEXT NOT NULL,
    sort_order INTEGER NOT NULL,
    active INTEGER NOT NULL,
    example_image_path TEXT NULL,
    editorial_notes TEXT NULL,
    reference_focal_mm REAL NULL,
    aperture TEXT NULL,
    shutter_speed TEXT NULL,
    iso_value TEXT NULL,
    phone_note TEXT NULL,
    phone_viability TEXT NOT NULL,
    UNIQUE(scene_id, variant_key)
)');

$connection->exec("INSERT INTO photography_scenes
    (scene_key,title,description,sort_order,active,example_image_path,editorial_notes)
    VALUES ('moon_with_horizon','Luna con horizonte','Escena existente',20,1,'assets/images/example-scene.jpg','Notas de escena')");
$sceneId = (int) $connection->lastInsertId();
$insertVariant = $connection->prepare('INSERT INTO photography_scene_variants
    (scene_id,variant_key,title,description,sort_order,active,example_image_path,editorial_notes,reference_focal_mm,aperture,shutter_speed,iso_value,phone_note,phone_viability)
    VALUES (:scene_id,:key,:title,:description,:sort_order,1,:image,:notes,:focal,:aperture,:shutter,:iso,:phone_note,:viability)');
foreach ([
    ['moon_protagonist', 'Luna protagonista', 'Texto A', 10, 'assets/images/a.jpg', 'Notas A', 600, 'f/8', '1/100 s', '200', 'Celular A', 'limited'],
    ['visible_landscape', 'Paisaje visible', 'Texto B', 20, 'assets/images/b.jpg', 'Notas B', 85, 'f/5.6', '1/20 s', '800', 'Celular B', 'good'],
    ['silhouette', 'Silueta', 'Texto C', 30, 'assets/images/c.jpg', 'Notas C', 200, 'f/11', '1/250 s', '400', 'Celular C', 'good'],
] as $row) {
    [$key,$title,$description,$order,$image,$notes,$focal,$aperture,$shutter,$iso,$phoneNote,$viability] = $row;
    $insertVariant->execute(['scene_id'=>$sceneId,'key'=>$key,'title'=>$title,'description'=>$description,'sort_order'=>$order,'image'=>$image,'notes'=>$notes,'focal'=>$focal,'aperture'=>$aperture,'shutter'=>$shutter,'iso'=>$iso,'phone_note'=>$phoneNote,'viability'=>$viability]);
}

$partialJson = json_encode([
    'version' => 1,
    'scenes' => [[
        'key' => 'moon_with_horizon',
        'variants' => [[
            'key' => 'moon_object_horizon',
            'active' => true,
            'order' => 40,
            'title' => 'Luna y astro con horizonte',
            'description' => 'Nueva variante.',
            'phone_viability' => 'good',
        ]],
    ]],
], JSON_THROW_ON_ERROR);
$validation = photographyEditorialValidateJson($partialJson, 'merge');
photographyJsonAssert($validation['valid'] === true, 'Merge debe aceptar una escena existente parcial.');
photographyJsonAssert(!array_key_exists('image', $validation['data']['scenes'][0]), 'El validador no debe normalizar una imagen ausente a null.');
photographyJsonAssert(!array_key_exists('notes', $validation['data']['scenes'][0]['variants'][0]), 'El validador debe preservar campos ausentes en variantes.');
photographyJsonAssert(photographyEditorialValidateImportAgainstDatabase($connection, $validation['data']) === [], 'La variante nueva contiene los campos mínimos.');

$beforeScene = $connection->query("SELECT * FROM photography_scenes WHERE scene_key='moon_with_horizon'")->fetch();
$beforeVariants = $connection->query("SELECT * FROM photography_scene_variants WHERE scene_id=$sceneId ORDER BY id")->fetchAll();
photographyEditorialImportPackage($connection, $validation['data']);
$afterScene = $connection->query("SELECT * FROM photography_scenes WHERE scene_key='moon_with_horizon'")->fetch();
$afterExistingVariants = $connection->query("SELECT * FROM photography_scene_variants WHERE scene_id=$sceneId AND variant_key<>'moon_object_horizon' ORDER BY id")->fetchAll();
photographyJsonAssert($afterScene === $beforeScene, 'Agregar una variante no debe modificar ningún campo de la escena existente.');
photographyJsonAssert($afterExistingVariants === $beforeVariants, 'Agregar una variante no debe pisar imágenes, EXIF, notas ni textos de variantes existentes.');
photographyJsonAssert((int) $connection->query("SELECT COUNT(*) FROM photography_scene_variants WHERE scene_id=$sceneId AND variant_key='moon_object_horizon'")->fetchColumn() === 1, 'La variante parcial nueva debe crearse.');

$explicitNull = photographyEditorialValidateJson('{"version":1,"scenes":[{"key":"moon_with_horizon","variants":[{"key":"moon_protagonist","image":null}]}]}', 'merge');
photographyJsonAssert($explicitNull['valid'] === true && array_key_exists('image', $explicitNull['data']['scenes'][0]['variants'][0]), 'Un null explícito debe conservarse como propiedad presente.');
$beforeNullUpdate = $connection->query("SELECT * FROM photography_scene_variants WHERE scene_id=$sceneId AND variant_key='moon_protagonist'")->fetch();
photographyEditorialImportPackage($connection, $explicitNull['data']);
$afterNullUpdate = $connection->query("SELECT * FROM photography_scene_variants WHERE scene_id=$sceneId AND variant_key='moon_protagonist'")->fetch();
photographyJsonAssert($afterNullUpdate['example_image_path'] === null, 'image:null debe borrar explícitamente la imagen.');
unset($beforeNullUpdate['example_image_path'], $afterNullUpdate['example_image_path']);
photographyJsonAssert($afterNullUpdate === $beforeNullUpdate, 'El null explícito no debe cambiar ningún otro campo.');

$incompleteVariant = photographyEditorialValidateJson('{"version":1,"scenes":[{"key":"moon_with_horizon","variants":[{"key":"incomplete"}]}]}', 'merge');
photographyJsonAssert($incompleteVariant['valid'] === true, 'La validación JSON parcial no debe inventar si la variante existe.');
photographyJsonAssert(photographyEditorialValidateImportAgainstDatabase($connection, $incompleteVariant['data']) !== [], 'Una variante nueva debe exigir sus campos mínimos.');

$incompleteScene = photographyEditorialValidateJson('{"version":1,"scenes":[{"key":"new_scene"}]}', 'merge');
photographyJsonAssert($incompleteScene['valid'] === true, 'La validación JSON debe preservar un parche de escena.');
photographyJsonAssert(photographyEditorialValidateImportAgainstDatabase($connection, $incompleteScene['data']) !== [], 'Una escena nueva debe exigir sus campos mínimos.');

photographyJsonAssert(photographyEditorialValidateJson($partialJson, 'replace')['valid'] === false, 'Reemplazar todo debe seguir exigiendo el catálogo completo.');

$replaceJson = json_encode(['version'=>1,'scenes'=>[[
    'key'=>'replacement','active'=>true,'order'=>1,'title'=>'Reemplazo','description'=>'Catálogo completo','image'=>null,'notes'=>null,
    'variants'=>[[
        'key'=>'complete','active'=>true,'order'=>1,'title'=>'Completa','description'=>'Variante completa','image'=>null,'notes'=>null,
        'reference_focal_mm'=>null,'aperture'=>null,'shutter_speed'=>null,'iso'=>null,'phone_note'=>null,'phone_viability'=>'limited',
    ]],
]]], JSON_THROW_ON_ERROR);
$replaceValidation = photographyEditorialValidateJson($replaceJson, 'replace');
photographyJsonAssert($replaceValidation['valid'] === true, 'Reemplazar todo debe aceptar un catálogo completo.');
$replaceResult = photographyEditorialReplacePackage($connection, $replaceValidation['data']);
photographyJsonAssert($replaceResult['scenes'] === 1 && $replaceResult['variants'] === 1, 'Reemplazar todo debe mantener sus cantidades.');
photographyJsonAssert((int) $connection->query('SELECT COUNT(*) FROM photography_scenes')->fetchColumn() === 1 && (int) $connection->query('SELECT COUNT(*) FROM photography_scene_variants')->fetchColumn() === 1, 'Reemplazar todo debe seguir sustituyendo el catálogo en la base aislada.');

$adminSource = file_get_contents(__DIR__ . '/../admin/fotografia/index.php');
photographyJsonAssert(str_contains((string) $adminSource, 'photographyEditorialValidateJson($importJson, $importMode)'), 'El admin debe validar según Merge o Reemplazar todo.');
photographyJsonAssert(!in_array(realpath(__DIR__ . '/../includes/web-database.php'), array_map('realpath', get_included_files()), true), 'Esta prueba nunca debe cargar la conexión web normal.');

echo "photography-editorial-json isolated: ok\n";
