<?php

declare(strict_types=1);

use App\Controllers\Api\AuthController;
use App\Controllers\Api\HealthController;
use App\Middleware\ApiAuthenticate;
use App\Middleware\ApiPermission;
use App\Middleware\ApiRateLimit;

$router->get('/api_v1/health', [HealthController::class, 'index'])->withMiddleware(ApiRateLimit::class);
$router->post('/api_v1/auth/login', [AuthController::class, 'login'])->withMiddleware(ApiRateLimit::class);
$router->post('/api_v1/auth/logout', [AuthController::class, 'logout'])
    ->withMiddleware([ApiRateLimit::class, ApiAuthenticate::class, ApiPermission::class]);
$router->get('/api_v1/auth/me', [AuthController::class, 'me'])
    ->withMiddleware([ApiRateLimit::class, ApiAuthenticate::class, ApiPermission::class]);
