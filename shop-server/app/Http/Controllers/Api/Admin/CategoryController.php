<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'is_active' => ['sometimes', 'boolean'],
            'search' => ['sometimes', 'string', 'max:255'],
        ]);

        $query = Category::query();

        if ($request->has('is_active')) {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        if ($request->filled('search')) {
            $search = $validated['search'];
            $query->where(function ($builder) use ($search): void {
                $builder->where('name_en', 'ilike', "%{$search}%")
                    ->orWhere('name_my', 'ilike', "%{$search}%")
                    ->orWhere('slug', 'ilike', "%{$search}%");
            });
        }

        $paginator = $query->orderBy('sort_order')->orderBy('id')->paginate(15);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (Category $category) => $this->categoryToArray($category))->values(),
            'meta' => $this->paginationMeta($paginator),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->categoryRules());

        $category = Category::query()->create($validated);

        return response()->json([
            'data' => $this->categoryToArray($category),
        ], 201);
    }

    public function show(Category $category): JsonResponse
    {
        return response()->json([
            'data' => $this->categoryToArray($category),
        ]);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate($this->categoryRules($category));

        $category->fill($validated);
        $category->save();

        return response()->json([
            'data' => $this->categoryToArray($category->fresh()),
        ]);
    }

    public function destroy(Category $category): JsonResponse
    {
        $category->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function categoryRules(?Category $category = null): array
    {
        return [
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'name_my' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'slug')->ignore($category?->id),
            ],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
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
            'created_at' => $category->created_at?->utc()->toIso8601String(),
            'updated_at' => $category->updated_at?->utc()->toIso8601String(),
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
