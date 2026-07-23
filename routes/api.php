<?php

declare(strict_types=1);

use App\Controllers\Api\PingController;
use App\Controllers\Api\SearchController;
use App\Middleware\Authenticate;

$router->get('/api/ping', [PingController::class, 'index']);
$router->get('/api/search', [SearchController::class, 'index'])->withMiddleware(Authenticate::class);
