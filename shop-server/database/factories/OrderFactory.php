<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_no' => 'ORD-'.strtoupper(Str::random(10)),
            'user_id' => User::factory(),
            'status' => OrderStatus::Placed,
            'payment_method' => PaymentMethod::Cod,
            'payment_status' => PaymentStatus::Pending,
            'subtotal' => 0,
            'discount_total' => 0,
            'shipping_total' => 0,
            'points_redeemed' => 0,
            'wallet_applied' => 0,
            'grand_total' => 0,
        ];
    }
}
