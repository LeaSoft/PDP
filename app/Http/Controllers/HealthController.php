<?php

namespace App\Http\Controllers;

use App\Services\HealthService;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /**
     * GET /api/health: 200 while the app and its database answer, 503 otherwise.
     */
    public function __invoke(HealthService $service): JsonResponse
    {
        $report = $service->check();

        return response()
            ->json($report->toArray(), $report->isHealthy() ? 200 : 503)
            ->header('Cache-Control', 'no-store');
    }
}
