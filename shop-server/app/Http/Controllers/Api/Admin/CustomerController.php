<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query()->where('role', Role::Customer);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        $paginator = $query->orderBy('id')->paginate(15);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (User $user) => (new UserResource($user))->resolve())->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function block(Request $request, User $customer): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        if (! $this->assertCustomerTarget($request, $customer)) {
            return response()->json([
                'message' => 'You do not have permission to perform this action.',
                'code' => 'forbidden',
            ], 403);
        }

        if ($customer->status === UserStatus::Blocked) {
            return response()->json([
                'user' => (new UserResource($customer))->resolve(),
            ]);
        }

        DB::transaction(function () use ($request, $customer, $validated): void {
            $oldStatus = $customer->status->value;

            $customer->status = UserStatus::Blocked;
            $customer->blocked_at = now();
            $customer->save();

            AuditLog::query()->create([
                'actor_id' => $request->user()->id,
                'action' => 'customer.blocked',
                'target_type' => 'user',
                'target_id' => $customer->id,
                'reason' => $validated['reason'],
                'old_values' => ['status' => $oldStatus],
                'new_values' => ['status' => UserStatus::Blocked->value],
                'created_at' => now(),
            ]);
        });

        return response()->json([
            'user' => (new UserResource($customer->fresh()))->resolve(),
        ]);
    }

    public function unblock(Request $request, User $customer): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        if (! $this->assertCustomerTarget($request, $customer)) {
            return response()->json([
                'message' => 'You do not have permission to perform this action.',
                'code' => 'forbidden',
            ], 403);
        }

        if ($customer->status === UserStatus::Active) {
            return response()->json([
                'user' => (new UserResource($customer))->resolve(),
            ]);
        }

        DB::transaction(function () use ($request, $customer, $validated): void {
            $oldStatus = $customer->status->value;

            $customer->status = UserStatus::Active;
            $customer->blocked_at = null;
            $customer->save();

            AuditLog::query()->create([
                'actor_id' => $request->user()->id,
                'action' => 'customer.unblocked',
                'target_type' => 'user',
                'target_id' => $customer->id,
                'reason' => $validated['reason'],
                'old_values' => ['status' => $oldStatus],
                'new_values' => ['status' => UserStatus::Active->value],
                'created_at' => now(),
            ]);
        });

        return response()->json([
            'user' => (new UserResource($customer->fresh()))->resolve(),
        ]);
    }

    private function assertCustomerTarget(Request $request, User $customer): bool
    {
        $actor = $request->user();

        if ($customer->id === $actor->id) {
            return false;
        }

        if ($customer->role !== Role::Customer) {
            return false;
        }

        return true;
    }
}
