<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\CustomerReliabilityEvent;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReliabilityController extends Controller
{
    public function index(Request $request, User $customer): JsonResponse
    {
        if (! $this->assertCustomerTarget($customer)) {
            return response()->json([
                'message' => 'The selected user is not a customer.',
                'code' => 'invalid_target',
            ], 422);
        }

        $paginator = CustomerReliabilityEvent::query()
            ->with(['actor', 'order'])
            ->where('user_id', $customer->id)
            ->orderByDesc('id')
            ->paginate(15);

        $score = (int) CustomerReliabilityEvent::query()
            ->where('user_id', $customer->id)
            ->sum('delta');

        return response()->json([
            'customer' => (new UserResource($customer))->resolve(),
            'score' => $score,
            'data' => $paginator->getCollection()->map(fn (CustomerReliabilityEvent $event) => $this->eventToArray($event))->values(),
            'meta' => $this->paginationMeta($paginator),
        ]);
    }

    public function adjust(Request $request, User $customer): JsonResponse
    {
        if (! $this->assertCustomerTarget($customer)) {
            return response()->json([
                'message' => 'The selected user is not a customer.',
                'code' => 'invalid_target',
            ], 422);
        }

        $validated = $request->validate([
            'delta' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', 'string', 'max:500'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
        ]);

        if ($validated['order_id'] ?? null) {
            $orderOwned = Order::query()
                ->where('id', $validated['order_id'])
                ->where('user_id', $customer->id)
                ->exists();

            if (! $orderOwned) {
                throw ValidationException::withMessages([
                    'order_id' => ['Order does not belong to this customer.'],
                ]);
            }
        }

        $event = DB::transaction(function () use ($request, $customer, $validated): CustomerReliabilityEvent {
            $event = CustomerReliabilityEvent::query()->create([
                'user_id' => $customer->id,
                'delta' => $validated['delta'],
                'reason' => $validated['reason'],
                'actor_id' => $request->user()->id,
                'order_id' => $validated['order_id'] ?? null,
            ]);

            AuditLog::query()->create([
                'actor_id' => $request->user()->id,
                'action' => 'customer.reliability_adjusted',
                'target_type' => 'user',
                'target_id' => $customer->id,
                'reason' => $validated['reason'],
                'new_values' => [
                    'delta' => $validated['delta'],
                    'event_id' => $event->id,
                ],
                'created_at' => now(),
            ]);

            return $event;
        });

        return response()->json([
            'data' => $this->eventToArray($event->load(['actor', 'order'])),
        ], 201);
    }

    private function assertCustomerTarget(User $customer): bool
    {
        return $customer->role === Role::Customer;
    }

    /**
     * @return array<string, mixed>
     */
    private function eventToArray(CustomerReliabilityEvent $event): array
    {
        return [
            'id' => $event->id,
            'user_id' => $event->user_id,
            'delta' => $event->delta,
            'reason' => $event->reason,
            'order_id' => $event->order_id,
            'actor' => $event->relationLoaded('actor') && $event->actor
                ? [
                    'id' => $event->actor->id,
                    'name' => $event->actor->name,
                    'email' => $event->actor->email,
                ]
                : null,
            'created_at' => $event->created_at?->utc()->toIso8601String(),
            'updated_at' => $event->updated_at?->utc()->toIso8601String(),
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
