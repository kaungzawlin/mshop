<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'actor_id' => ['sometimes', 'integer', 'exists:users,id'],
            'action' => ['sometimes', 'string', 'max:128'],
            'target_type' => ['sometimes', 'string', 'max:64'],
            'target_id' => ['sometimes', 'integer', 'min:1'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date'],
        ]);

        $paginator = $this->filteredQuery($validated)->paginate(15);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (AuditLog $log) => $this->logToArray($log))->values(),
            'meta' => $this->paginationMeta($paginator),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredQuery(array $filters): Builder
    {
        $query = AuditLog::query()->with('actor')->orderByDesc('id');

        if (isset($filters['actor_id'])) {
            $query->where('actor_id', $filters['actor_id']);
        }

        if (isset($filters['action'])) {
            $query->where('action', 'ilike', '%'.$filters['action'].'%');
        }

        if (isset($filters['target_type'])) {
            $query->where('target_type', $filters['target_type']);
        }

        if (isset($filters['target_id'])) {
            $query->where('target_id', $filters['target_id']);
        }

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function logToArray(AuditLog $log): array
    {
        return [
            'id' => $log->id,
            'actor_id' => $log->actor_id,
            'actor' => $log->relationLoaded('actor') && $log->actor
                ? [
                    'id' => $log->actor->id,
                    'name' => $log->actor->name,
                    'email' => $log->actor->email,
                ]
                : null,
            'action' => $log->action,
            'target_type' => $log->target_type,
            'target_id' => $log->target_id,
            'reason' => $log->reason,
            'old_values' => $log->old_values,
            'new_values' => $log->new_values,
            'meta' => $log->meta,
            'created_at' => $log->created_at?->utc()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function paginationMeta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
