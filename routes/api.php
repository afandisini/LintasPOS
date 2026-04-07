<?php

declare(strict_types=1);

use App\Controllers\Api\PingController;

$router->get('/api/ping', [PingController::class, 'index']);
