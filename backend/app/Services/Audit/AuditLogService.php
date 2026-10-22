<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    /**
     * Record an immutable audit event for a financial model mutation.
     */
    public function log(
        User $user,
        string $event,
        Model $model,
        ?array $oldValues = null,
        ?array $newValues = null
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $user->id,
            'event' => $event,
            'auditable_type' => get_class($model),
            'auditable_id' => (string) $model->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Retrieve paginated audit logs for a user with optional filtering.
     */
    public function getAuditLogsForUser(User $user, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = AuditLog::where('user_id', $user->id);

        if (! empty($filters['event'])) {
            $query->where('event', $filters['event']);
        }

        if (! empty($filters['auditable_type'])) {
            $type = $filters['auditable_type'];
            if (! str_contains($type, '\\')) {
                $query->where('auditable_type', 'like', "%\\{$type}");
            } else {
                $query->where('auditable_type', $type);
            }
        }

        if (! empty($filters['from_date'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['from_date'])->startOfDay());
        }

        if (! empty($filters['to_date'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['to_date'])->endOfDay());
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }
}
