<?php

declare(strict_types=1);

use App\Controllers\Api\AuthController;
use App\Controllers\Api\HealthController;
use App\Controllers\Api\KategoriController;
use App\Middleware\ApiAuthenticate;
use App\Middleware\ApiPermission;
use App\Middleware\ApiRateLimit;

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
