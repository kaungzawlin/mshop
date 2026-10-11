<?php

namespace App\Http\Controllers\Api\Rider;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;

class ShipmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', new Enum(ShipmentStatus::class)],
        ]);

        $query = Shipment::query()
            ->with(['order', 'items'])
            ->where('rider_id', $request->user()->id)
            ->whereNotIn('status', [ShipmentStatus::Cancelled]);

        if ($request->filled('status')) {
            $query->where('status', $validated['status']);
        }

        $paginator = $query->orderByDesc('id')->paginate(15);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (Shipment $shipment) => $this->shipmentToArray($shipment))->values(),
            'meta' => $this->paginationMeta($paginator),
        ]);
    }

    public function updateStatus(Request $request, Shipment $shipment): JsonResponse
    {
        if (! $this->assertAssignedRider($request, $shipment)) {
            return $this->forbiddenResponse();
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in([
                ShipmentStatus::OutForDelivery->value,
                ShipmentStatus::Delivered->value,
                ShipmentStatus::Failed->value,
            ])],
            'note' => ['nullable', 'string', 'max:1000'],
            'pod_refs' => ['sometimes', 'array'],
            'pod_refs.*' => ['string', 'max:500'],
        ]);

        $newStatus = ShipmentStatus::from($validated['status']);

        if (! $this->canTransitionTo($shipment->status, $newStatus)) {
            throw ValidationException::withMessages([
                'status' => ['Invalid status transition for this shipment.'],
            ]);
        }

        DB::transaction(function () use ($request, $shipment, $validated, $newStatus): void {
            $previousStatus = $shipment->status->value;

            $attemptLog = $shipment->attempt_log ?? [];
            $attemptLog[] = [
                'at' => now()->utc()->toIso8601String(),
                'status' => $newStatus->value,
                'actor_id' => $request->user()->id,
                'note' => $validated['note'] ?? null,
            ];

            $timeline = $shipment->timeline ?? [];
            $timeline[] = [
                'at' => now()->utc()->toIso8601String(),
                'event' => 'status_changed',
                'status' => $newStatus->value,
                'actor_id' => $request->user()->id,
                'note' => $validated['note'] ?? null,
            ];

            $shipment->status = $newStatus;
            $shipment->attempt_log = $attemptLog;
            $shipment->timeline = $timeline;

            if (array_key_exists('pod_refs', $validated)) {
                $shipment->pod_refs = $validated['pod_refs'];
            }

            $shipment->save();

            AuditLog::query()->create([
                'actor_id' => $request->user()->id,
                'action' => 'shipment.status_updated',
                'target_type' => 'shipment',
                'target_id' => $shipment->id,
                'reason' => $validated['note'] ?? null,
                'old_values' => ['status' => $previousStatus],
                'new_values' => ['status' => $newStatus->value],
                'created_at' => now(),
            ]);
        });

        return response()->json([
            'data' => $this->shipmentToArray($shipment->fresh(['order', 'items'])),
        ]);
    }

    public function markCodCollected(Request $request, Shipment $shipment): JsonResponse
    {
        if (! $this->assertAssignedRider($request, $shipment)) {
            return $this->forbiddenResponse();
        }

        if (! $shipment->cod_custody) {
            throw ValidationException::withMessages([
                'shipment' => ['This shipment is not COD custody.'],
            ]);
        }

        $shipment->loadMissing('order');

        if ($shipment->order === null || $shipment->order->payment_method !== PaymentMethod::Cod) {
            throw ValidationException::withMessages([
                'shipment' => ['Order is not cash on delivery.'],
            ]);
        }

        $validated = $request->validate([
            'reference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($request, $shipment, $validated): void {
            $payment = Payment::query()
                ->where('order_id', $shipment->order_id)
                ->where('method', PaymentMethod::Cod)
                ->orderByDesc('id')
                ->first();

            if ($payment === null) {
                $payment = Payment::query()->create([
                    'order_id' => $shipment->order_id,
                    'method' => PaymentMethod::Cod,
                    'amount' => $shipment->order->grand_total,
                    'status' => PaymentStatus::Paid,
                    'reference' => $validated['reference'] ?? null,
                    'verified_by' => $request->user()->id,
                    'verified_at' => now(),
                    'meta' => [
                        'shipment_id' => $shipment->id,
                        'collected_by_rider_id' => $request->user()->id,
                    ],
                ]);
            } else {
                $payment->status = PaymentStatus::Paid;
                $payment->verified_by = $request->user()->id;
                $payment->verified_at = now();
                if ($request->filled('reference')) {
                    $payment->reference = $validated['reference'];
                }
                $payment->save();
            }

            $order = $shipment->order;
            $order->payment_status = PaymentStatus::Paid;
            $order->save();

            $timeline = $shipment->timeline ?? [];
            $timeline[] = [
                'at' => now()->utc()->toIso8601String(),
                'event' => 'cod_collected',
                'actor_id' => $request->user()->id,
                'note' => $validated['note'] ?? null,
                'payment_id' => $payment->id,
            ];
            $shipment->timeline = $timeline;
            $shipment->save();

            AuditLog::query()->create([
                'actor_id' => $request->user()->id,
                'action' => 'payment.cod_collected',
                'target_type' => 'payment',
                'target_id' => $payment->id,
                'reason' => $validated['note'] ?? null,
                'new_values' => [
                    'order_id' => $shipment->order_id,
                    'shipment_id' => $shipment->id,
                    'status' => PaymentStatus::Paid->value,
                ],
                'created_at' => now(),
            ]);
        });

        return response()->json([
            'data' => $this->shipmentToArray($shipment->fresh(['order', 'items'])),
        ]);
    }

    private function assertAssignedRider(Request $request, Shipment $shipment): bool
    {
        return $shipment->rider_id === $request->user()->id;
    }

    private function canTransitionTo(ShipmentStatus $current, ShipmentStatus $next): bool
    {
        return match ($current) {
            ShipmentStatus::Assigned => in_array($next, [ShipmentStatus::OutForDelivery, ShipmentStatus::Failed], true),
            ShipmentStatus::OutForDelivery => in_array($next, [ShipmentStatus::Delivered, ShipmentStatus::Failed], true),
            default => false,
        };
    }

    private function forbiddenResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'You do not have permission to perform this action.',
            'code' => 'forbidden',
        ], 403);
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
            'order' => $shipment->relationLoaded('order') && $shipment->order
                ? [
                    'id' => $shipment->order->id,
                    'order_no' => $shipment->order->order_no,
                    'payment_method' => $shipment->order->payment_method->value,
                    'payment_status' => $shipment->order->payment_status->value,
                    'grand_total' => $shipment->order->grand_total,
                ]
                : null,
            'items' => $shipment->relationLoaded('items')
                ? $shipment->items->map(fn ($item) => [
                    'id' => $item->id,
                    'title' => $item->title,
                    'sku' => $item->sku,
                    'quantity' => $item->quantity,
                    'line_total' => $item->line_total,
                ])->values()
                : [],
            'created_at' => $shipment->created_at?->utc()->toIso8601String(),
            'updated_at' => $shipment->updated_at?->utc()->toIso8601String(),
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
