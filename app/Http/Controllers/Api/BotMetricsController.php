<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BotMetricsService;
use Illuminate\Http\JsonResponse;

class BotMetricsController extends Controller
{
    public function __invoke(BotMetricsService $metrics): JsonResponse
    {
        $snapshot = $metrics->getDashboardSnapshot();

        return response()->json([
            'summary' => [
                'intent_total' => array_sum($snapshot['intents'] ?? []),
                'route_total' => array_sum($snapshot['routes'] ?? []),
                'error_total' => array_sum($snapshot['errors'] ?? []),
            ],
            'intents' => $snapshot['intents'] ?? [],
            'routes' => $snapshot['routes'] ?? [],
            'errors' => $snapshot['errors'] ?? [],
            'updated_at' => $snapshot['updated_at'] ?? now()->toDateTimeString(),
        ]);
    }
}

