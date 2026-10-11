<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PointLedger;
use App\Models\Tier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PointsController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $now = now();

        $available = (int) PointLedger::query()
            ->where('user_id', $userId)
            ->where('status', 'available')
            ->where(function ($query) use ($now): void {
                $query->whereNull('available_at')
                    ->orWhere('available_at', '<=', $now);
            })
            ->sum('points');

        $pending = (int) PointLedger::query()
            ->where('user_id', $userId)
            ->where(function ($query) use ($now): void {
                $query->where('status', 'pending')
                    ->orWhere(function ($inner) use ($now): void {
                        $inner->where('status', 'available')
                            ->where('available_at', '>', $now);
                    });
            })
            ->sum('points');

        $earnedPoints = (int) PointLedger::query()
            ->where('user_id', $userId)
            ->where('points', '>', 0)
            ->sum('points');

        $tier = Tier::query()
            ->where('min_points_earned', '<=', $earnedPoints)
            ->orderByDesc('min_points_earned')
            ->orderByDesc('sort_order')
            ->first();

        return response()->json([
            'data' => [
                'available_points' => $available,
                'pending_points' => $pending,
                'earned_points' => $earnedPoints,
                'tier' => $tier ? $this->tierToArray($tier) : null,
            ],
        ]);
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
        ];
    }
}
