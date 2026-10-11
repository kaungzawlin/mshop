<?php

namespace App\Http\Controllers\Api\Finance;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentVerificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $paginator = Payment::query()
            ->where('method', PaymentMethod::ManualTransfer)
            ->where('status', PaymentStatus::Pending)
            ->with(['order.user'])
            ->orderBy('id')
            ->paginate(15);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (Payment $payment) => $this->paymentToArray($payment))->values(),
            'meta' => $this->paginationMeta($paginator),
        ]);
    }

    public function verify(Request $request, Payment $payment): JsonResponse
    {
        if (! $this->isPendingManualTransfer($payment)) {
            return response()->json([
                'message' => 'Payment is not pending manual transfer verification.',
                'code' => 'invalid_state',
            ], 422);
        }

        $validated = $request->validate([
            'reference' => ['sometimes', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($request, $payment, $validated): void {
            $payment->status = PaymentStatus::Paid;
            $payment->verified_by = $request->user()->id;
            $payment->verified_at = now();
            if (isset($validated['reference'])) {
                $payment->reference = $validated['reference'];
            }
            $payment->save();

            $order = $payment->order;
            $order->payment_status = PaymentStatus::Paid;
            if ($order->status === OrderStatus::Placed) {
                $order->status = OrderStatus::Paid;
            }
            $order->save();

            AuditLog::query()->create([
                'actor_id' => $request->user()->id,
                'action' => 'payment.verified',
                'target_type' => 'payment',
                'target_id' => $payment->id,
                'reason' => null,
                'old_values' => ['status' => PaymentStatus::Pending->value],
                'new_values' => ['status' => PaymentStatus::Paid->value],
                'created_at' => now(),
            ]);
        });

        return response()->json([
            'data' => $this->paymentToArray($payment->fresh(['order.user'])),
        ]);
    }

    public function reject(Request $request, Payment $payment): JsonResponse
    {
        if (! $this->isPendingManualTransfer($payment)) {
            return response()->json([
                'message' => 'Payment is not pending manual transfer verification.',
                'code' => 'invalid_state',
            ], 422);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($request, $payment, $validated): void {
            $payment->status = PaymentStatus::Failed;
            $payment->verified_by = $request->user()->id;
            $payment->verified_at = now();
            $payment->meta = array_merge($payment->meta ?? [], ['reject_reason' => $validated['reason']]);
            $payment->save();

            $order = $payment->order;
            $order->payment_status = PaymentStatus::Failed;
            $order->save();

            AuditLog::query()->create([
                'actor_id' => $request->user()->id,
                'action' => 'payment.rejected',
                'target_type' => 'payment',
                'target_id' => $payment->id,
                'reason' => $validated['reason'],
                'old_values' => ['status' => PaymentStatus::Pending->value],
                'new_values' => ['status' => PaymentStatus::Failed->value],
                'created_at' => now(),
            ]);
        });

        return response()->json([
            'data' => $this->paymentToArray($payment->fresh(['order.user'])),
        ]);
    }

    private function isPendingManualTransfer(Payment $payment): bool
    {
        return $payment->method === PaymentMethod::ManualTransfer
            && $payment->status === PaymentStatus::Pending;
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentToArray(Payment $payment): array
    {
        $order = $payment->order;

        return [
            'id' => $payment->id,
            'order_id' => $payment->order_id,
            'method' => $payment->method->value,
            'amount' => $payment->amount,
            'status' => $payment->status->value,
            'reference' => $payment->reference,
            'screenshot_path' => $payment->screenshot_path,
            'verified_at' => $payment->verified_at?->utc()->toIso8601String(),
            'order' => $order ? [
                'id' => $order->id,
                'order_no' => $order->order_no,
                'grand_total' => $order->grand_total,
                'user' => $order->user ? [
                    'id' => $order->user->id,
                    'name' => $order->user->name,
                    'email' => $order->user->email,
                ] : null,
            ] : null,
            'created_at' => $payment->created_at?->utc()->toIso8601String(),
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
