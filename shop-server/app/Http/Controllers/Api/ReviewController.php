<?php

namespace App\Http\Controllers\Api;

use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Review;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReviewController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shipment_id' => ['required', 'integer', 'exists:shipments,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'product_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'seller_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'delivery_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'body' => ['nullable', 'string', 'max:5000'],
        ]);

        if (
            ! isset($validated['product_rating'])
            && ! isset($validated['seller_rating'])
            && ! isset($validated['delivery_rating'])
        ) {
            throw ValidationException::withMessages([
                'product_rating' => ['At least one rating is required.'],
            ]);
        }

        $shipment = Shipment::query()->with('order')->findOrFail($validated['shipment_id']);

        if ($shipment->status !== ShipmentStatus::Delivered) {
            throw ValidationException::withMessages([
                'shipment_id' => ['Reviews are only allowed for delivered shipments.'],
            ]);
        }

        if ($shipment->order === null || $shipment->order->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You do not have permission to perform this action.',
                'code' => 'forbidden',
            ], 403);
        }

        $duplicate = Review::query()
            ->where('shipment_id', $shipment->id)
            ->where('user_id', $request->user()->id)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'shipment_id' => ['You have already reviewed this shipment.'],
            ]);
        }

        $review = DB::transaction(function () use ($request, $validated, $shipment): Review {
            $review = Review::query()->create([
                'shipment_id' => $shipment->id,
                'user_id' => $request->user()->id,
                'product_id' => $validated['product_id'] ?? null,
                'product_rating' => $validated['product_rating'] ?? null,
                'seller_rating' => $validated['seller_rating'] ?? null,
                'delivery_rating' => $validated['delivery_rating'] ?? null,
                'body' => $validated['body'] ?? null,
                'moderation_status' => 'visible',
            ]);

            AuditLog::query()->create([
                'actor_id' => $request->user()->id,
                'action' => 'review.created',
                'target_type' => 'review',
                'target_id' => $review->id,
                'new_values' => ['shipment_id' => $shipment->id],
                'created_at' => now(),
            ]);

            return $review;
        });

        return response()->json([
            'data' => $this->reviewToArray($review),
        ], 201);
    }

    public function moderate(Request $request, Review $review): JsonResponse
    {
        $validated = $request->validate([
            'moderation_status' => ['required', 'string', Rule::in(['visible', 'hidden', 'flagged'])],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($request, $review, $validated): void {
            $oldStatus = $review->moderation_status;

            $review->moderation_status = $validated['moderation_status'];
            $review->save();

            AuditLog::query()->create([
                'actor_id' => $request->user()->id,
                'action' => 'review.moderated',
                'target_type' => 'review',
                'target_id' => $review->id,
                'reason' => $validated['note'] ?? null,
                'old_values' => ['moderation_status' => $oldStatus],
                'new_values' => ['moderation_status' => $validated['moderation_status']],
                'created_at' => now(),
            ]);
        });

        return response()->json([
            'data' => $this->reviewToArray($review->fresh()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function reviewToArray(Review $review): array
    {
        return [
            'id' => $review->id,
            'shipment_id' => $review->shipment_id,
            'user_id' => $review->user_id,
            'product_id' => $review->product_id,
            'product_rating' => $review->product_rating,
            'seller_rating' => $review->seller_rating,
            'delivery_rating' => $review->delivery_rating,
            'body' => $review->body,
            'moderation_status' => $review->moderation_status,
            'created_at' => $review->created_at?->utc()->toIso8601String(),
            'updated_at' => $review->updated_at?->utc()->toIso8601String(),
        ];
    }
}
