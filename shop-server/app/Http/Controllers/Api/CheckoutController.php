<?php

namespace App\Http\Controllers\Api;

use App\Enums\DeliveryMode;
use App\Enums\ListingStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PointLedger;
use App\Models\ProductVariant;
use App\Models\Shipment;
use App\Models\Wallet;
use App\Models\WalletLedger;
use App\Services\Settings\RuntimeConfigResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CheckoutController extends Controller
{
    public function __construct(private RuntimeConfigResolver $resolver) {}

    public function quote(Request $request): JsonResponse
    {
        $cart = $this->loadCart($request);
        if ($cart === null) {
            return response()->json([
                'message' => 'Cart is empty.',
                'code' => 'cart_empty',
            ], 422);
        }

        $lines = $this->buildCartLines($cart);
        if ($lines === []) {
            return response()->json([
                'message' => 'Cart is empty.',
                'code' => 'cart_empty',
            ], 422);
        }

        $subtotal = (int) array_sum(array_column($lines, 'line_total'));
        $shipping = (int) $this->resolver->get('delivery', 'gtg_fee_mmk', 0);

        return response()->json([
            'data' => [
                'subtotal' => $subtotal,
                'shipping_total' => $shipping,
                'discount_total' => 0,
                'grand_total' => $subtotal + $shipping,
                'payment_methods' => $this->enabledPaymentMethods(),
                'points' => $this->pointsQuote($request),
                'wallet_balance' => $this->walletBalance($request->user()->id),
            ],
        ]);
    }

    public function place(Request $request): JsonResponse
    {
        if ($this->resolver->enabled('deposit') && $request->user()->status === UserStatus::PendingDeposit) {
            return response()->json([
                'message' => 'Complete your first deposit before checkout.',
                'code' => 'deposit_required',
            ], 403);
        }

        if (! $this->resolver->get('checkout', 'enabled', true)) {
            return response()->json([
                'message' => 'Checkout is currently unavailable.',
                'code' => 'checkout_disabled',
            ], 403);
        }

        $validated = $request->validate([
            'address_id' => ['required', 'integer', Rule::exists('addresses', 'id')],
            'payment_method' => ['required', 'string', Rule::in(array_column(PaymentMethod::cases(), 'value'))],
            'delivery_mode' => ['sometimes', 'string', Rule::in(array_column(DeliveryMode::cases(), 'value'))],
            'points_to_redeem' => ['sometimes', 'integer', 'min:0'],
            'wallet_amount' => ['sometimes', 'integer', 'min:0'],
        ]);

        $address = Address::query()
            ->where('id', $validated['address_id'])
            ->where('user_id', $request->user()->id)
            ->first();

        if ($address === null) {
            return response()->json([
                'message' => 'Address not found.',
                'code' => 'not_found',
            ], 404);
        }

        $paymentMethod = PaymentMethod::from($validated['payment_method']);
        $deliveryMode = isset($validated['delivery_mode'])
            ? DeliveryMode::from($validated['delivery_mode'])
            : DeliveryMode::GtgRider;

        $cart = $this->loadCart($request);
        if ($cart === null) {
            return response()->json([
                'message' => 'Cart is empty.',
                'code' => 'cart_empty',
            ], 422);
        }

        $lines = $this->buildCartLines($cart);
        if ($lines === []) {
            return response()->json([
                'message' => 'Cart is empty.',
                'code' => 'cart_empty',
            ], 422);
        }

        $subtotal = (int) array_sum(array_column($lines, 'line_total'));
        $shipping = (int) $this->resolver->get('delivery', 'gtg_fee_mmk', 0);
        $preTotal = $subtotal + $shipping;

        $pointsToRedeem = (int) ($validated['points_to_redeem'] ?? 0);
        $walletAmount = (int) ($validated['wallet_amount'] ?? 0);

        $pointsDiscount = $this->pointsDiscountMmk($request, $pointsToRedeem, $preTotal);
        if ($pointsDiscount === false) {
            return response()->json([
                'message' => 'Insufficient points or redemption exceeds the allowed cap.',
                'code' => 'points_invalid',
            ], 422);
        }

        $afterPoints = max(0, $preTotal - $pointsDiscount);

        if ($paymentMethod === PaymentMethod::Wallet && $walletAmount === 0) {
            $walletAmount = $afterPoints;
        }

        if ($walletAmount > $afterPoints) {
            $walletAmount = $afterPoints;
        }

        $walletBalance = $this->walletBalance($request->user()->id);
        if ($walletAmount > $walletBalance) {
            return response()->json([
                'message' => 'Insufficient wallet balance.',
                'code' => 'wallet_insufficient',
            ], 422);
        }

        $grandTotal = max(0, $afterPoints - $walletAmount);

        $methodError = $this->validatePaymentMethod($paymentMethod, $grandTotal);
        if ($methodError !== null) {
            return response()->json($methodError['body'], $methodError['status']);
        }

        if ($paymentMethod === PaymentMethod::Wallet && $grandTotal > 0) {
            return response()->json([
                'message' => 'Wallet amount does not cover the order total.',
                'code' => 'wallet_insufficient',
            ], 422);
        }

        try {
            $order = DB::transaction(function () use (
                $request,
                $address,
                $cart,
                $lines,
                $subtotal,
                $shipping,
                $pointsToRedeem,
                $pointsDiscount,
                $walletAmount,
                $grandTotal,
                $paymentMethod,
                $deliveryMode,
            ): Order {
                $this->reserveInventory($lines, $request->user()->id);

                $order = Order::query()->create([
                    'order_no' => $this->nextOrderNo(),
                    'user_id' => $request->user()->id,
                    'address_id' => $address->id,
                    'status' => OrderStatus::Placed,
                    'payment_method' => $paymentMethod,
                    'payment_status' => $this->initialPaymentStatus($paymentMethod, $grandTotal),
                    'subtotal' => $subtotal,
                    'discount_total' => $pointsDiscount,
                    'shipping_total' => $shipping,
                    'points_redeemed' => $pointsToRedeem,
                    'wallet_applied' => $walletAmount,
                    'grand_total' => $grandTotal,
                    'address_snapshot' => $this->addressSnapshot($address),
                ]);

                $shipmentsBySeller = $this->createShipments($order, $lines, $deliveryMode, $paymentMethod, $shipping);
                $this->createOrderItems($order, $lines, $shipmentsBySeller);

                Payment::query()->create([
                    'order_id' => $order->id,
                    'method' => $paymentMethod,
                    'amount' => $grandTotal,
                    'status' => $order->payment_status,
                    'meta' => [
                        'wallet_applied' => $walletAmount,
                        'points_redeemed' => $pointsToRedeem,
                        'points_discount_mmk' => $pointsDiscount,
                    ],
                ]);

                if ($walletAmount > 0) {
                    $this->debitWallet($request->user()->id, $walletAmount, $order);
                }

                if ($pointsToRedeem > 0) {
                    PointLedger::query()->create([
                        'user_id' => $request->user()->id,
                        'type' => 'redeem',
                        'points' => -$pointsToRedeem,
                        'status' => 'available',
                        'order_id' => $order->id,
                        'meta' => ['discount_mmk' => $pointsDiscount],
                    ]);
                }

                CartItem::query()->where('cart_id', $cart->id)->delete();

                return $order->load(['items', 'shipments', 'payments']);
            });
        } catch (\RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'inventory_unavailable',
            ], 422);
        }

        return response()->json([
            'data' => $this->orderToArray($order),
        ], 201);
    }

    private function loadCart(Request $request): ?Cart
    {
        return Cart::query()
            ->where('user_id', $request->user()->id)
            ->with(['items.variant.product'])
            ->first();
    }

    /**
     * @return list<array{variant: ProductVariant, quantity: int, unit_price: int, line_total: int, seller_user_id: int, title: string, sku: string}>
     */
    private function buildCartLines(Cart $cart): array
    {
        $lines = [];

        foreach ($cart->items as $item) {
            $variant = $item->variant;
            $product = $variant?->product;

            if ($variant === null || $product === null || ! $variant->is_active || $product->listing_status !== ListingStatus::Approved || ! $product->is_active) {
                continue;
            }

            if ($item->quantity < 1) {
                continue;
            }

            $lines[] = [
                'variant' => $variant,
                'quantity' => $item->quantity,
                'unit_price' => $variant->price,
                'line_total' => $variant->price * $item->quantity,
                'seller_user_id' => $product->seller_user_id,
                'title' => $product->title_en,
                'sku' => $variant->sku,
            ];
        }

        return $lines;
    }

    /**
     * @param  list<array{variant: ProductVariant, quantity: int, unit_price: int, line_total: int, seller_user_id: int, title: string, sku: string}>  $lines
     */
    private function reserveInventory(array $lines, int $actorId): void
    {
        foreach ($lines as $line) {
            $remaining = $line['quantity'];
            $inventories = Inventory::query()
                ->where('product_variant_id', $line['variant']->id)
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            foreach ($inventories as $inventory) {
                if ($remaining <= 0) {
                    break;
                }

                $available = $inventory->on_hand - $inventory->reserved;
                if ($available <= 0) {
                    continue;
                }

                $take = min($available, $remaining);
                $inventory->reserved += $take;
                $inventory->save();

                InventoryMovement::query()->create([
                    'inventory_id' => $inventory->id,
                    'type' => 'reserve',
                    'quantity' => $take,
                    'reason' => 'checkout',
                    'actor_id' => $actorId,
                    'meta' => ['product_variant_id' => $line['variant']->id],
                ]);

                $remaining -= $take;
            }

            if ($remaining > 0) {
                throw new \RuntimeException('Insufficient stock for one or more items.');
            }
        }
    }

    /**
     * @param  list<array{variant: ProductVariant, quantity: int, unit_price: int, line_total: int, seller_user_id: int, title: string, sku: string}>  $lines
     * @return array<int, Shipment>
     */
    private function createShipments(Order $order, array $lines, DeliveryMode $deliveryMode, PaymentMethod $paymentMethod, int $shippingTotal): array
    {
        $sellerIds = collect($lines)->pluck('seller_user_id')->unique()->values();
        $shipmentsBySeller = [];
        $feeAssigned = false;

        foreach ($sellerIds as $sellerId) {
            $shipment = Shipment::query()->create([
                'order_id' => $order->id,
                'seller_user_id' => $sellerId,
                'delivery_mode' => $deliveryMode,
                'status' => ShipmentStatus::Pending,
                'fee' => ! $feeAssigned ? $shippingTotal : 0,
                'cod_custody' => $paymentMethod === PaymentMethod::Cod,
            ]);

            $feeAssigned = true;
            $shipmentsBySeller[(int) $sellerId] = $shipment;
        }

        return $shipmentsBySeller;
    }

    /**
     * @param  list<array{variant: ProductVariant, quantity: int, unit_price: int, line_total: int, seller_user_id: int, title: string, sku: string}>  $lines
     * @param  array<int, Shipment>  $shipmentsBySeller
     */
    private function createOrderItems(Order $order, array $lines, array $shipmentsBySeller): void
    {
        foreach ($lines as $line) {
            $shipment = $shipmentsBySeller[$line['seller_user_id']];

            OrderItem::query()->create([
                'order_id' => $order->id,
                'shipment_id' => $shipment->id,
                'seller_user_id' => $line['seller_user_id'],
                'product_variant_id' => $line['variant']->id,
                'title' => $line['title'],
                'sku' => $line['sku'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'discount' => 0,
                'line_total' => $line['line_total'],
            ]);
        }
    }

    private function nextOrderNo(): string
    {
        $date = now()->format('Ymd');
        $prefix = "ORD-{$date}-";

        $last = Order::query()
            ->where('order_no', 'like', $prefix.'%')
            ->orderByDesc('order_no')
            ->lockForUpdate()
            ->value('order_no');

        $sequence = 1;
        if ($last !== null && preg_match('/-(\d{4})$/', $last, $matches) === 1) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function initialPaymentStatus(PaymentMethod $method, int $grandTotal): PaymentStatus
    {
        if ($grandTotal === 0) {
            return PaymentStatus::Paid;
        }

        return match ($method) {
            PaymentMethod::Wallet => PaymentStatus::Paid,
            PaymentMethod::Cod, PaymentMethod::ManualTransfer => PaymentStatus::Pending,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function addressSnapshot(Address $address): array
    {
        return [
            'recipient_name' => $address->recipient_name,
            'phone' => $address->phone,
            'region' => $address->region,
            'township' => $address->township,
            'ward' => $address->ward,
            'address_line' => $address->address_line,
            'landmark' => $address->landmark,
            'lat' => $address->lat !== null ? (float) $address->lat : null,
            'lng' => $address->lng !== null ? (float) $address->lng : null,
        ];
    }

    private function debitWallet(int $userId, int $amount, Order $order): void
    {
        $wallet = Wallet::query()->firstOrCreate(
            ['user_id' => $userId],
            ['balance' => 0],
        );

        $wallet = Wallet::query()->where('id', $wallet->id)->lockForUpdate()->firstOrFail();

        if ($wallet->balance < $amount) {
            throw new \RuntimeException('Insufficient wallet balance.');
        }

        $wallet->balance -= $amount;
        $wallet->save();

        WalletLedger::query()->create([
            'wallet_id' => $wallet->id,
            'type' => 'debit',
            'amount' => -$amount,
            'balance_after' => $wallet->balance,
            'reference' => $order->order_no,
            'meta' => ['order_id' => $order->id],
        ]);
    }

    private function walletBalance(int $userId): int
    {
        return (int) Wallet::query()->where('user_id', $userId)->value('balance');
    }

    /**
     * @return array<string, mixed>
     */
    private function pointsQuote(Request $request): array
    {
        $available = $this->availablePoints($request->user()->id);
        $capPercent = (int) $this->resolver->get('points', 'redeem_percent_cap', 20);
        $pointValue = (int) $this->resolver->get('points', 'point_value_mmk', 1);

        return [
            'available' => $available,
            'redeem_percent_cap' => $capPercent,
            'point_value_mmk' => $pointValue,
            'enabled' => $this->resolver->enabled('points'),
        ];
    }

    private function availablePoints(int $userId): int
    {
        return (int) PointLedger::query()
            ->where('user_id', $userId)
            ->where('status', 'available')
            ->sum('points');
    }

    private function pointsDiscountMmk(Request $request, int $pointsToRedeem, int $preTotal): int|false
    {
        if ($pointsToRedeem === 0) {
            return 0;
        }

        if (! $this->resolver->enabled('points')) {
            return false;
        }

        if ($pointsToRedeem > $this->availablePoints($request->user()->id)) {
            return false;
        }

        $pointValue = (int) $this->resolver->get('points', 'point_value_mmk', 1);
        $discount = $pointsToRedeem * $pointValue;
        $capPercent = (int) $this->resolver->get('points', 'redeem_percent_cap', 20);
        $maxDiscount = (int) floor($preTotal * $capPercent / 100);

        if ($discount > $maxDiscount) {
            return false;
        }

        return $discount;
    }

    /**
     * @return list<string>
     */
    private function enabledPaymentMethods(): array
    {
        if (! $this->resolver->get('payments', 'enabled', true)) {
            return [];
        }

        $methods = [];

        if ($this->resolver->get('payments', 'cod_enabled', true)) {
            $methods[] = PaymentMethod::Cod->value;
        }

        if ($this->resolver->get('payments', 'manual_transfer_enabled', true)) {
            $methods[] = PaymentMethod::ManualTransfer->value;
        }

        if ($this->resolver->get('payments', 'wallet_enabled', true)) {
            $methods[] = PaymentMethod::Wallet->value;
        }

        return $methods;
    }

    /**
     * @return array{body: array<string, mixed>, status: int}|null
     */
    private function validatePaymentMethod(PaymentMethod $method, int $grandTotal): ?array
    {
        $enabled = $this->enabledPaymentMethods();

        if (! in_array($method->value, $enabled, true)) {
            return [
                'body' => [
                    'message' => 'This payment method is not available.',
                    'code' => 'payment_method_disabled',
                ],
                'status' => 422,
            ];
        }

        if ($method === PaymentMethod::Cod && $grandTotal > 0) {
            $max = (int) $this->resolver->get('payments', 'cod_max_amount', 500000);
            if ($grandTotal > $max) {
                return [
                    'body' => [
                        'message' => 'Order total exceeds cash on delivery limit.',
                        'code' => 'cod_limit_exceeded',
                    ],
                    'status' => 422,
                ];
            }
        }

        return null;
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
                'cod_custody' => $shipment->cod_custody,
            ])->values(),
            'payments' => $order->payments->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'method' => $payment->method->value,
                'amount' => $payment->amount,
                'status' => $payment->status->value,
            ])->values(),
            'created_at' => $order->created_at?->utc()->toIso8601String(),
        ];
    }
}
