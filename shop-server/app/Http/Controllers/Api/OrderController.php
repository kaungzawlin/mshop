<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $paginator = Order::query()
            ->where('user_id', $request->user()->id)
            ->with(['items', 'shipments', 'payments'])
            ->orderByDesc('id')
            ->paginate(15);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (Order $order) => $this->orderToArray($order))->values(),
            'meta' => $this->paginationMeta($paginator),
        ]);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Order not found.',
                'code' => 'not_found',
            ], 404);
        }

        $order->load(['items', 'shipments', 'payments']);

        return response()->json([
            'data' => $this->orderToArray($order),
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
                'discount' => $item->discount,
                'line_total' => $item->line_total,
            ])->values(),
            'shipments' => $order->shipments->map(fn (Shipment $shipment) => [
                'id' => $shipment->id,
                'seller_user_id' => $shipment->seller_user_id,
                'delivery_mode' => $shipment->delivery_mode->value,
                'status' => $shipment->status->value,
                'fee' => $shipment->fee,
                'cod_custody' => $shipment->cod_custody,
                'eta_at' => $shipment->eta_at?->utc()->toIso8601String(),
            ])->values(),
            'payments' => $order->payments->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'method' => $payment->method->value,
                'amount' => $payment->amount,
                'status' => $payment->status->value,
                'reference' => $payment->reference,
                'verified_at' => $payment->verified_at?->utc()->toIso8601String(),
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
