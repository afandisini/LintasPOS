<?php

declare(strict_types=1);

$requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
// Normalize malformed URIs like //assets/... to /assets/...
$requestUri = '/' . ltrim($requestUri, '/');

$path = parse_url($requestUri, PHP_URL_PATH);
if (!is_string($path) || $path === '') {
    $path = '/';
}

$publicRoot = __DIR__ . '/public';
$decodedPath = rawurldecode($path);
$normalizedPath = ltrim(str_replace('\\', '/', $decodedPath), '/');
$staticFile = $publicRoot . '/' . $normalizedPath;

if ($normalizedPath !== '' && is_file($staticFile)) {
    $extension = strtolower(pathinfo($staticFile, PATHINFO_EXTENSION));
    $mime = match ($extension) {
        'css' => 'text/css; charset=UTF-8',
        'js' => 'application/javascript; charset=UTF-8',
        'json' => 'application/json; charset=UTF-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        default => 'application/octet-stream',
    };

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($staticFile));
    readfile($staticFile);
    return true;
}

require __DIR__ . '/public/index.php';
