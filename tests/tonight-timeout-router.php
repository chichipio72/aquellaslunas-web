<?php

sleep(3);
header('Content-Type: application/json');
echo json_encode([
    'night' => [],
    'planets' => [],
]);
