<?php

namespace Database\Factories;

use App\Enums\DeliveryMode;
use App\Enums\ResellerStatus;
use App\Models\Reseller;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reseller>
 */
class ResellerFactory extends Factory
{
    protected $model = Reseller::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'shop_name' => fake()->company(),
            'status' => ResellerStatus::Applied,
            'default_delivery_mode' => DeliveryMode::GtgRider,
        ];
    }
}
