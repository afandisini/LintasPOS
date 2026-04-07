<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use System\Http\Response;

class PingController
{
    public function index(): Response
    {
        return Response::json(['status' => 'ok']);
    }
}

