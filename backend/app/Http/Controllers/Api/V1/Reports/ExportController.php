<?php

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\ExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function __construct(
        protected ExportService $exportService
    ) {}

    /**
     * Stream financial statement transactions as RFC 4180 CSV with sanitization.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $filters = $request->only(['account_id', 'category_id', 'type', 'start_date', 'end_date']);

        return $this->exportService->exportTransactionsCsv(
            $request->user(),
            $filters
        );
    }

    /**
     * Export financial statement transactions as Open Financial structured JSON.
     */
    public function exportJson(Request $request): JsonResponse
    {
        $filters = $request->only(['account_id', 'category_id', 'type', 'start_date', 'end_date']);

        $data = $this->exportService->exportTransactionsJson(
            $request->user(),
            $filters
        );

        return response()->json($data);
    }
}
