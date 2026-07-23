<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Services\ApiResponse;
use System\Http\Response;

final class HealthController
{
    public function index(): Response
    {
        return ApiResponse::success(['service' => 'LintasPOS API', 'version' => 'v1', 'status' => 'ok']);
    }
}
