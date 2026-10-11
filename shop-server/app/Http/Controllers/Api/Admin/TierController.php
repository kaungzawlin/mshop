<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $paginator = Tier::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(15);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (Tier $tier) => $this->tierToArray($tier))->values(),
            'meta' => $this->paginationMeta($paginator),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->tierRules());

        $tier = DB::transaction(function () use ($request, $validated): Tier {
            $tier = Tier::query()->create($validated);

            AuditLog::query()->create([
                'actor_id' => $request->user()->id,
                'action' => 'tier.created',
                'target_type' => 'tier',
                'target_id' => $tier->id,
                'new_values' => $validated,
                'created_at' => now(),
            ]);

            return $tier;
        });

        return response()->json([
            'data' => $this->tierToArray($tier),
        ], 201);
    }

    public function show(Tier $tier): JsonResponse
    {
        return response()->json([
            'data' => $this->tierToArray($tier),
        ]);
    }

    public function update(Request $request, Tier $tier): JsonResponse
    {
        $validated = $request->validate($this->tierRules($tier));

        DB::transaction(function () use ($request, $tier, $validated): void {
            $oldValues = $tier->only(array_keys($validated));

            $tier->fill($validated);
            $tier->save();

            AuditLog::query()->create([
                'actor_id' => $request->user()->id,
                'action' => 'tier.updated',
                'target_type' => 'tier',
                'target_id' => $tier->id,
                'old_values' => $oldValues,
                'new_values' => $validated,
                'created_at' => now(),
            ]);
        });

        return response()->json([
            'data' => $this->tierToArray($tier->fresh()),
        ]);
    }

    public function destroy(Request $request, Tier $tier): JsonResponse
    {
        DB::transaction(function () use ($request, $tier): void {
            $tierId = $tier->id;

            AuditLog::query()->create([
                'actor_id' => $request->user()->id,
                'action' => 'tier.deleted',
                'target_type' => 'tier',
                'target_id' => $tierId,
                'old_values' => $this->tierToArray($tier),
                'created_at' => now(),
            ]);

            $tier->delete();
        });

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function tierRules(?Tier $tier = null): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('tiers', 'code')->ignore($tier?->id),
            ],
            'name_my' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'min_points_earned' => ['required', 'integer', 'min:0'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tierToArray(Tier $tier): array
    {
        return [
            'id' => $tier->id,
            'code' => $tier->code,
            'name_my' => $tier->name_my,
            'name_en' => $tier->name_en,
            'min_points_earned' => $tier->min_points_earned,
            'sort_order' => $tier->sort_order,
            'created_at' => $tier->created_at?->utc()->toIso8601String(),
            'updated_at' => $tier->updated_at?->utc()->toIso8601String(),
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
