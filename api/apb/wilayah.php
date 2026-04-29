<?php
require_once __DIR__ . '/../config/app.php';
header('Content-Type: application/json; charset=utf-8');

$type = $_GET['type'] ?? 'provinces';
$id   = $_GET['id']   ?? '';

$cacheDir  = sys_get_temp_dir();
$cacheKey  = 'wilayah_' . md5($type . $id) . '.json';
$cachePath = $cacheDir . '/' . $cacheKey;

// Cek cache file (berlaku 1 jam)
if (file_exists($cachePath) && (time() - filemtime($cachePath)) < 3600) {
    echo file_get_contents($cachePath);
    exit;
}

if ($type === 'provinces') {
    $url = 'https://www.emsifa.com/api-wilayah-indonesia/api/provinces.json';
} elseif ($type === 'cities' && $id) {
    $url = "https://www.emsifa.com/api-wilayah-indonesia/api/regencies/{$id}.json";
} else {
    echo json_encode([]);
    exit;
}

$ctx = stream_context_create(['http' => ['timeout' => 10, 'ignore_errors' => true]]);
$raw = @file_get_contents($url, false, $ctx);

if ($raw === false) {
    // Return empty array if fetch fails - don't error out
    echo json_encode([]);
    exit;
}

// Simpan cache
@file_put_contents($cachePath, $raw);
echo $raw;
