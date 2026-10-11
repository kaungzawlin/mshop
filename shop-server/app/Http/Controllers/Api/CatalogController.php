<?php

namespace App\Http\Controllers\Api;

use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function categories(): JsonResponse
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $categories->map(fn (Category $category) => $this->categoryToArray($category))->values(),
        ]);
    }

    public function products(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['sometimes', 'string', 'max:255'],
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
        ]);

        $query = Product::query()
            ->where('listing_status', ListingStatus::Approved)
            ->where('is_active', true)
            ->with(['category', 'variants' => fn ($builder) => $builder->where('is_active', true)->orderBy('id')]);

        if ($request->filled('q')) {
            $search = $validated['q'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('title_en', 'ilike', "%{$search}%")
                    ->orWhere('title_my', 'ilike', "%{$search}%")
                    ->orWhere('slug', 'ilike', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $validated['category_id']);
        }

        $paginator = $query->orderBy('id')->paginate(15);

        $variantIds = $paginator->getCollection()
            ->flatMap(fn (Product $product) => $product->variants->pluck('id'))
            ->all();

        $onHandByVariant = $this->onHandSumsByVariant($variantIds);

        return response()->json([
            'data' => $paginator->getCollection()->map(
                fn (Product $product) => $this->publicProductToArray($product, $onHandByVariant)
            )->values(),
            'meta' => $this->paginationMeta($paginator),
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $product = Product::query()
            ->where('slug', $slug)
            ->where('listing_status', ListingStatus::Approved)
            ->where('is_active', true)
            ->with(['category', 'variants' => fn ($builder) => $builder->where('is_active', true)->orderBy('id')])
            ->first();

        if ($product === null) {
            return response()->json([
                'message' => 'Product not found.',
                'code' => 'not_found',
            ], 404);
        }

        $onHandByVariant = $this->onHandSumsByVariant($product->variants->pluck('id')->all());

        return response()->json([
            'data' => $this->publicProductToArray($product, $onHandByVariant),
        ]);
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
    private function publicProductToArray(Product $product, array $onHandByVariant): array
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
            'category' => $product->category ? $this->categoryToArray($product->category) : null,
            'variants' => $product->variants->map(
                fn ($variant) => $this->variantToArray($variant, $onHandByVariant[$variant->id] ?? 0)
            )->values(),
            'on_hand' => (int) $product->variants->sum(fn ($variant) => $onHandByVariant[$variant->id] ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function categoryToArray(Category $category): array
    {
        return [
            'id' => $category->id,
            'parent_id' => $category->parent_id,
            'name_my' => $category->name_my,
            'name_en' => $category->name_en,
            'slug' => $category->slug,
            'sort_order' => $category->sort_order,
            'is_active' => $category->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function variantToArray($variant, int $onHand): array
    {
        return [
            'id' => $variant->id,
            'sku' => $variant->sku,
            'attributes' => $variant->attributes,
            'price' => $variant->price,
            'compare_at' => $variant->compare_at,
            'weight_grams' => $variant->weight_grams,
            'is_active' => $variant->is_active,
            'on_hand' => $onHand,
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
