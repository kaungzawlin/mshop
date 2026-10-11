<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'listing_status' => ['sometimes', new Enum(ListingStatus::class)],
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'search' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $query = Product::query()->with(['category', 'variants']);

        if ($request->filled('listing_status')) {
            $query->where('listing_status', $validated['listing_status']);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $validated['category_id']);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', (bool) $validated['is_active']);
        }

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
        $validated = $request->validate($this->productRules());

        $product = DB::transaction(function () use ($request, $validated): Product {
            $productData = collect($validated)->except('variants')->all();
            $product = Product::query()->create($productData);

            foreach ($validated['variants'] ?? [] as $variantData) {
                $this->createVariantWithInventory($product, $variantData);
            }

            AuditLog::query()->create([
                'actor_id' => $request->user()->id,
                'action' => 'product.created',
                'target_type' => 'product',
                'target_id' => $product->id,
                'reason' => 'Admin created product',
                'old_values' => null,
                'new_values' => ['listing_status' => $product->listing_status->value],
                'created_at' => now(),
            ]);

            return $product;
        });

        $product->load(['category', 'variants']);

        return response()->json([
            'data' => $this->productToArray($product, $this->onHandSumsByVariant($product->variants->pluck('id')->all())),
        ], 201);
    }

    public function show(Product $product): JsonResponse
    {
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
        $validated = $request->validate($this->productRules($product, requireVariants: false));

        $product->fill(collect($validated)->except('variants')->all());
        $product->save();

        $product = $product->fresh(['category', 'variants']);

        return response()->json([
            'data' => $this->productToArray(
                $product,
                $this->onHandSumsByVariant($product->variants->pluck('id')->all())
            ),
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(['ok' => true]);
    }

    public function approve(Request $request, Product $product): JsonResponse
    {
        if ($product->listing_status === ListingStatus::Approved) {
            return response()->json([
                'data' => $this->productToArray(
                    $product->load(['category', 'variants']),
                    $this->onHandSumsByVariant($product->variants->pluck('id')->all())
                ),
            ]);
        }

        DB::transaction(function () use ($request, $product): void {
            $oldStatus = $product->listing_status->value;

            $product->listing_status = ListingStatus::Approved;
            $product->save();

            AuditLog::query()->create([
                'actor_id' => $request->user()->id,
                'action' => 'product.approved',
                'target_type' => 'product',
                'target_id' => $product->id,
                'reason' => 'Admin approved product listing',
                'old_values' => ['listing_status' => $oldStatus],
                'new_values' => ['listing_status' => ListingStatus::Approved->value],
                'created_at' => now(),
            ]);
        });

        $product->load(['category', 'variants']);

        return response()->json([
            'data' => $this->productToArray(
                $product,
                $this->onHandSumsByVariant($product->variants->pluck('id')->all())
            ),
        ]);
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
            'seller_user_id' => ['required', 'integer', 'exists:users,id'],
            'reseller_id' => ['nullable', 'integer', 'exists:resellers,id'],
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
            'listing_status' => ['sometimes', new Enum(ListingStatus::class)],
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
     * @param  array<int, int>  $onHandByVariant
     * @return array<string, mixed>
     */
    private function productToArray(Product $product, array $onHandByVariant): array
    {
        return [
            'id' => $product->id,
            'seller_user_id' => $product->seller_user_id,
            'reseller_id' => $product->reseller_id,
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
