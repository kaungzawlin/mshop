<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Settlement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SettlementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reseller_id' => ['sometimes', 'integer', 'exists:resellers,id'],
            'status' => ['sometimes', 'string', Rule::in(['pending', 'paid', 'cancelled'])],
        ]);

        $query = Settlement::query()->with(['reseller', 'lines']);

        if ($request->filled('reseller_id')) {
            $query->where('reseller_id', $validated['reseller_id']);
        }

        if ($request->filled('status')) {
            $query->where('status', $validated['status']);
        }

        $paginator = $query->orderByDesc('id')->paginate(15);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (Settlement $settlement) => $this->settlementToArray($settlement))->values(),
            'meta' => $this->paginationMeta($paginator),
        ]);
    }

    public function markPaid(Request $request, Settlement $settlement): JsonResponse
    {
        if ($settlement->status === 'paid') {
            return response()->json([
                'data' => $this->settlementToArray($settlement->load(['reseller', 'lines'])),
            ]);
        }

        if ($settlement->status !== 'pending') {
            throw ValidationException::withMessages([
                'settlement' => ['Only pending settlements can be marked paid.'],
            ]);
        }

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($request, $settlement, $validated): void {
            $settlement->status = 'paid';
            $settlement->paid_at = now();
            $settlement->save();

            AuditLog::query()->create([
                'actor_id' => $request->user()->id,
                'action' => 'settlement.marked_paid',
                'target_type' => 'settlement',
                'target_id' => $settlement->id,
                'reason' => $validated['note'] ?? null,
                'new_values' => [
                    'status' => 'paid',
                    'paid_at' => $settlement->paid_at?->utc()->toIso8601String(),
                    'reference' => $validated['reference'] ?? null,
                ],
                'created_at' => now(),
            ]);
        });

        return response()->json([
            'data' => $this->settlementToArray($settlement->fresh(['reseller', 'lines'])),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function settlementToArray(Settlement $settlement): array
    {
        return [
            'id' => $settlement->id,
            'reseller_id' => $settlement->reseller_id,
            'period_start' => $settlement->period_start,
            'period_end' => $settlement->period_end,
            'gross_sales' => $settlement->gross_sales,
            'commission' => $settlement->commission,
            'net_payout' => $settlement->net_payout,
            'status' => $settlement->status,
            'paid_at' => $settlement->paid_at?->utc()->toIso8601String(),
            'reseller' => $settlement->relationLoaded('reseller') && $settlement->reseller
                ? [
                    'id' => $settlement->reseller->id,
                    'shop_name' => $settlement->reseller->shop_name,
                    'user_id' => $settlement->reseller->user_id,
                ]
                : null,
            'lines' => $settlement->relationLoaded('lines')
                ? $settlement->lines->map(fn ($line) => [
                    'id' => $line->id,
                    'order_id' => $line->order_id,
                    'amount' => $line->amount,
                    'commission' => $line->commission,
                    'note' => $line->note,
                ])->values()
                : [],
            'created_at' => $settlement->created_at?->utc()->toIso8601String(),
            'updated_at' => $settlement->updated_at?->utc()->toIso8601String(),
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
