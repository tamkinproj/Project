<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Modules\Admin\Services\SystemHealth;
use Illuminate\Http\JsonResponse;

class SystemHealthController
{
    public function __invoke(SystemHealth $health): JsonResponse
    {
        return response()->json($health->report());
    }
}
