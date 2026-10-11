<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $addresses = Address::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $addresses->map(fn (Address $address) => $this->addressToArray($address))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->addressRules());

        $address = DB::transaction(function () use ($request, $validated): Address {
            if (($validated['is_default'] ?? false) === true) {
                $this->clearDefaultForUser($request->user()->id);
            }

            return Address::query()->create([
                ...$validated,
                'user_id' => $request->user()->id,
            ]);
        });

        return response()->json([
            'data' => $this->addressToArray($address),
        ], 201);
    }

    public function show(Request $request, Address $address): JsonResponse
    {
        if (! $this->ownsAddress($request, $address)) {
            return $this->notFoundResponse();
        }

        return response()->json([
            'data' => $this->addressToArray($address),
        ]);
    }

    public function update(Request $request, Address $address): JsonResponse
    {
        if (! $this->ownsAddress($request, $address)) {
            return $this->notFoundResponse();
        }

        $validated = $request->validate($this->addressRules());

        DB::transaction(function () use ($request, $address, $validated): void {
            if (($validated['is_default'] ?? false) === true) {
                $this->clearDefaultForUser($request->user()->id, $address->id);
            }

            $address->fill($validated);
            $address->save();
        });

        return response()->json([
            'data' => $this->addressToArray($address->fresh()),
        ]);
    }

    public function destroy(Request $request, Address $address): JsonResponse
    {
        if (! $this->ownsAddress($request, $address)) {
            return $this->notFoundResponse();
        }

        $address->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function addressRules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:255'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'region' => ['required', 'string', 'max:255'],
            'township' => ['required', 'string', 'max:255'],
            'ward' => ['nullable', 'string', 'max:255'],
            'address_line' => ['required', 'string', 'max:500'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function addressToArray(Address $address): array
    {
        return [
            'id' => $address->id,
            'label' => $address->label,
            'recipient_name' => $address->recipient_name,
            'phone' => $address->phone,
            'region' => $address->region,
            'township' => $address->township,
            'ward' => $address->ward,
            'address_line' => $address->address_line,
            'landmark' => $address->landmark,
            'lat' => $address->lat !== null ? (float) $address->lat : null,
            'lng' => $address->lng !== null ? (float) $address->lng : null,
            'is_default' => $address->is_default,
            'created_at' => $address->created_at?->utc()->toIso8601String(),
            'updated_at' => $address->updated_at?->utc()->toIso8601String(),
        ];
    }

    private function ownsAddress(Request $request, Address $address): bool
    {
        return $address->user_id === $request->user()->id;
    }

    private function notFoundResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Address not found.',
            'code' => 'not_found',
        ], 404);
    }

    private function clearDefaultForUser(int $userId, ?int $exceptId = null): void
    {
        Address::query()
            ->where('user_id', $userId)
            ->when($exceptId !== null, fn ($query) => $query->where('id', '!=', $exceptId))
            ->update(['is_default' => false]);
    }
}
