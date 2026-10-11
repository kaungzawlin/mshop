<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(array_column(OrderStatus::cases(), 'value'))],
            'search' => ['sometimes', 'string', 'max:255'],
        ]);

        $query = Order::query()->with(['user', 'items', 'shipments', 'payments']);

        if ($request->filled('status')) {
            $query->where('status', $validated['status']);
        }

        if ($request->filled('search')) {
            $search = $validated['search'];
            $query->where(function ($builder) use ($search): void {
                $builder->where('order_no', 'ilike', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search): void {
                        $userQuery->where('email', 'ilike', "%{$search}%")
                            ->orWhere('name', 'ilike', "%{$search}%");
                    });
            });
        }

        $paginator = $query->orderByDesc('id')->paginate(15);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (Order $order) => $this->orderToArray($order))->values(),
            'meta' => $this->paginationMeta($paginator),
        ]);
    }

    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(array_column(OrderStatus::cases(), 'value'))],
            'reason' => ['sometimes', 'string', 'max:500'],
        ]);

        $newStatus = OrderStatus::from($validated['status']);

        if ($order->status === $newStatus) {
            return response()->json([
                'data' => $this->orderToArray($order->load(['user', 'items', 'shipments', 'payments'])),
            ]);
        }

        DB::transaction(function () use ($request, $order, $newStatus, $validated): void {
            $oldStatus = $order->status->value;

            $order->status = $newStatus;
            $order->save();

            AuditLog::query()->create([
                'actor_id' => $request->user()->id,
                'action' => 'order.status_updated',
                'target_type' => 'order',
                'target_id' => $order->id,
                'reason' => $validated['reason'] ?? null,
                'old_values' => ['status' => $oldStatus],
                'new_values' => ['status' => $newStatus->value],
                'created_at' => now(),
            ]);
        });

        return response()->json([
            'data' => $this->orderToArray($order->fresh(['user', 'items', 'shipments', 'payments'])),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function orderToArray(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_no' => $order->order_no,
            'user' => $order->user ? [
                'id' => $order->user->id,
                'name' => $order->user->name,
                'email' => $order->user->email,
            ] : null,
            'status' => $order->status->value,
            'payment_method' => $order->payment_method->value,
            'payment_status' => $order->payment_status->value,
            'subtotal' => $order->subtotal,
            'discount_total' => $order->discount_total,
            'shipping_total' => $order->shipping_total,
            'points_redeemed' => $order->points_redeemed,
            'wallet_applied' => $order->wallet_applied,
            'grand_total' => $order->grand_total,
            'address_id' => $order->address_id,
            'address_snapshot' => $order->address_snapshot,
            'items' => $order->items->map(fn (OrderItem $item) => [
                'id' => $item->id,
                'shipment_id' => $item->shipment_id,
                'seller_user_id' => $item->seller_user_id,
                'product_variant_id' => $item->product_variant_id,
                'title' => $item->title,
                'sku' => $item->sku,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'line_total' => $item->line_total,
            ])->values(),
            'shipments' => $order->shipments->map(fn (Shipment $shipment) => [
                'id' => $shipment->id,
                'seller_user_id' => $shipment->seller_user_id,
                'delivery_mode' => $shipment->delivery_mode->value,
                'status' => $shipment->status->value,
                'fee' => $shipment->fee,
            ])->values(),
            'payments' => $order->payments->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'method' => $payment->method->value,
                'amount' => $payment->amount,
                'status' => $payment->status->value,
            ])->values(),
            'created_at' => $order->created_at?->utc()->toIso8601String(),
            'updated_at' => $order->updated_at?->utc()->toIso8601String(),
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
