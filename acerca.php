<?php

require_once __DIR__ . '/includes/current-datetime.php';

$scriptName = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '/acerca.php';
$basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/.');
$destination = ($basePath !== '' ? $basePath : '') . '/' . astronomyInternalUrl('acerca-del-sitio.php');

header('Location: ' . $destination, true, 301);
exit;
