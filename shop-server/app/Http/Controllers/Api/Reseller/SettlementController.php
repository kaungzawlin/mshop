<?php

namespace App\Http\Controllers\Api\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Reseller;
use App\Models\Settlement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettlementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $reseller = $this->resolveReseller($request);

        if ($reseller instanceof JsonResponse) {
            return $reseller;
        }

        $validated = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(['pending', 'paid', 'cancelled'])],
        ]);

        $query = Settlement::query()
            ->with(['lines'])
            ->where('reseller_id', $reseller->id);

        if ($request->filled('status')) {
            $query->where('status', $validated['status']);
        }

        $paginator = $query->orderByDesc('id')->paginate(15);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (Settlement $settlement) => $this->settlementToArray($settlement))->values(),
            'meta' => $this->paginationMeta($paginator),
        ]);
    }

    private function resolveReseller(Request $request): Reseller|JsonResponse
    {
        $reseller = Reseller::query()->where('user_id', $request->user()->id)->first();

        if ($reseller === null) {
            return response()->json([
                'message' => 'Reseller profile not found.',
                'code' => 'reseller_not_found',
            ], 404);
        }

        return $reseller;
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
