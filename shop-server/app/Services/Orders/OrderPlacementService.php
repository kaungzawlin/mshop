<?php

namespace App\Services\Orders;

use App\Models\Inventory;
use App\Models\InventoryMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderPlacementService
{
    /**
     * @param  list<array{product_variant_id: int, quantity: int}>  $lines
     */
    public function reserveInventory(array $lines, int $actorId, ?int $orderId = null): void
    {
        if ($lines === []) {
            return;
        }

        DB::transaction(function () use ($lines, $actorId, $orderId): void {
            $variantIds = collect($lines)->pluck('product_variant_id')->unique()->sort()->values()->all();

            $inventories = Inventory::query()
                ->whereIn('product_variant_id', $variantIds)
                ->orderBy('product_variant_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('product_variant_id');

            foreach ($lines as $line) {
                $variantId = $line['product_variant_id'];
                $quantity = $line['quantity'];

                if ($quantity < 1) {
                    throw ValidationException::withMessages([
                        'quantity' => ['Quantity must be at least 1.'],
                    ]);
                }

                $inventory = $inventories->get($variantId);

                if ($inventory === null) {
                    throw ValidationException::withMessages([
                        'product_variant_id' => ["No inventory record for variant {$variantId}."],
                    ]);
                }

                $available = $inventory->on_hand - $inventory->reserved;

                if ($available < $quantity) {
                    throw ValidationException::withMessages([
                        'product_variant_id' => ["Insufficient stock for variant {$variantId}."],
                    ]);
                }

                $inventory->reserved += $quantity;
                $inventory->save();

                InventoryMovement::query()->create([
                    'inventory_id' => $inventory->id,
                    'type' => 'reserve',
                    'quantity' => $quantity,
                    'reason' => 'order_placement',
                    'actor_id' => $actorId,
                    'meta' => [
                        'order_id' => $orderId,
                        'product_variant_id' => $variantId,
                    ],
                ]);
            }
        });
    }
}
