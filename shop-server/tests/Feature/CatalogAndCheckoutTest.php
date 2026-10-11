<?php

use App\Enums\ListingStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserStatus;
use App\Models\Address;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Wallet;
use Database\Seeders\SettingsSeeder;

beforeEach(function (): void {
    $this->seed(SettingsSeeder::class);
});

it('lists approved catalog products', function (): void {
    $seller = User::factory()->admin()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->create([
        'seller_user_id' => $seller->id,
        'category_id' => $category->id,
        'listing_status' => ListingStatus::Approved,
        'is_active' => true,
        'slug' => 'demo-tea',
    ]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'price' => 1500,
    ]);
    Inventory::query()->create([
        'product_variant_id' => $variant->id,
        'on_hand' => 10,
        'reserved' => 0,
        'damaged' => 0,
    ]);

    $this->getJson('/api/catalog/products')
        ->assertOk()
        ->assertJsonPath('data.0.slug', 'demo-tea');

    $this->getJson('/api/catalog/products/demo-tea')
        ->assertOk()
        ->assertJsonPath('data.slug', 'demo-tea');
});

it('places a COD order from cart', function (): void {
    $customer = User::factory()->customer()->create();
    $token = $customer->createToken('web', ['*'], now()->addDays(30))->plainTextToken;
    $seller = User::factory()->admin()->create();
    $product = Product::factory()->create([
        'seller_user_id' => $seller->id,
        'listing_status' => ListingStatus::Approved,
        'is_active' => true,
    ]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'price' => 2000,
    ]);
    Inventory::query()->create([
        'product_variant_id' => $variant->id,
        'on_hand' => 5,
        'reserved' => 0,
        'damaged' => 0,
    ]);
    $address = Address::query()->create([
        'user_id' => $customer->id,
        'recipient_name' => 'Aung',
        'phone' => '09123456789',
        'region' => 'Yangon',
        'township' => 'Bahan',
        'address_line' => '42 Test St',
        'is_default' => true,
    ]);

    $this->withToken($token)
        ->patchJson('/api/cart', [
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 2],
            ],
        ])
        ->assertOk();

    $this->withToken($token)
        ->postJson('/api/orders', [
            'address_id' => $address->id,
            'payment_method' => PaymentMethod::Cod->value,
            'delivery_mode' => 'gtg_rider',
        ])
        ->assertCreated()
        ->assertJsonPath('data.payment_method', 'cod');

    expect(Inventory::query()->where('product_variant_id', $variant->id)->value('reserved'))->toBe(2);
});

it('blocks checkout when pending deposit', function (): void {
    $admin = User::factory()->admin()->create();
    $adminToken = $admin->createToken('web', ['*'], now()->addDays(30))->plainTextToken;
    $this->withToken($adminToken)->putJson('/api/admin/settings/deposit', [
        'enabled' => true,
        'amount_mmk' => 10000,
    ])->assertOk();

    $customer = User::factory()->customer()->create();
    $customer->forceFill(['status' => UserStatus::PendingDeposit])->save();
    $token = $customer->createToken('web', ['*'], now()->addDays(30))->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/orders', [
            'address_id' => 1,
            'payment_method' => 'cod',
        ])
        ->assertStatus(403)
        ->assertJsonPath('code', 'deposit_required');
});

it('creates wallet for user factory path', function (): void {
    $user = User::factory()->create();
    Wallet::query()->create(['user_id' => $user->id, 'balance' => 0]);
    expect(Wallet::query()->where('user_id', $user->id)->exists())->toBeTrue();
});
