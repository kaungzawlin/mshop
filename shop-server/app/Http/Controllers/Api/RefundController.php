<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\RefundRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'reason' => ['required', 'string', 'max:2000'],
            'evidence' => ['nullable', 'array'],
            'evidence.*' => ['string', 'max:500'],
        ]);

        $order = Order::query()->findOrFail($validated['order_id']);

        if ($order->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You do not have permission to perform this action.',
                'code' => 'forbidden',
            ], 403);
        }

        $existingPending = RefundRequest::query()
            ->where('order_id', $order->id)
            ->where('status', 'pending')
            ->exists();

        if ($existingPending) {
            throw ValidationException::withMessages([
                'order_id' => ['A pending refund request already exists for this order.'],
            ]);
        }

        $refund = DB::transaction(function () use ($request, $order, $validated): RefundRequest {
            $refund = RefundRequest::query()->create([
                'order_id' => $order->id,
                'user_id' => $request->user()->id,
                'status' => 'pending',
                'reason' => $validated['reason'],
                'evidence' => $validated['evidence'] ?? null,
            ]);

            AuditLog::query()->create([
                'actor_id' => $request->user()->id,
                'action' => 'refund.requested',
                'target_type' => 'refund_request',
                'target_id' => $refund->id,
                'reason' => $validated['reason'],
                'new_values' => ['order_id' => $order->id, 'status' => 'pending'],
                'created_at' => now(),
            ]);

            return $refund;
        });

        return response()->json([
            'data' => $this->refundToArray($refund),
        ], 201);
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
