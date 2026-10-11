<?php

namespace App\Http\Controllers\Api\Reseller;

use App\Enums\DeliveryMode;
use App\Enums\ListingStatus;
use App\Enums\ResellerStatus;
use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Reseller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;

class ListingController extends Controller
{
    public function apply(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shop_name' => ['required', 'string', 'max:255'],
            'pickup_address' => ['nullable', 'string', 'max:500'],
            'region' => ['nullable', 'string', 'max:255'],
            'township' => ['nullable', 'string', 'max:255'],
            'payout_account' => ['nullable', 'string', 'max:255'],
            'default_delivery_mode' => ['sometimes', new Enum(DeliveryMode::class)],
        ]);

        $user = $request->user();

        $reseller = Reseller::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'shop_name' => $validated['shop_name'],
                'status' => ResellerStatus::Applied,
                'pickup_address' => $validated['pickup_address'] ?? null,
                'region' => $validated['region'] ?? null,
                'township' => $validated['township'] ?? null,
                'payout_account' => $validated['payout_account'] ?? null,
                'default_delivery_mode' => $validated['default_delivery_mode'] ?? DeliveryMode::GtgRider,
            ]
        );

        if (! $reseller->wasRecentlyCreated) {
            $reseller->fill([
                'shop_name' => $validated['shop_name'],
                'pickup_address' => $validated['pickup_address'] ?? $reseller->pickup_address,
                'region' => $validated['region'] ?? $reseller->region,
                'township' => $validated['township'] ?? $reseller->township,
                'payout_account' => $validated['payout_account'] ?? $reseller->payout_account,
            ]);

            if ($request->filled('default_delivery_mode')) {
                $reseller->default_delivery_mode = $validated['default_delivery_mode'];
            }

            $reseller->save();
        }

        return response()->json([
            'data' => $this->resellerToArray($reseller),
        ], $reseller->wasRecentlyCreated ? 201 : 200);
    }

    public function index(Request $request): JsonResponse
    {
        $reseller = $this->resolveReseller($request);

        if ($reseller instanceof JsonResponse) {
            return $reseller;
        }

        $validated = $request->validate([
            'search' => ['sometimes', 'string', 'max:255'],
        ]);

        $query = Product::query()
            ->where('reseller_id', $reseller->id)
            ->where('seller_user_id', $request->user()->id)
            ->with(['category', 'variants']);

        if ($request->filled('search')) {
            $search = $validated['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('title_en', 'ilike', "%{$search}%")
                    ->orWhere('title_my', 'ilike', "%{$search}%")
                    ->orWhere('slug', 'ilike', "%{$search}%");
            });
        }

        $paginator = $query->orderBy('id')->paginate(15);

        $onHandByVariant = $this->onHandSumsByVariant(
            $paginator->getCollection()->flatMap(fn (Product $product) => $product->variants->pluck('id'))->all()
        );

        return response()->json([
            'data' => $paginator->getCollection()->map(
                fn (Product $product) => $this->productToArray($product, $onHandByVariant)
            )->values(),
            'meta' => $this->paginationMeta($paginator),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $reseller = $this->resolveReseller($request);

        if ($reseller instanceof JsonResponse) {
            return $reseller;
        }

        if ($denied = $this->denyIfSuspended($reseller)) {
            return $denied;
        }

        $validated = $request->validate($this->productRules());

        $product = DB::transaction(function () use ($request, $validated, $reseller): Product {
            $productData = collect($validated)->except('variants')->all();
            $productData['seller_user_id'] = $request->user()->id;
            $productData['reseller_id'] = $reseller->id;
            $productData['listing_status'] = ListingStatus::Pending;

            $product = Product::query()->create($productData);

            foreach ($validated['variants'] as $variantData) {
                $this->createVariantWithInventory($product, $variantData);
            }

            return $product;
        });

        $product->load(['category', 'variants']);

        return response()->json([
            'data' => $this->productToArray(
                $product,
                $this->onHandSumsByVariant($product->variants->pluck('id')->all())
            ),
        ], 201);
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        if ($response = $this->assertOwnProduct($request, $product)) {
            return $response;
        }

        $product->load(['category', 'variants']);

        return response()->json([
            'data' => $this->productToArray(
                $product,
                $this->onHandSumsByVariant($product->variants->pluck('id')->all())
            ),
        ]);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        if ($response = $this->assertOwnProduct($request, $product)) {
            return $response;
        }

        $reseller = $this->resolveReseller($request);

        if ($reseller instanceof JsonResponse) {
            return $reseller;
        }

        if ($denied = $this->denyIfSuspended($reseller)) {
            return $denied;
        }

        $validated = $request->validate($this->productRules($product, requireVariants: false));

        $product->fill(collect($validated)->except('variants')->all());
        $product->listing_status = ListingStatus::Pending;
        $product->save();

        $product = $product->fresh(['category', 'variants']);

        return response()->json([
            'data' => $this->productToArray(
                $product,
                $this->onHandSumsByVariant($product->variants->pluck('id')->all())
            ),
        ]);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        if ($response = $this->assertOwnProduct($request, $product)) {
            return $response;
        }

        $product->delete();

        return response()->json(['ok' => true]);
    }

    public function adjustInventory(Request $request, Product $product, ProductVariant $variant): JsonResponse
    {
        if ($response = $this->assertOwnProduct($request, $product)) {
            return $response;
        }

        if ($variant->product_id !== $product->id) {
            return response()->json([
                'message' => 'Variant does not belong to this product.',
                'code' => 'not_found',
            ], 404);
        }

        $reseller = $this->resolveReseller($request);

        if ($reseller instanceof JsonResponse) {
            return $reseller;
        }

        if ($denied = $this->denyIfSuspended($reseller)) {
            return $denied;
        }

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'not_in:0'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $inventory = DB::transaction(function () use ($request, $variant, $validated): Inventory {
            $inventory = Inventory::query()->firstOrCreate(
                [
                    'product_variant_id' => $variant->id,
                    'location_id' => null,
                ],
                [
                    'on_hand' => 0,
                ]
            );

            $newOnHand = $inventory->on_hand + (int) $validated['quantity'];

            if ($newOnHand < 0) {
                throw ValidationException::withMessages([
                    'quantity' => ['Insufficient on-hand inventory for this adjustment.'],
                ]);
            }

            $inventory->on_hand = $newOnHand;
            $inventory->save();

            InventoryMovement::query()->create([
                'inventory_id' => $inventory->id,
                'type' => 'adjustment',
                'quantity' => (int) $validated['quantity'],
                'reason' => $validated['reason'] ?? null,
                'actor_id' => $request->user()->id,
                'meta' => [
                    'product_id' => $variant->product_id,
                    'product_variant_id' => $variant->id,
                ],
            ]);

            return $inventory->fresh();
        });

        return response()->json([
            'data' => [
                'inventory_id' => $inventory->id,
                'product_variant_id' => $variant->id,
                'on_hand' => $inventory->on_hand,
            ],
        ]);
    }

    private function resolveReseller(Request $request): Reseller|JsonResponse
    {
        $reseller = Reseller::query()->where('user_id', $request->user()->id)->first();

        if ($reseller === null) {
            return response()->json([
                'message' => 'Reseller profile not found. Submit an application first.',
                'code' => 'reseller_profile_missing',
            ], 403);
        }

        return $reseller;
    }

    private function denyIfSuspended(Reseller $reseller): ?JsonResponse
    {
        if ($reseller->status === ResellerStatus::Suspended) {
            return response()->json([
                'message' => 'Your reseller account is suspended.',
                'code' => 'reseller_suspended',
            ], 403);
        }

        return null;
    }

    private function assertOwnProduct(Request $request, Product $product): ?JsonResponse
    {
        $reseller = Reseller::query()->where('user_id', $request->user()->id)->first();

        if ($reseller === null) {
            return response()->json([
                'message' => 'Reseller profile not found. Submit an application first.',
                'code' => 'reseller_profile_missing',
            ], 403);
        }

        if ($product->seller_user_id !== $request->user()->id || $product->reseller_id !== $reseller->id) {
            return response()->json([
                'message' => 'Product not found.',
                'code' => 'not_found',
            ], 404);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $variantData
     */
    private function createVariantWithInventory(Product $product, array $variantData): ProductVariant
    {
        $onHand = (int) ($variantData['on_hand'] ?? 0);
        unset($variantData['on_hand']);

        $variant = $product->variants()->create($variantData);

        Inventory::query()->create([
            'product_variant_id' => $variant->id,
            'location_id' => null,
            'on_hand' => $onHand,
        ]);

        return $variant;
    }

    /**
     * @return array<string, mixed>
     */
    private function productRules(?Product $product = null, bool $requireVariants = true): array
    {
        $rules = [
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'title_my' => ['required', 'string', 'max:255'],
            'title_en' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('products', 'slug')->ignore($product?->id),
            ],
            'description_my' => ['nullable', 'string'],
            'description_en' => ['nullable', 'string'],
            'brand' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'variants' => [$requireVariants ? 'required' : 'sometimes', 'array', 'min:1'],
            'variants.*.sku' => ['required', 'string', 'max:255', 'distinct', Rule::unique('product_variants', 'sku')],
            'variants.*.attributes' => ['nullable', 'array'],
            'variants.*.price' => ['required', 'integer', 'min:0'],
            'variants.*.compare_at' => ['nullable', 'integer', 'min:0'],
            'variants.*.weight_grams' => ['nullable', 'integer', 'min:0'],
            'variants.*.is_active' => ['sometimes', 'boolean'],
            'variants.*.on_hand' => ['sometimes', 'integer', 'min:0'],
        ];

        if ($product !== null) {
            $rules['variants.*.sku'] = ['prohibited'];
            $rules['variants'] = ['prohibited'];
        }

        return $rules;
    }

    /**
     * @param  array<int, int>  $variantIds
     * @return array<int, int>
     */
    private function onHandSumsByVariant(array $variantIds): array
    {
        if ($variantIds === []) {
            return [];
        }

        return Inventory::query()
            ->whereIn('product_variant_id', $variantIds)
            ->selectRaw('product_variant_id, COALESCE(SUM(on_hand), 0) as on_hand_sum')
            ->groupBy('product_variant_id')
            ->pluck('on_hand_sum', 'product_variant_id')
            ->map(fn ($sum) => (int) $sum)
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function resellerToArray(Reseller $reseller): array
    {
        return [
            'id' => $reseller->id,
            'user_id' => $reseller->user_id,
            'shop_name' => $reseller->shop_name,
            'status' => $reseller->status->value,
            'pickup_address' => $reseller->pickup_address,
            'region' => $reseller->region,
            'township' => $reseller->township,
            'payout_account' => $reseller->payout_account,
            'default_delivery_mode' => $reseller->default_delivery_mode->value,
            'approved_at' => $reseller->approved_at?->utc()->toIso8601String(),
        ];
    }

    /**
     * @param  array<int, int>  $onHandByVariant
     * @return array<string, mixed>
     */
    private function productToArray(Product $product, array $onHandByVariant): array
    {
        return [
            'id' => $product->id,
            'category_id' => $product->category_id,
            'title_my' => $product->title_my,
            'title_en' => $product->title_en,
            'slug' => $product->slug,
            'description_my' => $product->description_my,
            'description_en' => $product->description_en,
            'brand' => $product->brand,
            'listing_status' => $product->listing_status->value,
            'is_active' => $product->is_active,
            'category' => $product->relationLoaded('category') && $product->category
                ? [
                    'id' => $product->category->id,
                    'name_en' => $product->category->name_en,
                    'name_my' => $product->category->name_my,
                    'slug' => $product->category->slug,
                ]
                : null,
            'variants' => $product->relationLoaded('variants')
                ? $product->variants->map(fn (ProductVariant $variant) => [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'attributes' => $variant->attributes,
                    'price' => $variant->price,
                    'compare_at' => $variant->compare_at,
                    'weight_grams' => $variant->weight_grams,
                    'is_active' => $variant->is_active,
                    'on_hand' => $onHandByVariant[$variant->id] ?? 0,
                ])->values()
                : [],
            'created_at' => $product->created_at?->utc()->toIso8601String(),
            'updated_at' => $product->updated_at?->utc()->toIso8601String(),
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
