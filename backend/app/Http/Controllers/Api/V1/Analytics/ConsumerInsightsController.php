<?php

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Http\Controllers\Controller;
use App\Services\Analytics\ConsumerInsightsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsumerInsightsController extends Controller
{
    public function __construct(
        protected ConsumerInsightsService $insightsService
    ) {}

    /**
     * Get Malaysian consumer insights, archetype persona, and DOSM peer benchmarks.
     */
    public function index(Request $request): JsonResponse
    {
        $insights = $this->insightsService->getInsights($request->user());

        return response()->json([
            'status' => 'success',
            'data' => $insights,
        ]);
    }

    /**
     * Update PDPA analytics opt-in consent and demographic parameters.
     */
    public function updateConsent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'is_opted_in' => 'sometimes|boolean',
            'state_code' => 'sometimes|string|max:10',
            'income_tier' => 'sometimes|string|in:B40,M40,T20',
        ]);

        $consent = $this->insightsService->updateConsent($request->user(), $validated);

        return response()->json([
            'status' => 'success',
            'data' => [
                'is_opted_in' => $consent->is_opted_in,
                'state_code' => $consent->state_code,
                'income_tier' => $consent->income_tier,
                'consented_at' => $consent->consented_at?->toISOString(),
            ],
            'message' => 'Consumer insights preferences updated successfully.',
        ]);
    }
}
