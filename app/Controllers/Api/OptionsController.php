<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use System\Http\Response;

final class OptionsController
{
    public function preflight(): Response
    {
        return Response::json([], 204)->withoutBody();
    }
}
