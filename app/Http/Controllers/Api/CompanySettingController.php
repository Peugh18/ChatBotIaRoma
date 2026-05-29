<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CompanySettingController extends Controller
{
    public function index(): JsonResponse
    {
        $settings = CompanySetting::first();
        if (!$settings) {
            return response()->json(null, 404);
        }
        return response()->json($settings);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_name' => 'sometimes|string|max:255',
            'yape_number' => 'sometimes|string|max:255',
            'yape_name' => 'sometimes|string|max:255',
            'business_hours' => 'nullable|array',
            'social_networks' => 'nullable|array',
            'address' => 'nullable|string',
            'sales_tone' => 'nullable|string|max:255',
            'sales_closing_cta' => 'nullable|string|max:255',
        ]);

        $settings = CompanySetting::firstOrCreate([]);
        $settings->update($validated);

        return response()->json($settings);
    }
}
