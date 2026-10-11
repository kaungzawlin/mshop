<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\RefundRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = RefundRequest::query()->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $paginator = $query->paginate(15);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (RefundRequest $refund) => $this->refundToArray($refund))->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function approve(Request $request, RefundRequest $refundRequest): JsonResponse
    {
        return $this->resolve($request, $refundRequest, 'approved');
    }

    public function reject(Request $request, RefundRequest $refundRequest): JsonResponse
    {
        return $this->resolve($request, $refundRequest, 'rejected');
    }

    private function resolve(Request $request, RefundRequest $refundRequest, string $status): JsonResponse
    {
        if ($refundRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'refund_request' => ['This refund request has already been resolved.'],
            ]);
        }

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
            'amount' => ['nullable', 'integer', 'min:0'],
            'method' => ['nullable', 'string', 'max:64'],
        ]);

        DB::transaction(function () use ($request, $refundRequest, $status, $validated): void {
            $resolution = [
                'status' => $status,
                'note' => $validated['note'],
                'resolved_by' => $request->user()->id,
                'resolved_at' => now()->utc()->toIso8601String(),
            ];

            if (array_key_exists('amount', $validated)) {
                $resolution['amount'] = $validated['amount'];
            }

            if (array_key_exists('method', $validated)) {
                $resolution['method'] = $validated['method'];
            }

            $refundRequest->status = $status;
            $refundRequest->resolution = $resolution;
            $refundRequest->save();

            AuditLog::query()->create([
                'actor_id' => $request->user()->id,
                'action' => 'refund.'.$status,
                'target_type' => 'refund_request',
                'target_id' => $refundRequest->id,
                'reason' => $validated['note'],
                'new_values' => $resolution,
                'created_at' => now(),
            ]);
        });

        return response()->json([
            'data' => $this->refundToArray($refundRequest->fresh()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function refundToArray(RefundRequest $refund): array
    {
        return [
            'id' => $refund->id,
            'order_id' => $refund->order_id,
            'user_id' => $refund->user_id,
            'status' => $refund->status,
            'reason' => $refund->reason,
            'evidence' => $refund->evidence,
            'resolution' => $refund->resolution,
            'created_at' => $refund->created_at?->utc()->toIso8601String(),
            'updated_at' => $refund->updated_at?->utc()->toIso8601String(),
        ];
    }
}
