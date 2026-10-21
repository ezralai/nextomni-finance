<?php

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\MlIntelligenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(
        protected AnalyticsService $analyticsService,
        protected MlIntelligenceService $mlIntelligenceService
    ) {}

    /**
     * Compute cash flow metrics, category breakdowns, and trends.
     */
    public function cashFlow(Request $request): JsonResponse
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $data = $this->analyticsService->getCashFlow(
            $request->user(),
            $startDate,
            $endDate
        );

        return response()->json($data);
    }

    /**
     * Compute predictive ML spending forecasts and anomaly detection.
     */
    public function mlIntelligence(Request $request): JsonResponse
    {
        $forceRefresh = $request->boolean('refresh', false);

        $data = $this->mlIntelligenceService->getIntelligence(
            $request->user(),
            $forceRefresh
        );

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }
}
