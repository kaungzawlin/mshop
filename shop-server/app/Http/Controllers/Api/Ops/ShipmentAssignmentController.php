<?php

namespace App\Http\Controllers\Api\Ops;

use App\Enums\Role;
use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Rider;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShipmentAssignmentController extends Controller
{
    public function assign(Request $request, Shipment $shipment): JsonResponse
    {
        $validated = $request->validate([
            'rider_id' => ['required', 'integer', 'exists:users,id'],
            'eta_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $riderUser = User::query()->findOrFail($validated['rider_id']);

        if ($riderUser->role !== Role::Rider) {
            throw ValidationException::withMessages([
                'rider_id' => ['The selected user is not a rider.'],
            ]);
        }

        Rider::query()->firstOrCreate(
            ['user_id' => $riderUser->id],
            ['status' => 'active']
        );

        if (! in_array($shipment->status, [ShipmentStatus::Pending, ShipmentStatus::Assigned, ShipmentStatus::Failed], true)) {
            throw ValidationException::withMessages([
                'shipment' => ['Shipment cannot be reassigned in its current status.'],
            ]);
        }

        DB::transaction(function () use ($request, $shipment, $validated, $riderUser): void {
            $previousRiderId = $shipment->rider_id;

            $timeline = $shipment->timeline ?? [];
            $timeline[] = [
                'at' => now()->utc()->toIso8601String(),
                'event' => 'rider_assigned',
                'actor_id' => $request->user()->id,
                'rider_id' => $riderUser->id,
                'note' => $validated['note'] ?? null,
            ];

            $shipment->rider_id = $riderUser->id;
            $shipment->status = ShipmentStatus::Assigned;

            if ($request->filled('eta_at')) {
                $shipment->eta_at = $validated['eta_at'];
            }

            $shipment->timeline = $timeline;
            $shipment->save();

            AuditLog::query()->create([
                'actor_id' => $request->user()->id,
                'action' => 'shipment.rider_assigned',
                'target_type' => 'shipment',
                'target_id' => $shipment->id,
                'reason' => $validated['note'] ?? null,
                'old_values' => ['rider_id' => $previousRiderId],
                'new_values' => ['rider_id' => $riderUser->id],
                'created_at' => now(),
            ]);
        });

        return response()->json([
            'data' => $this->shipmentToArray($shipment->fresh()),
        ]);
    }

    public function forceMajeure(Request $request, Shipment $shipment): JsonResponse
    {
        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $shipment, $validated): void {
            $timeline = $shipment->timeline ?? [];
            $timeline[] = [
                'at' => now()->utc()->toIso8601String(),
                'event' => 'force_majeure',
                'actor_id' => $request->user()->id,
                'note' => $validated['note'],
            ];

            $shipment->timeline = $timeline;
            $shipment->save();

            AuditLog::query()->create([
                'actor_id' => $request->user()->id,
                'action' => 'shipment.force_majeure',
                'target_type' => 'shipment',
                'target_id' => $shipment->id,
                'reason' => $validated['note'],
                'created_at' => now(),
            ]);
        });

        return response()->json([
            'data' => $this->shipmentToArray($shipment->fresh()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function shipmentToArray(Shipment $shipment): array
    {
        return [
            'id' => $shipment->id,
            'order_id' => $shipment->order_id,
            'seller_user_id' => $shipment->seller_user_id,
            'rider_id' => $shipment->rider_id,
            'delivery_mode' => $shipment->delivery_mode->value,
            'status' => $shipment->status->value,
            'fee' => $shipment->fee,
            'cod_custody' => $shipment->cod_custody,
            'eta_at' => $shipment->eta_at?->utc()->toIso8601String(),
            'attempt_log' => $shipment->attempt_log,
            'pod_refs' => $shipment->pod_refs,
            'timeline' => $shipment->timeline,
            'created_at' => $shipment->created_at?->utc()->toIso8601String(),
            'updated_at' => $shipment->updated_at?->utc()->toIso8601String(),
        ];
    }
}
