<?php

namespace App\Http\Controllers\Api\V1\Audit;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AuditLogResource;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditLogController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    /**
     * List user's immutable audit log records with optional filters.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only(['event', 'auditable_type', 'from_date', 'to_date']);
        $perPage = min(100, max(5, (int) $request->input('per_page', 25)));

        $logs = $this->auditLogService->getAuditLogsForUser($request->user(), $filters, $perPage);

        return AuditLogResource::collection($logs);
    }
}
