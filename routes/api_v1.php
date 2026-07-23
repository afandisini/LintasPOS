<?php

declare(strict_types=1);

use App\Controllers\Api\AuthController;
use App\Controllers\Api\BarangController;
use App\Controllers\Api\DiskonController;
use App\Controllers\Api\HealthController;
use App\Controllers\Api\JasaController;
use App\Controllers\Api\KategoriController;
use App\Controllers\Api\LookupController;
use App\Controllers\Api\MediaController;
use App\Controllers\Api\OptionsController;
use App\Controllers\Api\PelangganController;
use App\Controllers\Api\SatuanController;
use App\Controllers\Api\SupplierController;
use App\Middleware\ApiAuthenticate;
use App\Middleware\ApiPermission;
use App\Middleware\ApiRateLimit;
use App\Middleware\ApiCors;

$router->options('/api_v1/{path...}', [OptionsController::class, 'preflight'])
    ->withMiddleware(ApiCors::class);
$router->get('/api_v1/health', [HealthController::class, 'index'])->withMiddleware(ApiRateLimit::class);
$router->post('/api_v1/auth/login', [AuthController::class, 'login'])->withMiddleware(ApiRateLimit::class);
$router->post('/api_v1/auth/logout', [AuthController::class, 'logout'])
    ->withMiddleware([ApiRateLimit::class, ApiAuthenticate::class, ApiPermission::class]);
$router->get('/api_v1/auth/me', [AuthController::class, 'me'])
    ->withMiddleware([ApiRateLimit::class, ApiAuthenticate::class, ApiPermission::class]);
$kategoriMiddleware = [ApiRateLimit::class, ApiAuthenticate::class, ApiPermission::class];
$router->get('/api_v1/kategori', [KategoriController::class, 'index'])->withMiddleware($kategoriMiddleware);
$router->post('/api_v1/kategori', [KategoriController::class, 'store'])->withMiddleware($kategoriMiddleware);
$router->get('/api_v1/kategori/{id}', [KategoriController::class, 'show'])->withMiddleware($kategoriMiddleware);
$router->put('/api_v1/kategori/{id}', [KategoriController::class, 'update'])->withMiddleware($kategoriMiddleware);
$router->delete('/api_v1/kategori/{id}', [KategoriController::class, 'destroy'])->withMiddleware($kategoriMiddleware);
$satuanMiddleware = [ApiRateLimit::class, ApiAuthenticate::class, ApiPermission::class];
$router->get('/api_v1/satuan', [SatuanController::class, 'index'])->withMiddleware($satuanMiddleware);
$router->post('/api_v1/satuan', [SatuanController::class, 'store'])->withMiddleware($satuanMiddleware);
$router->get('/api_v1/satuan/{id}', [SatuanController::class, 'show'])->withMiddleware($satuanMiddleware);
$router->put('/api_v1/satuan/{id}', [SatuanController::class, 'update'])->withMiddleware($satuanMiddleware);
$router->delete('/api_v1/satuan/{id}', [SatuanController::class, 'destroy'])->withMiddleware($satuanMiddleware);
$masterMiddleware = [ApiRateLimit::class, ApiAuthenticate::class, ApiPermission::class];
foreach ([
    'barang' => BarangController::class,
    'jasa' => JasaController::class,
    'pelanggan' => PelangganController::class,
    'supplier' => SupplierController::class,
    'diskon' => DiskonController::class,
] as $resource => $controller) {
    $router->get('/api_v1/' . $resource, [$controller, 'index'])->withMiddleware($masterMiddleware);
    $router->post('/api_v1/' . $resource, [$controller, 'store'])->withMiddleware($masterMiddleware);
    $router->get('/api_v1/' . $resource . '/{id}', [$controller, 'show'])->withMiddleware($masterMiddleware);
    $router->put('/api_v1/' . $resource . '/{id}', [$controller, 'update'])->withMiddleware($masterMiddleware);
    $router->delete('/api_v1/' . $resource . '/{id}', [$controller, 'destroy'])->withMiddleware($masterMiddleware);
}
$lookupMiddleware = [ApiRateLimit::class, ApiAuthenticate::class, ApiPermission::class];
foreach (['barang', 'jasa', 'pelanggan', 'supplier'] as $resource) {
    $router->get('/api_v1/lookups/' . $resource, [LookupController::class, 'index'])->withMiddleware($lookupMiddleware);
}
$mediaMiddleware = [ApiRateLimit::class, ApiAuthenticate::class, ApiPermission::class];
$router->post('/api_v1/media', [MediaController::class, 'store'])->withMiddleware($mediaMiddleware);
$router->get('/api_v1/media/{id}', [MediaController::class, 'show'])->withMiddleware($mediaMiddleware);
$router->delete('/api_v1/media/{id}', [MediaController::class, 'destroy'])->withMiddleware($mediaMiddleware);
