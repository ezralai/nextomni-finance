<?php

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(
        protected AnalyticsService $analyticsService
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
}
