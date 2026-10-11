<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BannerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Banner::query()->orderBy('sort_order')->orderByDesc('id');

        if ($request->filled('active')) {
            $query->where('is_active', filter_var($request->input('active'), FILTER_VALIDATE_BOOLEAN));
        }

        $paginator = $query->paginate(20);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (Banner $banner) => $this->toArray($banner))->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());

        $banner = DB::transaction(function () use ($request, $validated): Banner {
            $banner = Banner::query()->create([
                ...$validated,
                'created_by' => $request->user()->id,
            ]);

            AuditLog::query()->create([
                'actor_id' => $request->user()->id,
                'action' => 'banner.created',
                'target_type' => 'banner',
                'target_id' => $banner->id,
                'new_values' => $this->toArray($banner),
                'created_at' => now(),
            ]);

            return $banner;
        });

        return response()->json(['data' => $this->toArray($banner)], 201);
    }

    public function show(Banner $banner): JsonResponse
    {
        return response()->json(['data' => $this->toArray($banner)]);
    }

    public function update(Request $request, Banner $banner): JsonResponse
    {
        $validated = $request->validate($this->rules(updating: true));
        $old = $this->toArray($banner);

        DB::transaction(function () use ($request, $banner, $validated, $old): void {
            $banner->fill($validated)->save();

            AuditLog::query()->create([
                'actor_id' => $request->user()->id,
                'action' => 'banner.updated',
                'target_type' => 'banner',
                'target_id' => $banner->id,
                'old_values' => $old,
                'new_values' => $this->toArray($banner->fresh()),
                'created_at' => now(),
            ]);
        });

        return response()->json(['data' => $this->toArray($banner->fresh())]);
    }

    public function destroy(Request $request, Banner $banner): JsonResponse
    {
        DB::transaction(function () use ($request, $banner): void {
            $banner->delete();

            AuditLog::query()->create([
                'actor_id' => $request->user()->id,
                'action' => 'banner.deleted',
                'target_type' => 'banner',
                'target_id' => $banner->id,
                'created_at' => now(),
            ]);
        });

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(bool $updating = false): array
    {
        $required = $updating ? ['sometimes'] : ['required'];

        return [
            'title' => [...$required, 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:500'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'link_url' => ['nullable', 'string', 'max:2048'],
            'locale' => ['nullable', 'string', Rule::in(['my', 'en'])],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(Banner $banner): array
    {
        return [
            'id' => $banner->id,
            'title' => $banner->title,
            'subtitle' => $banner->subtitle,
            'image_url' => $banner->image_url,
            'link_url' => $banner->link_url,
            'locale' => $banner->locale,
            'sort_order' => $banner->sort_order,
            'is_active' => $banner->is_active,
            'starts_at' => $banner->starts_at?->utc()->toIso8601String(),
            'ends_at' => $banner->ends_at?->utc()->toIso8601String(),
            'created_by' => $banner->created_by,
            'created_at' => $banner->created_at?->utc()->toIso8601String(),
            'updated_at' => $banner->updated_at?->utc()->toIso8601String(),
        ];
    }
}
