<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BannerPublicController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Banner::query()
            ->currentlyScheduled()
            ->orderBy('sort_order')
            ->orderByDesc('id');

        if ($request->filled('locale')) {
            $locale = $request->string('locale')->toString();
            $query->where(function ($builder) use ($locale): void {
                $builder->whereNull('locale')->orWhere('locale', $locale);
            });
        }

        $banners = $query->get()->map(fn (Banner $banner) => [
            'id' => $banner->id,
            'title' => $banner->title,
            'subtitle' => $banner->subtitle,
            'image_url' => $banner->image_url,
            'link_url' => $banner->link_url,
            'locale' => $banner->locale,
            'sort_order' => $banner->sort_order,
            'starts_at' => $banner->starts_at?->utc()->toIso8601String(),
            'ends_at' => $banner->ends_at?->utc()->toIso8601String(),
        ]);

        return response()->json(['data' => $banners->values()]);
    }
}
