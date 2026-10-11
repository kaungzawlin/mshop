<?php

namespace Database\Factories;

use App\Enums\ListingStatus;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->words(3, true);

        return [
            'seller_user_id' => User::factory(),
            'title_my' => $title,
            'title_en' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numerify('####'),
            'listing_status' => ListingStatus::Draft,
            'is_active' => true,
        ];
    }
}
