<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CartController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $cart = $this->cartForUser($request);

        return response()->json([
            'data' => $this->cartToArray($cart),
        ]);
    }

    public function updateItems(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.product_variant_id' => ['required', 'integer', Rule::exists('product_variants', 'id')],
            'items.*.quantity' => ['required', 'integer', 'min:0'],
            'merge' => ['sometimes', 'boolean'],
        ]);

        $merge = (bool) ($validated['merge'] ?? false);
        $cart = $this->cartForUser($request);

        DB::transaction(function () use ($cart, $validated, $merge): void {
            foreach ($validated['items'] as $line) {
                $variantId = (int) $line['product_variant_id'];
                $quantity = (int) $line['quantity'];

                $existing = CartItem::query()
                    ->where('cart_id', $cart->id)
                    ->where('product_variant_id', $variantId)
                    ->first();

                if ($quantity === 0) {
                    $existing?->delete();

                    continue;
                }

                if ($existing !== null) {
                    $existing->quantity = $merge ? $existing->quantity + $quantity : $quantity;
                    $existing->save();

                    continue;
                }

                if ($merge && $quantity < 0) {
                    continue;
                }

                CartItem::query()->create([
                    'cart_id' => $cart->id,
                    'product_variant_id' => $variantId,
                    'quantity' => $quantity,
                ]);
            }
        });

        $cart->load(['items.variant.product']);

        return response()->json([
            'data' => $this->cartToArray($cart->fresh(['items.variant.product'])),
        ]);
    }

    private function cartForUser(Request $request): Cart
    {
        return Cart::query()->firstOrCreate(
            ['user_id' => $request->user()->id],
            ['user_id' => $request->user()->id],
        )->load(['items.variant.product']);
    }

    /**
     * @return array<string, mixed>
     */
    private function cartToArray(Cart $cart): array
    {
        $items = $cart->items->map(function (CartItem $item) {
            $variant = $item->variant;
            $product = $variant?->product;

            return [
                'id' => $item->id,
                'product_variant_id' => $item->product_variant_id,
                'quantity' => $item->quantity,
                'variant' => $variant ? $this->variantToArray($variant) : null,
                'product' => $product ? $this->productToArray($product) : null,
                'line_total' => $variant ? $variant->price * $item->quantity : 0,
            ];
        });

        return [
            'id' => $cart->id,
            'items' => $items->values(),
            'item_count' => (int) $cart->items->sum('quantity'),
            'subtotal' => (int) $items->sum('line_total'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function variantToArray(ProductVariant $variant): array
    {
        return [
            'id' => $variant->id,
            'product_id' => $variant->product_id,
            'sku' => $variant->sku,
            'attributes' => $variant->attributes,
            'price' => $variant->price,
            'compare_at' => $variant->compare_at,
            'weight_grams' => $variant->weight_grams,
            'is_active' => $variant->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function productToArray(Product $product): array
    {
        return [
            'id' => $product->id,
            'seller_user_id' => $product->seller_user_id,
            'title_my' => $product->title_my,
            'title_en' => $product->title_en,
            'slug' => $product->slug,
        ];
    }
}
