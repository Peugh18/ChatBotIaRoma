<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ConversationSalesContextService;
use Illuminate\Http\JsonResponse;

class ConversationSalesContextController extends Controller
{
    public function show(string $phone, ConversationSalesContextService $service): JsonResponse
    {
        return response()->json($service->forPhone($phone));
    }
}
