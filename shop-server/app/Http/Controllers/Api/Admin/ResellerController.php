<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ResellerStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Reseller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Enum;

class ResellerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', new Enum(ResellerStatus::class)],
            'search' => ['sometimes', 'string', 'max:255'],
        ]);

        $query = Reseller::query()->with('user');

        if ($request->filled('status')) {
            $query->where('status', $validated['status']);
        }

        if ($request->filled('search')) {
            $search = $validated['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('shop_name', 'ilike', "%{$search}%")
                    ->orWhereHas('user', function (Builder $userQuery) use ($search): void {
                        $userQuery->where('name', 'ilike', "%{$search}%")
                            ->orWhere('email', 'ilike', "%{$search}%");
                    });
            });
        }

        $paginator = $query->orderBy('id')->paginate(15);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (Reseller $reseller) => $this->resellerToArray($reseller))->values(),
            'meta' => $this->paginationMeta($paginator),
        ]);
    }

    public function approve(Request $request, Reseller $reseller): JsonResponse
    {
        if ($reseller->status === ResellerStatus::Approved) {
            return response()->json([
                'data' => $this->resellerToArray($reseller->load('user')),
            ]);
        }

        DB::transaction(function () use ($request, $reseller): void {
            $oldStatus = $reseller->status->value;

            $reseller->status = ResellerStatus::Approved;
            $reseller->approved_at = now();
            $reseller->save();

            AuditLog::query()->create([
                'actor_id' => $request->user()->id,
                'action' => 'reseller.approved',
                'target_type' => 'reseller',
                'target_id' => $reseller->id,
                'reason' => 'Admin approved reseller',
                'old_values' => ['status' => $oldStatus],
                'new_values' => ['status' => ResellerStatus::Approved->value],
                'created_at' => now(),
            ]);
        });

        return response()->json([
            'data' => $this->resellerToArray($reseller->fresh(['user'])),
        ]);
    }

    public function suspend(Request $request, Reseller $reseller): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        if ($reseller->status === ResellerStatus::Suspended) {
            return response()->json([
                'data' => $this->resellerToArray($reseller->load('user')),
            ]);
        }

        DB::transaction(function () use ($request, $reseller, $validated): void {
            $oldStatus = $reseller->status->value;

            $reseller->status = ResellerStatus::Suspended;
            $reseller->save();

            AuditLog::query()->create([
                'actor_id' => $request->user()->id,
                'action' => 'reseller.suspended',
                'target_type' => 'reseller',
                'target_id' => $reseller->id,
                'reason' => $validated['reason'],
                'old_values' => ['status' => $oldStatus],
                'new_values' => ['status' => ResellerStatus::Suspended->value],
                'created_at' => now(),
            ]);
        });

        return response()->json([
            'data' => $this->resellerToArray($reseller->fresh(['user'])),
        ]);
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
            'kyc_notes' => $reseller->kyc_notes,
            'pickup_address' => $reseller->pickup_address,
            'region' => $reseller->region,
            'township' => $reseller->township,
            'payout_account' => $reseller->payout_account,
            'default_delivery_mode' => $reseller->default_delivery_mode->value,
            'approved_at' => $reseller->approved_at?->utc()->toIso8601String(),
            'user' => $reseller->relationLoaded('user') && $reseller->user
                ? [
                    'id' => $reseller->user->id,
                    'name' => $reseller->user->name,
                    'email' => $reseller->user->email,
                ]
                : null,
            'created_at' => $reseller->created_at?->utc()->toIso8601String(),
            'updated_at' => $reseller->updated_at?->utc()->toIso8601String(),
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
