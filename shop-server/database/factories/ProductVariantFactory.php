<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => strtoupper(Str::random(8)).'-'.fake()->unique()->numerify('####'),
            'attributes' => ['size' => fake()->randomElement(['S', 'M', 'L'])],
            'price' => fake()->numberBetween(1000, 500000),
            'is_active' => true,
        ];
    }
}
